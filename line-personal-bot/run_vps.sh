#!/bin/bash
# ==========================================
# LINE Personal Bot - VPS Deployment Script
# ==========================================
# รัน API เป็นหลัก และเปิด Chromium worker เฉพาะโหมด legacy/browser

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
cd "$SCRIPT_DIR"

if [ "${EUID:-$(id -u)}" -eq 0 ]; then
    SUDO=""
else
    SUDO="sudo"
fi

get_env_value() {
    local key="$1"
    local default_value="$2"

    if [ ! -f ".env" ]; then
        printf '%s' "$default_value"
        return
    fi

    local value
    value="$(awk -F= -v key="$key" '
        $0 ~ "^[[:space:]]*" key "[[:space:]]*=" {
            sub(/^[^=]*=/, "", $0)
            gsub(/^[[:space:]]+|[[:space:]]+$/, "", $0)
            gsub(/^["'"'"']|["'"'"']$/, "", $0)
            print $0
            exit
        }
    ' .env | tr -d '\r')"

    if [ -n "$value" ]; then
        printf '%s' "$value"
    else
        printf '%s' "$default_value"
    fi
}

port_is_listening() {
    local port="$1"

    if command -v ss >/dev/null 2>&1; then
        ss -ltn 2>/dev/null | awk '{print $4}' | grep -Eq "(^|:)${port}$"
        return
    fi

    if command -v netstat >/dev/null 2>&1; then
        netstat -ltn 2>/dev/null | awk '{print $4}' | grep -Eq "(^|:)${port}$"
        return
    fi

    return 1
}

start_api() {
    mkdir -p "$SCRIPT_DIR/logs"
    touch "$SCRIPT_DIR/logs/api.log"

    if port_is_listening "$API_PORT"; then
        echo "LINE Personal API ทำงานอยู่แล้วบนพอร์ต $API_PORT"
    else
        echo "กำลังเริ่ม LINE Personal API บนพอร์ต $API_PORT..."
        nohup python api.py > "$SCRIPT_DIR/logs/api.log" 2>&1 &
        API_PID=$!
        echo "LINE Personal API PID: $API_PID"
    fi

    sleep 2
    echo "=========================================="
    echo "API log:"
    tail -n 20 "$SCRIPT_DIR/logs/api.log" || true
    echo "=========================================="
}

echo "=========================================="
echo "1. กำลังติดตั้งเครื่องมือ Python"
echo "=========================================="
$SUDO apt-get update
$SUDO apt-get install -y python3-pip python3-venv

echo "=========================================="
echo "2. ตรวจสอบ/อัปเดต Python Virtual Environment"
echo "=========================================="
if [ ! -d "venv-310" ]; then
    echo "กำลังสร้าง venv ใหม่..."
    python3 -m venv venv-310
fi

source venv-310/bin/activate
pip install -r requirements.txt

LINE_SEND_MODE_CONFIG="$(get_env_value "LINE_SEND_MODE" "disabled" | tr '[:upper:]' '[:lower:]')"
API_PORT="$(get_env_value "API_PORT" "5000")"
WORKER_PORT="$(get_env_value "WORKER_PORT" "5001")"

if [ "$LINE_SEND_MODE_CONFIG" = "automation" ]; then
    echo "=========================================="
    echo "3. ตรวจพบ LINE_SEND_MODE=automation"
    echo "=========================================="
    echo "โหมดนี้ใช้ Windows LINE Desktop Worker ผ่าน SSH tunnel"
    echo "กำลังปิด Chromium worker เก่าบน VPS เพื่อคืนพอร์ต $WORKER_PORT..."
    pkill -f "chromium_line_worker.py" || true
    pkill -f "Xvfb :99" || true
    echo "ข้ามการเปิด Chromium worker บน VPS แล้ว"

    start_api
    echo "ให้เปิด Windows worker และ start_worker_tunnel.ps1 ค้างไว้"
    exit 0
fi

if [ "$LINE_SEND_MODE_CONFIG" = "mock" ] || \
   [ "$LINE_SEND_MODE_CONFIG" = "disabled" ]; then
    echo "=========================================="
    echo "3. ตรวจพบ LINE_SEND_MODE=$LINE_SEND_MODE_CONFIG"
    echo "=========================================="
    echo "โหมดนี้ให้ api.py จัดการการส่งเอง ไม่ต้องเปิด Chromium worker บน VPS"
    pkill -f "chromium_line_worker.py" || true
    pkill -f "Xvfb :99" || true
    start_api
    exit 0
fi

echo "=========================================="
echo "3. ติดตั้งเครื่องมือ Chromium Worker"
echo "=========================================="
echo "LINE_SEND_MODE=$LINE_SEND_MODE_CONFIG จะใช้ Chromium worker legacy บนพอร์ต $WORKER_PORT"
$SUDO apt-get install -y xvfb imagemagick

echo "=========================================="
echo "4. ติดตั้ง Playwright Chromium"
echo "=========================================="
export PLAYWRIGHT_BROWSERS_PATH="$SCRIPT_DIR/pw-browsers"
python -m playwright install chromium
python -m playwright install-deps chromium

# ลบ extension เก่าทิ้งเพื่อให้ setup_extension.py โหลดใหม่และ patch manifest.json
rm -rf line_extension
# ดาวน์โหลด LINE Extension (ถ้ายังไม่มี)
python setup_extension.py

echo "=========================================="
echo "5. กำลังเริ่ม LINE Personal API"
echo "=========================================="
start_api

echo "=========================================="
echo "6. กำลังเริ่มการทำงานของ Worker (Background/Non-Root)"
echo "=========================================="
# กวาดล้างโพรเซสเก่า
pkill -f "chromium_line_worker.py" || true
pkill -f "Xvfb :99" || true
sleep 2

# ตรวจสอบว่ามี user 'linebot' หรือไม่ ถ้าไม่มีให้สร้าง (รัน worker แบบไม่ใช้ root เพื่อให้ Chrome Sandbox ทำงานได้)
if ! id -u linebot > /dev/null 2>&1; then
    echo "Creating non-root user 'linebot' for Chrome Sandbox..."
    $SUDO useradd -m -s /bin/bash linebot
fi

# ตั้งค่าสิทธิ์ให้ linebot เข้าถึงโฟลเดอร์รันได้
mkdir -p "$SCRIPT_DIR/chromium_data" "$SCRIPT_DIR/automation" "$SCRIPT_DIR/logs"
touch "$SCRIPT_DIR/worker.log"
$SUDO chown -R linebot:linebot \
    "$SCRIPT_DIR/chromium_data" \
    "$SCRIPT_DIR/automation" \
    "$SCRIPT_DIR/logs" \
    "$SCRIPT_DIR/worker.log" >/dev/null 2>&1 || true
# อนุญาตให้ทะลุโฟลเดอร์ Plesk
$SUDO chmod a+x /var /var/www /var/www/vhosts /var/www/vhosts/imzshop97.com /var/www/vhosts/imzshop97.com/httpdocs

export PLAYWRIGHT_BROWSERS_PATH="$SCRIPT_DIR/pw-browsers"
$SUDO chown -R linebot:linebot "$PLAYWRIGHT_BROWSERS_PATH" >/dev/null 2>&1 || true
$SUDO chown -R linebot:linebot "$SCRIPT_DIR/line_extension" >/dev/null 2>&1 || true

# เริ่ม xvfb display :99 เป็น root ได้ ไม่เป็นไร
Xvfb :99 -screen 0 1280x720x24 -ac &
XVFB_PID=$!
sleep 1
echo "Xvfb started on :99 (PID: $XVFB_PID)"

echo "✨ ติดตั้งและเริ่มการทำงานเรียบร้อยแล้ว!"
echo "Xvfb PID: $XVFB_PID (display :99)"
echo "=========================================="
echo "Log (กำลังรอ worker เริ่ม...):"

# รัน Worker ด้วย linebot user (เป็น background)
$SUDO su - linebot -c "cd '$SCRIPT_DIR' && export DISPLAY=:99 && export PLAYWRIGHT_BROWSERS_PATH='$PLAYWRIGHT_BROWSERS_PATH' && source venv-310/bin/activate && nohup python chromium_line_worker.py > worker.log 2>&1 &"

sleep 5
tail -n 20 worker.log
