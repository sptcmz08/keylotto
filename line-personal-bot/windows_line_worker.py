"""Windows automation worker for sending LINE Personal messages via the desktop app."""

from __future__ import annotations

import logging
import platform
import tempfile
import time
from pathlib import Path
from typing import Any, Dict, List, Optional
from urllib.parse import urlparse

import pyautogui
import pyperclip
import requests
import uvicorn
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel

from config import Config

logging.basicConfig(
    level=getattr(logging, Config.LOG_LEVEL),
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=[logging.StreamHandler()],
)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="LINE Desktop Automation Worker",
    description="Windows worker that controls the LINE desktop client",
    version="1.0.0",
)


class WorkerSendRequest(BaseModel):
    group_id: str
    group_name: str
    messages: List[Dict[str, Any]]


class LineDesktopAutomator:
    def __init__(self) -> None:
        pyautogui.PAUSE = 0.15
        self.search_box = (Config.WORKER_SEARCH_BOX_X, Config.WORKER_SEARCH_BOX_Y)
        self.message_box = (Config.WORKER_MESSAGE_BOX_X, Config.WORKER_MESSAGE_BOX_Y)
        self.attach_button = (Config.WORKER_ATTACH_BUTTON_X, Config.WORKER_ATTACH_BUTTON_Y)

    def status(self) -> Dict[str, Any]:
        if platform.system() != "Windows":
            return {"ready": False, "error": "This worker only runs on Windows"}

        window = self._find_line_window()
        return {
            "ready": bool(window and self._has_text_calibration()),
            "platform": platform.platform(),
            "line_window_found": bool(window),
            "line_window_title": window.window_text() if window else "",
            "line_window_regex": Config.WORKER_LINE_WINDOW_TITLE_REGEX,
            "search_box_configured": self._is_point_configured(self.search_box),
            "message_box_configured": self._is_point_configured(self.message_box),
            "attach_button_configured": self._is_point_configured(self.attach_button),
            "requires_group_name": True,
        }

    def send(self, group_name: str, messages: List[Dict[str, Any]]) -> Dict[str, Any]:
        if platform.system() != "Windows":
            raise RuntimeError("This worker only runs on Windows")
        if not group_name.strip():
            raise RuntimeError("group_name is required for desktop automation")
        if not self._has_text_calibration():
            raise RuntimeError("Search box and message box coordinates are not configured")

        rect = self._focus_line_window()
        self._open_chat(rect, group_name)

        details: List[Dict[str, Any]] = []
        for message in messages:
            message_type = str(message.get("type", "text")).strip().lower()
            if message_type == "text":
                text = str(message.get("text", "")).strip()
                if not text:
                    raise RuntimeError("Text message is empty")
                self._send_text(rect, text)
                details.append({"type": "text", "success": True})
                continue

            if message_type == "image":
                image_url = str(message.get("originalContentUrl") or message.get("previewImageUrl") or "").strip()
                if not image_url:
                    raise RuntimeError("Image url is required")
                self._send_image(rect, image_url)
                details.append({"type": "image", "success": True, "image_url": image_url})
                continue

            raise RuntimeError(f"Unsupported message type: {message_type}")

        return {
            "success": True,
            "mode": "automation",
            "details": details,
            "timestamp": time.time(),
        }

    def _find_line_window(self):
        try:
            from pywinauto import Desktop
        except ImportError as exc:
            raise RuntimeError("pywinauto is not installed. Install requirements-worker.txt") from exc

        windows = Desktop(backend="uia").windows(title_re=Config.WORKER_LINE_WINDOW_TITLE_REGEX, visible_only=True)
        if not windows:
            return None
        return windows[0]

    def _focus_line_window(self):
        window = self._find_line_window()
        if window is None:
            raise RuntimeError(f"Could not find a LINE window matching {Config.WORKER_LINE_WINDOW_TITLE_REGEX!r}")

        try:
            if window.is_minimized():
                window.restore()
        except Exception:
            pass

        try:
            window.set_focus()
        except Exception:
            try:
                window.wrapper_object().set_focus()
            except Exception as exc:
                raise RuntimeError(f"Could not focus LINE window: {exc}") from exc

        time.sleep(Config.WORKER_AFTER_FOCUS_DELAY)
        return window.rectangle()

    def _open_chat(self, rect, group_name: str) -> None:
        self._click_relative(rect, self.search_box, "search box")
        self._clear_field()
        self._paste_text(group_name)
        time.sleep(Config.WORKER_AFTER_SEARCH_DELAY)
        pyautogui.press("enter")
        time.sleep(Config.WORKER_AFTER_OPEN_CHAT_DELAY)

    def _send_text(self, rect, text: str) -> None:
        self._click_relative(rect, self.message_box, "message box")
        self._clear_field()
        self._paste_text(text)
        time.sleep(Config.WORKER_AFTER_TEXT_DELAY)
        pyautogui.press("enter")
        time.sleep(Config.WORKER_AFTER_TEXT_DELAY)

    def _send_image(self, rect, image_url: str) -> None:
        if not self._is_point_configured(self.attach_button):
            raise RuntimeError("Attach button coordinates are not configured")

        temp_path = self._download_temp_file(image_url)
        try:
            self._click_relative(rect, self.attach_button, "attach button")
            time.sleep(Config.WORKER_AFTER_ATTACH_DELAY)
            self._select_file_in_dialog(temp_path)
            time.sleep(Config.WORKER_AFTER_IMAGE_SEND_DELAY)
            pyautogui.press("enter")
            time.sleep(Config.WORKER_AFTER_IMAGE_SEND_DELAY)
        finally:
            try:
                temp_path.unlink(missing_ok=True)
            except Exception:
                pass

    def _select_file_in_dialog(self, temp_path: Path) -> None:
        try:
            from pywinauto import Desktop
        except ImportError as exc:
            raise RuntimeError("pywinauto is not installed. Install requirements-worker.txt") from exc

        deadline = time.time() + 10
        last_error: Optional[str] = None

        while time.time() < deadline:
            try:
                dialog = Desktop(backend="uia").window(class_name="#32770")
                dialog.wait("visible ready", timeout=1)
                dialog.set_focus()

                edits = dialog.descendants(control_type="Edit")
                if edits:
                    edit = edits[-1]
                    edit.set_edit_text(str(temp_path))
                    time.sleep(0.2)
                    pyautogui.press("enter")
                    return
            except Exception as exc:
                last_error = str(exc)
            time.sleep(0.4)

        raise RuntimeError(f"Could not control the file dialog automatically: {last_error or 'dialog not found'}")

    def _download_temp_file(self, image_url: str) -> Path:
        response = requests.get(image_url, timeout=30)
        response.raise_for_status()

        suffix = Path(urlparse(image_url).path).suffix or ".jpg"
        if len(suffix) > 10:
            suffix = ".jpg"

        temp_file = tempfile.NamedTemporaryFile(
            prefix="line-worker-",
            suffix=suffix,
            dir=Config.AUTOMATION_TEMP_DIR,
            delete=False,
        )
        path = Path(temp_file.name)
        try:
            temp_file.write(response.content)
        finally:
            temp_file.close()
        return path

    def _click_relative(self, rect, point: tuple[int, int], label: str) -> None:
        if not self._is_point_configured(point):
            raise RuntimeError(f"{label.capitalize()} coordinates are not configured")

        x = rect.left + point[0]
        y = rect.top + point[1]
        pyautogui.click(x=x, y=y)

    @staticmethod
    def _clear_field() -> None:
        pyautogui.hotkey("ctrl", "a")
        pyautogui.press("backspace")

    @staticmethod
    def _paste_text(text: str) -> None:
        pyperclip.copy(text)
        pyautogui.hotkey("ctrl", "v")

    @staticmethod
    def _is_point_configured(point: tuple[int, int]) -> bool:
        return point[0] > 0 and point[1] > 0

    def _has_text_calibration(self) -> bool:
        return self._is_point_configured(self.search_box) and self._is_point_configured(self.message_box)


worker = LineDesktopAutomator()


def _require_token(x_worker_token: Optional[str]) -> None:
    expected = Config.WORKER_API_TOKEN.strip()
    if expected and x_worker_token != expected:
        raise HTTPException(status_code=401, detail="Invalid worker token")


@app.get("/")
def root(x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    return {"service": "LINE Desktop Automation Worker", **worker.status()}


@app.get("/status")
def status(x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    return worker.status()


@app.post("/send")
def send(request: WorkerSendRequest, x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    try:
        return worker.send(request.group_name, request.messages)
    except Exception as exc:
        logger.exception("Automation send failed")
        raise HTTPException(status_code=400, detail={"error": str(exc)}) from exc


if __name__ == "__main__":
    print(f"Starting LINE Desktop Automation Worker on {Config.WORKER_HOST}:{Config.WORKER_PORT}")
    print(f"Window regex: {Config.WORKER_LINE_WINDOW_TITLE_REGEX}")
    print(f"Search box: {worker.search_box}")
    print(f"Message box: {worker.message_box}")
    print(f"Attach button: {worker.attach_button}")

    uvicorn.run(app, host=Config.WORKER_HOST, port=Config.WORKER_PORT, log_level=Config.LOG_LEVEL.lower())
