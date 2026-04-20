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
# ติดตั้ง xvfb สำหรับหลอก Chrome Extension ให้คิดว่ามีหน้าจออยู่ (Bypass Headless restriction)
sudo apt-get update
sudo apt-get install -y xvfb python3-pip python3-venv

echo "=========================================="
echo "2. ตรวจสอบ/อัปเดต Python Virtual Environment"
echo "=========================================="
if [ ! -d "venv-310" ]; then
    echo "กำลังสร้าง venv ใหม่..."
    python3 -m venv venv-310
fi

# Activate venv และติดตั้ง libraries ที่จำเป็น
source venv-310/bin/activate
pip install -r requirements.txt
playwright install chromium
playwright install-deps

# หุ้มเพื่อดาวน์โหลด extension ป้องกันการขาดหาย
python setup_extension.py

echo "=========================================="
echo "3. กำลังเริ่มการทำงานของ Worker (Background)"
echo "=========================================="
# กวาดล้างโพรเซสเก่าที่อาจค้างอยู่
pkill -f "chromium_line_worker.py" || true

# รัน Worker ภายใต้หน้าจอจำลอง (Xvfb) โดยให้รับค่า :99 และขนาดจอ 1280x720
# รันแบบเบื้องหลัง (Background process) เพื่อให้ทำงาน 24 ชั่วโมง
nohup xvfb-run --auto-servernum --server-args="-screen 0 1280x720x24" python chromium_line_worker.py > worker.log 2>&1 &

echo "✨ ติดตั้งและเริ่มการทำงานเรียบร้อยแล้ว!"
echo "บอทรันอยู่ที่พอร์ต 5001"
echo "สามารถเข้าไปที่หน้า 'LINE Groups' บนเว็บไซต์ และกดปุ่ม 'ดึงภาพหน้าจอบอท' เพื่อตั้งค่าล็อกอินครั้งแรกได้เลยครับ"
echo "=========================================="
echo "Log ล่าสุด (cat worker.log):"
sleep 2
tail -n 10 worker.log
