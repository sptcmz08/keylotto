#!/bin/bash
# ==========================================
# LINE Chromium Worker - VPS Deployment Script
# ==========================================
# รัน Chrome เป็น user linebot + xvfb display capture

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
cd "$SCRIPT_DIR"

echo "=========================================="
echo "1. กำลังติดตั้งเครื่องมือ (xvfb + imagemagick)"
echo "=========================================="
sudo apt-get update
sudo apt-get install -y xvfb python3-pip python3-venv imagemagick

echo "=========================================="
echo "2. ตรวจสอบ/อัปเดต Python Virtual Environment"
echo "=========================================="
if [ ! -d "venv-310" ]; then
    echo "กำลังสร้าง venv ใหม่..."
    python3 -m venv venv-310
fi

source venv-310/bin/activate
pip install -r requirements.txt

LINE_SEND_MODE_CONFIG=""
if [ -f ".env" ]; then
    LINE_SEND_MODE_CONFIG="$(awk -F= '/^[[:space:]]*LINE_SEND_MODE[[:space:]]*=/ { gsub(/^[[:space:]]+|[[:space:]]+$/, "", $2); print tolower($2); exit }' .env | tr -d "\"'\r")"
fi

if [ "$LINE_SEND_MODE_CONFIG" = "automation" ]; then
    echo "=========================================="
    echo "3. ตรวจพบ LINE_SEND_MODE=automation"
    echo "=========================================="
    echo "โหมดนี้ใช้ Windows LINE Desktop Worker ผ่าน SSH tunnel"
    echo "กำลังปิด Chromium worker เก่าบน VPS เพื่อคืนพอร์ต 5001..."
    pkill -f "chromium_line_worker.py" || true
    pkill -f "Xvfb :99" || true
    echo "ข้ามการเปิด Chromium worker บน VPS แล้ว"

    mkdir -p "$SCRIPT_DIR/logs"
    touch "$SCRIPT_DIR/logs/api.log"

    if ss -ltn 2>/dev/null | awk '{print $4}' | grep -Eq '(^|:)5000$'; then
        echo "LINE Personal API ทำงานอยู่แล้วบนพอร์ต 5000"
    else
        echo "กำลังเริ่ม LINE Personal API บนพอร์ต 5000..."
        nohup python api.py > "$SCRIPT_DIR/logs/api.log" 2>&1 &
        API_PID=$!
        echo "LINE Personal API PID: $API_PID"
    fi

    sleep 2
    echo "=========================================="
    echo "API log:"
    tail -n 20 "$SCRIPT_DIR/logs/api.log" || true
    echo "=========================================="
    echo "ให้เปิด Windows worker และ start_worker_tunnel.ps1 ค้างไว้"
    exit 0
fi

echo "=========================================="
echo "3. ติดตั้ง Playwright Chromium"
echo "=========================================="
export PLAYWRIGHT_BROWSERS_PATH="$SCRIPT_DIR/pw-browsers"
python -m playwright install chromium
python -m playwright install-deps chromium

# ลบ extension เก่าทิ้งเพื่อให้ setup_extension.py โหลดใหม่และ patch manifest.json
rm -rf line_extension
# ดาวน์โหลด LINE Extension (ถ้ายังไม่มี)
python setup_extension.py

echo "=========================================="
echo "4. กำลังเริ่มการทำงานของ Worker (Background/Non-Root)"
echo "=========================================="
# กวาดล้างโพรเซสเก่า
pkill -f "chromium_line_worker.py" || true
pkill -f "Xvfb :99" || true
sleep 2

# ตรวจสอบว่ามี user 'linebot' หรือไม่ ถ้าไม่มีให้สร้าง (รัน worker แบบไม่ใช้ root เพื่อให้ Chrome Sandbox ทำงานได้)
if ! id -u linebot > /dev/null 2>&1; then
    echo "Creating non-root user 'linebot' for Chrome Sandbox..."
    useradd -m -s /bin/bash linebot
fi

# ตั้งค่าสิทธิ์ให้ linebot เข้าถึงโฟลเดอร์รันได้
mkdir -p "$SCRIPT_DIR/chromium_data" "$SCRIPT_DIR/automation" "$SCRIPT_DIR/chrline" "$SCRIPT_DIR/logs"
touch "$SCRIPT_DIR/worker.log"
chown -R linebot:linebot \
    "$SCRIPT_DIR/chromium_data" \
    "$SCRIPT_DIR/automation" \
    "$SCRIPT_DIR/chrline" \
    "$SCRIPT_DIR/logs" \
    "$SCRIPT_DIR/worker.log" >/dev/null 2>&1 || true
# อนุญาตให้ทะลุโฟลเดอร์ Plesk
chmod a+x /var /var/www /var/www/vhosts /var/www/vhosts/imzshop97.com /var/www/vhosts/imzshop97.com/httpdocs

export PLAYWRIGHT_BROWSERS_PATH="$SCRIPT_DIR/pw-browsers"
chown -R linebot:linebot "$PLAYWRIGHT_BROWSERS_PATH" >/dev/null 2>&1 || true
chown -R linebot:linebot "$SCRIPT_DIR/line_extension" >/dev/null 2>&1 || true

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
su - linebot -c "cd '$SCRIPT_DIR' && export DISPLAY=:99 && export PLAYWRIGHT_BROWSERS_PATH='$PLAYWRIGHT_BROWSERS_PATH' && source venv-310/bin/activate && nohup python chromium_line_worker.py > worker.log 2>&1 &"

sleep 5
tail -n 20 worker.log
