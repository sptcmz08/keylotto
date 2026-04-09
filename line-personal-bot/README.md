# LINE Personal Bot API

API สำหรับส่งข้อความผ่าน LINE Personal Account (ไม่ใช่ Official Account)
สำหรับใช้งานร่วมกับระบบ key_lotto (imzshop97.com)

## ความต้องการของระบบ

- Python 3.8+
- Ubuntu 20.04+ หรือ Debian 10+
- Port 5000 ว่าง

## การติดตั้ง

```bash
# 1. เข้าไปยังโฟลเดอร์โปรเจค
cd /var/www/vhosts/imzshop97.com/line-personal-bot

# 2. สร้าง virtual environment
python3 -m venv venv
source venv/bin/activate

# 3. ติดตั้ง dependencies
pip install -r requirements.txt

# 4. สร้างไฟล์ config
cp .env.example .env
# แก้ไข .env ตามต้องการ

# 5. สร้าง log directory
mkdir -p /var/log/line-bot
touch /var/log/line-bot/app.log
chown -R www-data:www-data /var/log/line-bot
```

## การใช้งาน

### รันโดยตรง (สำหรับทดสอบ)
```bash
source venv/bin/activate
python api.py
```

### รันด้วย Supervisor (แนะนำสำหรับ production)
```bash
# ติดตั้ง supervisor
sudo apt install supervisor

# สร้าง config file
sudo nano /etc/supervisor/conf.d/line-personal-bot.conf
```

ใส่เนื้อหาต่อไปนี้:
```ini
[program:line-personal-bot]
command=/var/www/vhosts/imzshop97.com/line-personal-bot/venv/bin/python /var/www/vhosts/imzshop97.com/line-personal-bot/api.py
directory=/var/www/vhosts/imzshop97.com/line-personal-bot
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/line-bot/supervisor.err.log
stdout_logfile=/var/log/line-bot/supervisor.out.log
environment=PATH="/var/www/vhosts/imzshop97.com/line-personal-bot/venv/bin"
EOF

# อัพเดท supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start line-personal-bot

# ตรวจสอบสถานะ
sudo supervisorctl status line-personal-bot
```

## API Endpoints

| Endpoint | Method | คำอธิบาย |
|----------|--------|---------|
| `/` | GET | สถานะ API |
| `/status` | GET | สถานะ login และ groups |
| `/send` | POST | ส่งข้อความธรรมดา |
| `/send/lotto` | POST | ส่งผลหวย (มี format พิเศษ) |
| `/send/multiple` | POST | ส่งหลายกลุ่มพร้อมกัน |
| `/groups` | GET | ดูรายการกลุ่ม |
| `/groups/add` | POST | เพิ่มกลุ่ม |
| `/groups/remove` | DELETE | ลบกลุ่ม |
| `/settings` | GET/POST | ดู/แก้ไขการตั้งค่า |
| `/login/qr` | POST | เริ่ม login ด้วย QR |
| `/logout` | POST | ออกจากระบบ |
| `/health` | GET | Health check |

## การเชื่อมต่อกับ PHP

แก้ไขไฟล์ `key_lotto/line/common.php` เพื่อเพิ่มการส่งผ่าน Python API:

```php
// เพิ่มฟังก์ชันนี้ใน common.php
function linePushViaPython($groupId, $message) {
    $apiUrl = 'http://localhost:5000/send';
    
    $data = json_encode([
        'group_id' => $groupId,
        'message' => $message
    ]);
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

## การตั้งค่า

แก้ไขไฟล์ `.env`:

```env
# API
API_HOST=0.0.0.0
API_PORT=5000

# LINE Personal
LINE_PERSONAL_ENABLED=true

# CORS - ใส่ domain ของคุณ
ALLOWED_ORIGINS=https://imzshop97.com,http://imzshop97.com
```

## คำเตือน

⚠️ **LINE Personal Bot ผิด Terms of Service ของ LINE**
- บัญชีอาจโดนแบนถาวร
- ใช้บัญชีสำรอง/บัญชีที่ไม่สำคัญ
- แนะนำให้ใช้ Proxy/VPN หากส่งข้อความบ่อย

## การแก้ปัญหา

### 1. ตรวจสอบว่า API รันอยู่
```bash
curl http://localhost:5000/health
```

### 2. ดู logs
```bash
tail -f /var/log/line-bot/app.log
tail -f /var/log/line-bot/supervisor.err.log
```

### 3. รีสตาร์ท service
```bash
sudo supervisorctl restart line-personal-bot
```

## License

Private use only
