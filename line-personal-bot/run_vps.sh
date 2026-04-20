#!/bin/bash
# ==========================================
# LINE Chromium Worker - VPS Deployment Script
# ==========================================
# สคริปต์นี้ใช้สำหรับติดตั้งและเปิดบอท LINE บนเซิร์ฟเวอร์ Ubuntu/Debian

set -e

# ไปที่โฟลเดอร์ของโปรเจกต์
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)"
cd "$SCRIPT_DIR"

echo "=========================================="
echo "1. กำลังติดตั้งเครื่องมือสร้างหน้าจอจำลอง (xvfb)"
echo "=========================================="
sudo apt-get update
sudo apt-get install -y xvfb python3-pip python3-venv

echo "=========================================="
echo "2. ตรวจสอบ/อัปเดต Python Virtual Environment"
echo "=========================================="
if [ ! -d "venv-310" ]; then
    echo "กำลังสร้าง venv ใหม่..."
    python3 -m venv venv-310
fi

# Activate venv
source venv-310/bin/activate
pip install -r requirements.txt

echo "=========================================="
echo "3. ติดตั้ง Playwright Chromium"
echo "=========================================="
# ใช้ python -m playwright เพื่อรันผ่าน venv ที่ activate ไว้แล้ว
python -m playwright install chromium
python -m playwright install-deps chromium

# ดาวน์โหลด LINE Extension (ถ้ายังไม่มี)
python setup_extension.py

echo "=========================================="
echo "4. กำลังเริ่มการทำงานของ Worker (Background)"
echo "=========================================="
# กวาดล้างโพรเซสเก่า
pkill -f "chromium_line_worker.py" || true

# รัน Worker ภายใต้จอจำลอง xvfb แบบ background 24 ชั่วโมง
VENV_PYTHON="$SCRIPT_DIR/venv-310/bin/python"
nohup xvfb-run --auto-servernum --server-args="-screen 0 1280x720x24" \
    "$VENV_PYTHON" chromium_line_worker.py > worker.log 2>&1 &

echo "✨ ติดตั้งและเริ่มการทำงานเรียบร้อยแล้ว!"
echo "บอทรันอยู่ที่พอร์ต 5001"
echo "เข้าหน้า LINE Groups บนเว็บแล้วกดปุ่ม 'ดึงภาพหน้าจอ' เพื่อสแกน QR Code ล็อกอินครั้งแรกได้เลยครับ"
echo "=========================================="
echo "Log ล่าสุด (tail worker.log):"
sleep 3
tail -n 15 worker.log

