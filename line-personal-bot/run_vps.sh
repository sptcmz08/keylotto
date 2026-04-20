#!/bin/bash
# ==========================================
# LINE Chromium Worker - VPS Deployment Script
# ==========================================
# รัน Chrome เป็น root + --no-sandbox + xvfb display capture

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

echo "=========================================="
echo "3. ติดตั้ง Playwright Chromium"
echo "=========================================="
python -m playwright install chromium
python -m playwright install-deps chromium

# ดาวน์โหลด LINE Extension (ถ้ายังไม่มี)
python setup_extension.py

echo "=========================================="
echo "3.1 Patch: LTSM bypass (E2EE ไม่ต้องใช้สำหรับ login)"
echo "=========================================="
# LTSM ใช้ SharedArrayBuffer + WebAssembly สำหรับ E2EE (Letter Sealing)
# บน VPS ไม่สามารถใช้ SharedArrayBuffer ได้ → extension crash ทั้ง app
# Patch: เปลี่ยน throw error เป็น console.warn เพื่อให้ app ทำงานต่อได้
MAIN_JS="$SCRIPT_DIR/line_extension/static/js/main.js"
if [ -f "$MAIN_JS" ]; then
    if grep -q 'throw new Hp(Id.LTSM_NOT_AVAILABLE)' "$MAIN_JS"; then
        sed -i 's/throw new Hp(Id.LTSM_NOT_AVAILABLE)/console.warn("LTSM bypassed on VPS")/g' "$MAIN_JS"
        echo "✅ LTSM patched — app จะไม่ crash แล้ว"
    else
        echo "ℹ️  LTSM patch already applied or pattern changed"
    fi
else
    echo "⚠️  main.js not found"
fi

echo "=========================================="
echo "4. กำลังเริ่มการทำงานของ Worker (Background)"
echo "=========================================="
# กวาดล้างโพรเซสเก่า
pkill -f "chromium_line_worker.py" || true
pkill -f "Xvfb :99" || true
sleep 2

# เริ่ม xvfb display :99
Xvfb :99 -screen 0 1280x720x24 -ac &
XVFB_PID=$!
sleep 1
echo "Xvfb started on :99 (PID: $XVFB_PID)"

# รัน Worker (root + --no-sandbox ใน Chrome args)
VENV_PYTHON="$SCRIPT_DIR/venv-310/bin/python"
DISPLAY=:99 nohup "$VENV_PYTHON" "$SCRIPT_DIR/chromium_line_worker.py" \
    > "$SCRIPT_DIR/worker.log" 2>&1 &
WORKER_PID=$!

echo "✨ ติดตั้งและเริ่มการทำงานเรียบร้อยแล้ว!"
echo "Xvfb PID: $XVFB_PID (display :99)"
echo "Worker PID: $WORKER_PID (port 5001)"
echo "=========================================="
echo "Log (กำลังรอ worker เริ่ม...):"
sleep 10
tail -n 20 worker.log

