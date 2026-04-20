#!/bin/bash
# ==========================================
# LINE Chromium Worker - VPS Deployment Script
# ==========================================
# รัน Chrome เป็น non-root user (linebot) เพื่อให้ Chrome sandbox ทำงานได้
# ซึ่ง extension LTSM sandbox ต้องการ Chrome sandbox ที่สมบูรณ์

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
cd "$SCRIPT_DIR"

BOT_USER="linebot"
# เก็บ Playwright browsers ไว้ในโปรเจกต์ ให้ linebot เข้าถึงได้
export PLAYWRIGHT_BROWSERS_PATH="$SCRIPT_DIR/.browsers"

echo "=========================================="
echo "1. กำลังติดตั้งเครื่องมือ (xvfb + imagemagick)"
echo "=========================================="
sudo apt-get update
sudo apt-get install -y xvfb python3-pip python3-venv imagemagick

echo "=========================================="
echo "2. ตั้งค่า user: $BOT_USER"
echo "=========================================="
if ! id "$BOT_USER" &>/dev/null; then
    useradd -r -m -s /bin/bash "$BOT_USER"
    echo "สร้าง user $BOT_USER เรียบร้อย"
else
    echo "user $BOT_USER มีอยู่แล้ว"
fi

echo "=========================================="
echo "3. ตรวจสอบ/อัปเดต Python Virtual Environment"
echo "=========================================="
if [ ! -d "venv-310" ]; then
    echo "กำลังสร้าง venv ใหม่..."
    python3 -m venv venv-310
fi

source venv-310/bin/activate
pip install -r requirements.txt

echo "=========================================="
echo "4. ติดตั้ง Playwright Chromium (shared path)"
echo "=========================================="
# ติดตั้ง browsers ไว้ที่ $PLAYWRIGHT_BROWSERS_PATH (ในโปรเจกต์)
python -m playwright install chromium
python -m playwright install-deps chromium

# ดาวน์โหลด LINE Extension (ถ้ายังไม่มี)
python setup_extension.py

# ── ให้ linebot เป็นเจ้าของทุกอย่างในโปรเจกต์ ──
chown -R "$BOT_USER":"$BOT_USER" "$SCRIPT_DIR"

echo "=========================================="
echo "5. กำลังเริ่มการทำงานของ Worker (Background)"
echo "=========================================="
# กวาดล้างโพรเซสเก่า
pkill -f "chromium_line_worker.py" || true
pkill -f "Xvfb :99" || true
sleep 2

# เริ่ม xvfb display :99 (รันเป็น root, -ac = allow all clients)
Xvfb :99 -screen 0 1280x720x24 -ac &
XVFB_PID=$!
sleep 1
echo "Xvfb started on :99 (PID: $XVFB_PID)"

# ── รัน Worker เป็น linebot ──
# DISPLAY=:99         → ให้ Chrome render บน xvfb
# PLAYWRIGHT_BROWSERS_PATH → ให้ Playwright หา browser ที่ติดตั้งไว้ได้
VENV_PYTHON="$SCRIPT_DIR/venv-310/bin/python"
sudo -u "$BOT_USER" \
    DISPLAY=:99 \
    PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_BROWSERS_PATH" \
    HOME="/home/$BOT_USER" \
    nohup "$VENV_PYTHON" "$SCRIPT_DIR/chromium_line_worker.py" \
    > "$SCRIPT_DIR/worker.log" 2>&1 &

echo "✨ ติดตั้งและเริ่มการทำงานเรียบร้อยแล้ว!"
echo "Xvfb PID: $XVFB_PID (display :99)"
echo "Worker: runs as '$BOT_USER' (non-root → Chrome sandbox OK)"
echo "Browsers: $PLAYWRIGHT_BROWSERS_PATH"
echo "=========================================="
echo "Log (กำลังรอ worker เริ่ม...):"
sleep 10
tail -n 20 worker.log

