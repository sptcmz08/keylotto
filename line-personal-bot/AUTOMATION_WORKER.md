# LINE Personal Automation Worker

โหมดนี้ใช้เมื่อ QR/token login ผ่าน unofficial API ใช้งานไม่ได้แล้ว โดยให้:

- VPS รัน `api.py` ตามปกติ
- เครื่อง Windows ที่เปิด LINE Desktop รัน `windows_line_worker.py`
- VPS ส่งงานไปที่ worker ผ่าน HTTP

## 1. ฝั่ง VPS

ตั้งค่า `.env`

```env
LINE_PERSONAL_ENABLED=true
LINE_SEND_MODE=automation
AUTOMATION_WORKER_URL=http://WINDOWS_PC_IP:5001
AUTOMATION_WORKER_TOKEN=change-me
AUTOMATION_WORKER_TIMEOUT=60
AUTOMATION_VERIFY_SSL=false
```

ฝั่ง PHP จะส่ง `group_id` และ `group_name` ไปให้ Python API อัตโนมัติแล้ว

## 2. ฝั่ง Windows

ติดตั้ง dependencies

```powershell
cd D:\key_lotto\line-personal-bot
py -3.10 -m venv venv-worker
.\venv-worker\Scripts\Activate.ps1
pip install -U pip setuptools wheel
pip install -r .\requirements-worker.txt
copy .env.worker.example .env
```

## 3. Calibrate ตำแหน่งใน LINE Desktop

เปิด LINE Desktop และล็อกอินให้เรียบร้อย

รัน:

```powershell
python .\line_automation_calibrate.py
```

นำค่าที่ได้ไปใส่ใน `.env`

```env
WORKER_SEARCH_BOX_X=...
WORKER_SEARCH_BOX_Y=...
WORKER_MESSAGE_BOX_X=...
WORKER_MESSAGE_BOX_Y=...
WORKER_ATTACH_BUTTON_X=...
WORKER_ATTACH_BUTTON_Y=...
```

ค่าทั้งหมดเป็นพิกัดแบบ relative จากมุมซ้ายบนของหน้าต่าง LINE

## 4. รัน worker

```powershell
python .\windows_line_worker.py
```

ตรวจสอบสถานะ:

```powershell
Invoke-RestMethod http://127.0.0.1:5001/status -Headers @{ "X-Worker-Token" = "change-me" }
```

ถ้า `ready=true` แปลว่า worker พร้อมรับงานแล้ว

## 5. ให้ VPS เรียก Windows ได้จริง

ถ้า VPS อยู่นอกบ้านหรือคนละเครือข่ายกับ Windows เครื่องนี้ ค่า `192.168.x.x` จะใช้จาก VPS ไม่ได้

แนะนำให้ใช้ reverse SSH tunnel จาก Windows กลับไปหา VPS:

```powershell
cd D:\key_lotto\line-personal-bot
.\start_worker_tunnel.ps1 -ServerHost YOUR_VPS_IP -ServerUser root
```

เมื่อ tunnel ติดแล้ว ให้ฝั่ง VPS ตั้งค่า `.env` แบบนี้:

```env
LINE_SEND_MODE=automation
AUTOMATION_WORKER_URL=http://127.0.0.1:5001
AUTOMATION_WORKER_TOKEN=change-me
AUTOMATION_WORKER_TIMEOUT=60
AUTOMATION_VERIFY_SSL=false
```

แนวคิดคือ:
- Windows worker ยังรันอยู่ที่ `127.0.0.1:5001` บนเครื่องคุณ
- คำสั่ง `ssh -R` จะเปิดพอร์ต `127.0.0.1:5001` บน VPS แล้วส่งต่อกลับมา Windows
- ดังนั้น Python API บน VPS จะเรียก worker ผ่าน `localhost` ได้เลย

## 6. ทดสอบจาก VPS

```bash
curl http://127.0.0.1:5000/status
```

ถ้า `send_mode=automation` และ `transport_ready=true` แปลว่า API ฝั่ง VPS มองเห็น worker แล้ว

## หมายเหตุ

- worker ต้องเปิด LINE Desktop ไว้และหน้าจอไม่ควรถูกล็อก
- การส่งรูปใช้ปุ่ม attach ของ LINE Desktop และ file dialog ของ Windows
- ถ้าหน้าตา UI ของ LINE เปลี่ยน ต้อง calibrate ใหม่
