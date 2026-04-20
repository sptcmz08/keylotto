#!/bin/bash
# ==========================================
# LINE Chromium Worker - VPS Deployment Script
# ==========================================
# สคริปต์นี้ใช้สำหรับติดตั้งและเปิดบอท LINE บนเซิร์ฟเวอร์ Ubuntu/Debian
# รัน Chrome เป็น non-root user (linebot) เพื่อให้ Chrome sandbox ทำงานได้ถูกต้อง
# — ซึ่ง extension sandbox (LTSM) ต้องการ Chrome sandbox ที่สมบูรณ์

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
cd "$SCRIPT_DIR"

BOT_USER="linebot"

echo "=========================================="
echo "1. กำลังติดตั้งเครื่องมือสร้างหน้าจอจำลอง (xvfb + imagemagick)"
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

# ให้ linebot เข้าถึงโฟลเดอร์โปรเจกต์
chown -R "$BOT_USER":"$BOT_USER" "$SCRIPT_DIR"

echo "=========================================="
echo "3. ตรวจสอบ/อัปเดต Python Virtual Environment"
echo "=========================================="
if [ ! -d "venv-310" ]; then
    echo "กำลังสร้าง venv ใหม่..."
    python3 -m venv venv-310
    chown -R "$BOT_USER":"$BOT_USER" venv-310
fi

# Activate venv
source venv-310/bin/activate
pip install -r requirements.txt

echo "=========================================="
echo "4. ติดตั้ง Playwright Chromium"
echo "=========================================="
python -m playwright install chromium
python -m playwright install-deps chromium

# ดาวน์โหลด LINE Extension (ถ้ายังไม่มี)
python setup_extension.py

# ── ให้ linebot เป็นเจ้าของ Playwright browser cache ──
PLAYWRIGHT_BROWSERS="$(python -c 'from playwright._impl._driver import compute_driver_executable; import os; print(os.path.dirname(os.path.dirname(compute_driver_executable())))')/playwright/driver/package/.local-browsers" 2>/dev/null || true
# Fallback: set ownership on common locations
chown -R "$BOT_USER":"$BOT_USER" "$SCRIPT_DIR" 2>/dev/null || true
chown -R "$BOT_USER":"$BOT_USER" /root/.cache/ms-playwright 2>/dev/null || true

echo "=========================================="
echo "5. กำลังเริ่มการทำงานของ Worker (Background)"
echo "=========================================="
# กวาดล้างโพรเซสเก่า
pkill -f "chromium_line_worker.py" || true
pkill -f "Xvfb :99" || true
sleep 2

# ─── เริ่ม xvfb display :99 (รันเป็น root, -ac = allow all clients) ───
Xvfb :99 -screen 0 1280x720x24 -ac &
XVFB_PID=$!
sleep 1
echo "Xvfb started on :99 (PID: $XVFB_PID)"

# ─── รัน Worker เป็น linebot (non-root → Chrome sandbox ทำงาน) ───
VENV_PYTHON="$SCRIPT_DIR/venv-310/bin/python"
sudo -u "$BOT_USER" bash -c "DISPLAY=:99 nohup $VENV_PYTHON $SCRIPT_DIR/chromium_line_worker.py > $SCRIPT_DIR/worker.log 2>&1 &"

echo "✨ ติดตั้งและเริ่มการทำงานเรียบร้อยแล้ว!"
echo "Xvfb PID: $XVFB_PID (display :99)"
echo "Worker: runs as '$BOT_USER' (non-root → Chrome sandbox OK)"
echo "เข้าหน้า LINE Groups บนเว็บแล้วกดปุ่ม 'ดึงภาพหน้าจอ' เพื่อสแกน QR Code ล็อกอินครั้งแรกได้เลยครับ"
echo "=========================================="
echo "Log ล่าสุด (tail worker.log):"
sleep 8
tail -n 20 worker.log

