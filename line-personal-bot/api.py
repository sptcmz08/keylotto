"""
LINE Personal Bot API.

FastAPI server for handling LINE Personal messaging requests from the PHP backend.
"""

import logging
from datetime import datetime
from typing import Any, Dict, List, Optional

import uvicorn
from fastapi import FastAPI, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from config import Config
from line_client_runtime import line_client

logging.basicConfig(
    level=getattr(logging, Config.LOG_LEVEL),
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=[
        logging.FileHandler(Config.LOG_FILE, encoding="utf-8") if Config.LOG_FILE else logging.StreamHandler(),
        logging.StreamHandler(),
    ],
)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="LINE Personal Bot API",
    description="API for sending messages through a LINE Personal account",
    version="1.1.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=Config.ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
)


class SendMessageRequest(BaseModel):
    group_id: str
    message: Optional[str] = None
    messages: Optional[List[Dict[str, Any]]] = None


class SendLottoRequest(BaseModel):
    group_id: str
    lottery_type: str
    result: str
    time: str


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


class SessionLoginRequest(BaseModel):
    auth_token: str
    save: bool = True


def ensure_personal_ready() -> None:
    if not Config.LINE_PERSONAL_ENABLED:
        raise HTTPException(status_code=503, detail="LINE Personal Bot is disabled")
    if not line_client.check_login_status():
        raise HTTPException(status_code=401, detail="LINE Personal session is not logged in")


def response_status() -> Dict[str, Any]:
    settings = Config.load_settings()
    client_status = line_client.get_status()
    return {
        "logged_in": client_status["logged_in"],
        "line_personal_enabled": Config.LINE_PERSONAL_ENABLED,
        "send_mode": client_status["send_mode"],
        "active_groups": len(Config.get_active_groups()),
        "total_groups": len(settings.get("groups", [])),
        "auto_send": settings.get("auto_send", False),
        "last_login": client_status.get("last_login_at") or settings.get("last_login"),
        "session_file": client_status["session_file"],
        "transport_ready": client_status["transport_ready"],
        "last_error": client_status.get("last_error"),
        "timestamp": datetime.now().isoformat(),
    }


@app.get("/")
def root() -> Dict[str, Any]:
    return {
        "service": "LINE Personal Bot API",
        "version": "1.1.0",
        "status": "running",
        "timestamp": datetime.now().isoformat(),
        "line_personal_enabled": Config.LINE_PERSONAL_ENABLED,
        "logged_in": line_client.check_login_status(),
        "send_mode": Config.LINE_SEND_MODE,
    }


@app.get("/status")
def get_status() -> Dict[str, Any]:
    return response_status()


@app.post("/send")
def send_message(request: SendMessageRequest) -> Dict[str, Any]:
    ensure_personal_ready()

    payload_messages = request.messages
    if payload_messages is None:
        text = (request.message or "").strip()
        if text == "":
            raise HTTPException(status_code=400, detail="message or messages is required")
        payload_messages = [{"type": "text", "text": text}]

    result = line_client.send_messages(request.group_id, payload_messages)
    if not result["success"]:
        raise HTTPException(status_code=400, detail=result.get("error", "Unknown error"))
    return result


@app.post("/send/lotto")
def send_lotto(request: SendLottoRequest) -> Dict[str, Any]:
    ensure_personal_ready()

    flag = "VN" if any(x in request.lottery_type for x in ["ฮานอย", "ฮานอย", "VIP"]) else "LA" if "ลาว" in request.lottery_type else "TH"
    message = f"{flag} {request.lottery_type} {flag}\nเวลา {request.time} น.\n\n{request.result}"

    result = line_client.send_messages(request.group_id, [{"type": "text", "text": message}])
    if not result["success"]:
        raise HTTPException(status_code=400, detail=result.get("error", "Unknown error"))

    return {
        "success": True,
        "message": "Lottery result sent successfully",
        "lottery_type": request.lottery_type,
        "group_id": request.group_id,
        "timestamp": datetime.now().isoformat(),
    }


@app.post("/send/multiple")
def send_multiple(request: SendMultipleRequest) -> Dict[str, Any]:
    ensure_personal_ready()
    return line_client.send_to_multiple_groups(request.group_ids, request.message)


@app.get("/groups")
def get_groups() -> Dict[str, Any]:
    settings = Config.load_settings()
    return {
        "groups": settings.get("groups", []),
        "active_groups": Config.get_active_groups(),
    }


@app.get("/groups/joined")
def get_joined_groups() -> Dict[str, Any]:
    ensure_personal_ready()
    groups = line_client.get_joined_groups()
    return {"success": True, "groups": groups, "count": len(groups)}


@app.get("/groups/summary")
def get_group_summary(group_id: str = Query(...)) -> Dict[str, Any]:
    ensure_personal_ready()
    summary = line_client.get_group_summary(group_id)
    if not summary:
        raise HTTPException(status_code=404, detail="Group summary not available")
    return {"success": True, "group": summary}


@app.post("/groups/add")
def add_group(group_id: str, group_name: Optional[str] = "") -> Dict[str, Any]:
    success = Config.add_group(group_id, group_name)
    if not success:
        raise HTTPException(status_code=400, detail="Group already exists or invalid")
    return {"success": True, "message": "Group added successfully"}


@app.delete("/groups/remove")
def remove_group(group_id: str) -> Dict[str, Any]:
    success = Config.remove_group(group_id)
    if not success:
        raise HTTPException(status_code=400, detail="Failed to remove group")
    return {"success": True, "message": "Group removed successfully"}


@app.get("/settings")
def get_settings() -> Dict[str, Any]:
    return Config.load_settings()


@app.post("/settings")
def update_settings(request: SettingsUpdate) -> Dict[str, Any]:
    settings = Config.load_settings()

    if request.groups is not None:
        settings["groups"] = [group.model_dump() for group in request.groups]

    if request.auto_send is not None:
        settings["auto_send"] = request.auto_send

    success = Config.save_settings(settings)
    if not success:
        raise HTTPException(status_code=500, detail="Failed to save settings")

    return {"success": True, "settings": settings}


@app.post("/login/session")
def login_session(request: SessionLoginRequest) -> Dict[str, Any]:
    result = line_client.set_session(request.auth_token, save=request.save)
    if not result["success"]:
        raise HTTPException(status_code=400, detail=result.get("error", "Failed to save session"))

    settings = Config.load_settings()
    settings["last_login"] = result["last_login_at"]
    Config.save_settings(settings)
    return result


@app.post("/login/qr")
def login_qr() -> Dict[str, Any]:
    return {
        "success": False,
        "message": "Run `python chrline_qr_login.py` on the host to log in with QR and save the auth token. `python linepy_qr_login.py` still works and chooses CHRLINE unless LINE_SEND_MODE=linepy.",
        "send_mode": Config.LINE_SEND_MODE,
        "session_file": str(Config.SESSION_FILE),
    }


@app.post("/logout")
def logout() -> Dict[str, Any]:
    success = line_client.logout()
    return {"success": success, "message": "Logout successful" if success else "Logout failed"}


@app.get("/health")
def health_check() -> Dict[str, Any]:
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "line_personal_enabled": Config.LINE_PERSONAL_ENABLED,
        "logged_in": line_client.check_login_status(),
        "send_mode": Config.LINE_SEND_MODE,
        "transport_ready": line_client.get_status()["transport_ready"],
    }


if __name__ == "__main__":
    print(f"Starting LINE Personal Bot API on {Config.API_HOST}:{Config.API_PORT}")
    print(f"Logging to: {Config.LOG_FILE}")
    print(f"LINE Personal Enabled: {Config.LINE_PERSONAL_ENABLED}")
    print(f"Logged In: {line_client.check_login_status()}")

    uvicorn.run(
        app,
        host=Config.API_HOST,
        port=Config.API_PORT,
        log_level=Config.LOG_LEVEL.lower(),
    )
