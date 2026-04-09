"""
LINE Personal Bot API
FastAPI server for handling LINE messaging requests from PHP backend
"""
import os
import sys
import logging
from datetime import datetime
from typing import Optional, List, Dict, Any
from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import uvicorn

from config import Config
from line_client import line_client

# Setup logging
logging.basicConfig(
    level=getattr(logging, Config.LOG_LEVEL),
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(Config.LOG_FILE) if Config.LOG_FILE else logging.StreamHandler(),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="LINE Personal Bot API",
    description="API สำหรับส่งข้อความผ่าน LINE Personal Account",
    version="1.0.0"
)

# CORS Middleware - อนุญาตให้ PHP backend เข้าถึง
app.add_middleware(
    CORSMiddleware,
    allow_origins=Config.ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
)

# ============ Models ============
class SendMessageRequest(BaseModel):
    group_id: str
    message: str

class SendLottoRequest(BaseModel):
    group_id: str
    lottery_type: str  # เช่น "ฮานอย HD", "ได้หวัน VIP"
    result: str        # เช่น "916 - 48"
    time: str          # เช่น "11:30"

class SendMultipleRequest(BaseModel):
    group_ids: List[str]
    message: str

class GroupInfo(BaseModel):
    id: str
    name: Optional[str] = ""
    active: bool = True

class SettingsUpdate(BaseModel):
    groups: Optional[List[GroupInfo]] = None
    auto_send: Optional[bool] = None

# ============ Endpoints ============
@app.get("/")
def root():
    """Root endpoint - API status"""
    return {
        "service": "LINE Personal Bot API",
        "version": "1.0.0",
        "status": "running",
        "timestamp": datetime.now().isoformat(),
        "line_personal_enabled": Config.LINE_PERSONAL_ENABLED,
        "logged_in": line_client.check_login_status()
    }

@app.get("/status")
def get_status():
    """Get current status of the bot"""
    settings = Config.load_settings()
    
    return {
        "logged_in": line_client.check_login_status(),
        "line_personal_enabled": Config.LINE_PERSONAL_ENABLED,
        "active_groups": len(Config.get_active_groups()),
        "total_groups": len(settings.get("groups", [])),
        "auto_send": settings.get("auto_send", False),
        "last_login": settings.get("last_login"),
        "timestamp": datetime.now().isoformat()
    }

@app.post("/send")
def send_message(request: SendMessageRequest):
    """
    Send text message to a specific group
    ใช้โดย PHP backend
    """
    if not Config.LINE_PERSONAL_ENABLED:
        raise HTTPException(status_code=503, detail="LINE Personal Bot ไม่ได้เปิดใช้งาน")
    
    if not line_client.check_login_status():
        raise HTTPException(
            status_code=401, 
            detail="ยังไม่ได้ Login กรุณา Login ที่ /login ก่อน"
        )
    
    result = line_client.send_text_message(request.group_id, request.message)
    
    if not result['success']:
        raise HTTPException(status_code=400, detail=result.get('error', 'Unknown error'))
    
    return result

@app.post("/send/lotto")
def send_lotto(request: SendLottoRequest):
    """
    Send lottery result in formatted style
    ใช้โดย PHP backend - format เหมือนใน screenshot
    """
    if not Config.LINE_PERSONAL_ENABLED:
        raise HTTPException(status_code=503, detail="LINE Personal Bot ไม่ได้เปิดใช้งาน")
    
    if not line_client.check_login_status():
        raise HTTPException(
            status_code=401,
            detail="ยังไม่ได้ Login กรุณา Login ที่ /login ก่อน"
        )
    
    # เลือก flag ตามประเภทหวย
    flag = "🇻🇳" if any(x in request.lottery_type for x in ["ฮานอย", "หวัน", "VIP"]) else "🇱🇦" if "ลาว" in request.lottery_type else "🇹🇭"
    
    # สร้างข้อความตามรูปแบบใน screenshot
    message = f"""{flag} {request.lottery_type} {flag}
เวลา {request.time} น.

{request.result}"""
    
    result = line_client.send_text_message(request.group_id, message)
    
    if not result['success']:
        raise HTTPException(status_code=400, detail=result.get('error', 'Unknown error'))
    
    return {
        "success": True,
        "message": "ส่งผลหวยสำเร็จ",
        "lottery_type": request.lottery_type,
        "group_id": request.group_id,
        "timestamp": datetime.now().isoformat()
    }

@app.post("/send/multiple")
def send_multiple(request: SendMultipleRequest):
    """Send message to multiple groups at once"""
    if not Config.LINE_PERSONAL_ENABLED:
        raise HTTPException(status_code=503, detail="LINE Personal Bot ไม่ได้เปิดใช้งาน")
    
    if not line_client.check_login_status():
        raise HTTPException(status_code=401, detail="ยังไม่ได้ Login")
    
    result = line_client.send_to_multiple_groups(request.group_ids, request.message)
    return result

@app.get("/groups")
def get_groups():
    """Get list of configured groups"""
    settings = Config.load_settings()
    return {
        "groups": settings.get("groups", []),
        "active_groups": Config.get_active_groups()
    }

@app.post("/groups/add")
def add_group(group_id: str, group_name: Optional[str] = ""):
    """Add a new group"""
    success = Config.add_group(group_id, group_name)
    if not success:
        raise HTTPException(status_code=400, detail="Group already exists or invalid")
    return {"success": True, "message": "เพิ่มกลุ่มสำเร็จ"}

@app.delete("/groups/remove")
def remove_group(group_id: str):
    """Remove a group"""
    success = Config.remove_group(group_id)
    if not success:
        raise HTTPException(status_code=400, detail="Failed to remove group")
    return {"success": True, "message": "ลบกลุ่มสำเร็จ"}

@app.get("/settings")
def get_settings():
    """Get current settings"""
    return Config.load_settings()

@app.post("/settings")
def update_settings(request: SettingsUpdate):
    """Update settings"""
    settings = Config.load_settings()
    
    if request.groups is not None:
        settings["groups"] = [g.dict() for g in request.groups]
    
    if request.auto_send is not None:
        settings["auto_send"] = request.auto_send
    
    success = Config.save_settings(settings)
    if not success:
        raise HTTPException(status_code=500, detail="Failed to save settings")
    
    return {"success": True, "settings": settings}

@app.post("/login/qr")
def login_qr():
    """
    Start QR code login process
    จะ return QR code URL ให้ user สแกน
    """
    # TODO: Implement actual QR login
    # สำหรับตอนนี้ return instruction ไปก่อน
    return {
        "message": "กรุณาใช้ LINE PC หรือ LINE Chrome Extension สำหรับการ Login",
        "note": "การ Login ด้วย QR Code สำหรับ Personal Account ต้องใช้ automation หรือ manual login",
        "alternative": "แนะนำให้ใช้ method อื่น เช่น ใช้ LINE PC Client บน server แล้วส่งผ่าน UI automation"
    }

@app.post("/logout")
def logout():
    """Logout and clear session"""
    success = line_client.logout()
    return {"success": success, "message": "Logout สำเร็จ" if success else "Logout ล้มเหลว"}

@app.get("/health")
def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "timestamp": datetime.now().isoformat()}

# ============ Main ============
if __name__ == "__main__":
    print(f"🚀 Starting LINE Personal Bot API on {Config.API_HOST}:{Config.API_PORT}")
    print(f"📊 Logging to: {Config.LOG_FILE}")
    print(f"🔧 LINE Personal Enabled: {Config.LINE_PERSONAL_ENABLED}")
    print(f"🔒 Logged In: {line_client.check_login_status()}")
    
    uvicorn.run(
        app,
        host=Config.API_HOST,
        port=Config.API_PORT,
        log_level=Config.LOG_LEVEL.lower()
    )
