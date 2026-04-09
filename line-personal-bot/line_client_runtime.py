"""Runtime LINE personal client used by the API layer."""

import logging
import pickle
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

from config import Config

logger = logging.getLogger(__name__)


class LinePersonalClient:
    """Client for LINE Personal Account messaging."""

    def __init__(self, session_file: Optional[Path] = None):
        self.session_file = Path(session_file or Config.SESSION_FILE)
        self.is_logged_in = False
        self.auth_token: Optional[str] = None
        self.last_error: Optional[str] = None
        self.last_login_at: Optional[str] = None
        self.send_mode = Config.LINE_SEND_MODE
        self._linepy_client = None
        self._linepy_auth_token: Optional[str] = None

        self._load_session()

    def _load_session(self) -> bool:
        try:
            if self.session_file.exists():
                with self.session_file.open("rb") as file:
                    session_data = pickle.load(file)

                self.auth_token = session_data.get("auth_token")
                self.is_logged_in = bool(session_data.get("is_logged_in", False) and self.auth_token)
                self.last_login_at = session_data.get("last_login_at")
                self.last_error = session_data.get("last_error")

                if self.is_logged_in:
                    logger.info("Session loaded successfully from %s", self.session_file)
                    return True
        except Exception as exc:
            self.last_error = f"Error loading session: {exc}"
            logger.error(self.last_error)

        return False

    def _save_session(self) -> bool:
        try:
            self.session_file.parent.mkdir(parents=True, exist_ok=True)
            session_data = {
                "auth_token": self.auth_token,
                "is_logged_in": self.is_logged_in,
                "last_login_at": self.last_login_at,
                "last_error": self.last_error,
                "saved_at": datetime.now().isoformat(),
            }
            with self.session_file.open("wb") as file:
                pickle.dump(session_data, file)
            return True
        except Exception as exc:
            self.last_error = f"Error saving session: {exc}"
            logger.error(self.last_error)
            return False

    def check_login_status(self) -> bool:
        return self.is_logged_in and bool(self.auth_token)

    def get_status(self) -> Dict[str, Any]:
        return {
            "logged_in": self.check_login_status(),
            "session_file": str(self.session_file),
            "send_mode": self.send_mode,
            "last_login_at": self.last_login_at,
            "last_error": self.last_error,
            "has_auth_token": bool(self.auth_token),
            "transport_ready": self._transport_ready(),
        }

    def set_session(self, auth_token: str, save: bool = True) -> Dict[str, Any]:
        cleaned_token = auth_token.strip()
        if not cleaned_token:
            return {"success": False, "error": "auth_token is required"}

        self.auth_token = cleaned_token
        self.is_logged_in = True
        self.last_login_at = datetime.now().isoformat()
        self.last_error = None
        self._linepy_client = None
        self._linepy_auth_token = None

        if save and not self._save_session():
            return {"success": False, "error": self.last_error or "Failed to save session"}

        logger.info("Session updated successfully")
        return {
            "success": True,
            "logged_in": True,
            "last_login_at": self.last_login_at,
            "send_mode": self.send_mode,
        }

    def send_text_message(self, group_id: str, message: str) -> Dict[str, Any]:
        return self.send_messages(group_id, [{"type": "text", "text": message}])

    def send_messages(self, group_id: str, messages: List[Dict[str, Any]]) -> Dict[str, Any]:
        normalized_group_id = group_id.strip()
        if not normalized_group_id:
            return {"success": False, "error": "group_id is required"}

        if not isinstance(messages, list) or not messages:
            return {"success": False, "error": "messages is required"}

        if not self.check_login_status():
            return {"success": False, "error": "Not logged in. Set session or complete login first."}

        details: List[Dict[str, Any]] = []
        sent_count = 0

        try:
            for message in messages:
                result = self._send_single_message(normalized_group_id, message)
                details.append(result)
                if result["success"]:
                    sent_count += 1
                    continue

                self.last_error = result.get("error")
                logger.warning("Failed to send message to %s: %s", normalized_group_id, self.last_error)
                return {
                    "success": False,
                    "error": result.get("error", "Failed to send message"),
                    "mode": result.get("mode", self.send_mode),
                    "sent_count": sent_count,
                    "failed_count": len(messages) - sent_count,
                    "details": details,
                    "timestamp": datetime.now().isoformat(),
                }

            self.last_error = None
            logger.info("Sent %s message(s) to %s", sent_count, normalized_group_id)
            return {
                "success": True,
                "mode": details[-1].get("mode", self.send_mode) if details else self.send_mode,
                "sent_count": sent_count,
                "failed_count": 0,
                "details": details,
                "timestamp": datetime.now().isoformat(),
            }
        except Exception as exc:
            self.last_error = str(exc)
            logger.error("Error sending messages: %s", exc)
            return {"success": False, "error": str(exc), "mode": self.send_mode}

    def _send_single_message(self, group_id: str, message: Dict[str, Any]) -> Dict[str, Any]:
        message_type = str(message.get("type", "text")).strip().lower()

        if message_type == "text":
            text = str(message.get("text", "")).strip()
            if text == "":
                return {"success": False, "error": "text message is empty", "mode": self.send_mode}
            return self._send_via_internal_api(group_id, text)

        if message_type == "image":
            image_url = str(message.get("originalContentUrl") or message.get("previewImageUrl") or "").strip()
            if image_url == "":
                return {"success": False, "error": "image url is required", "mode": self.send_mode}
            return self._send_image_via_transport(group_id, image_url)

        return {"success": False, "error": f"Unsupported message type: {message_type}", "mode": self.send_mode}

    def _send_via_internal_api(self, group_id: str, message: str) -> Dict[str, Any]:
        if self.send_mode == "mock":
            logger.info("[MOCK] Would send to group %s: %s", group_id, message[:120])
            return {
                "success": True,
                "message_id": f"mock_{int(time.time())}",
                "mode": "mock",
                "type": "text",
                "timestamp": datetime.now().isoformat(),
            }

        if self.send_mode == "linepy":
            return self._send_text_via_linepy(group_id, message)

        return {
            "success": False,
            "error": "LINE personal transport is not implemented. Use LINE_SEND_MODE=mock for testing or LINE_SEND_MODE=linepy for real sending.",
            "mode": self.send_mode,
        }

    def _send_image_via_transport(self, group_id: str, image_url: str) -> Dict[str, Any]:
        if self.send_mode == "mock":
            logger.info("[MOCK] Would send image to group %s: %s", group_id, image_url)
            return {
                "success": True,
                "message_id": f"mock_img_{int(time.time())}",
                "mode": "mock",
                "type": "image",
                "image_url": image_url,
                "timestamp": datetime.now().isoformat(),
            }

        if self.send_mode == "linepy":
            return self._send_image_via_linepy(group_id, image_url)

        return {
            "success": False,
            "error": "LINE personal image transport is not implemented.",
            "mode": self.send_mode,
        }

    def _transport_ready(self) -> bool:
        if self.send_mode == "mock":
            return True
        if self.send_mode == "linepy":
            return bool(self.auth_token)
        return False

    def _get_linepy_client(self):
        if self._linepy_client is not None and self._linepy_auth_token == self.auth_token:
            return self._linepy_client

        if not self.auth_token:
            raise RuntimeError("Missing auth token for linepy transport")

        try:
            from linepy import LINE
        except ImportError as exc:
            raise RuntimeError(
                "linepy is not installed. Run `pip install -r requirements.txt` in line-personal-bot first."
            ) from exc

        kwargs: Dict[str, Any] = {"idOrAuthToken": self.auth_token}
        if Config.LINEPY_APP_NAME:
            kwargs["appName"] = Config.LINEPY_APP_NAME

        client = LINE(**kwargs)
        self._linepy_client = client
        self._linepy_auth_token = self.auth_token
        return client

    def _send_text_via_linepy(self, group_id: str, message: str) -> Dict[str, Any]:
        try:
            client = self._get_linepy_client()
            response = client.sendMessage(group_id, message)
            message_id = getattr(response, "id", None) or getattr(response, "_id", None)
            to_mid = getattr(response, "to", None)
            return {
                "success": True,
                "message_id": message_id,
                "to": to_mid or group_id,
                "mode": "linepy",
                "type": "text",
                "timestamp": datetime.now().isoformat(),
            }
        except Exception as exc:
            self._linepy_client = None
            self._linepy_auth_token = None
            return {
                "success": False,
                "error": f"linepy send failed: {exc}",
                "mode": "linepy",
                "type": "text",
            }

    def _send_image_via_linepy(self, group_id: str, image_url: str) -> Dict[str, Any]:
        try:
            client = self._get_linepy_client()
            response = client.sendImageWithURL(group_id, image_url)
            return {
                "success": bool(response),
                "mode": "linepy",
                "type": "image",
                "image_url": image_url,
                "timestamp": datetime.now().isoformat(),
            }
        except Exception as exc:
            self._linepy_client = None
            self._linepy_auth_token = None
            return {
                "success": False,
                "error": f"linepy image send failed: {exc}",
                "mode": "linepy",
                "type": "image",
                "image_url": image_url,
            }

    def get_group_summary(self, group_id: str) -> Optional[Dict[str, Any]]:
        if not self.check_login_status():
            return None

        try:
            client = self._get_linepy_client()
            group = client.getGroup(group_id)
            name = getattr(group, "name", "") or ""
            picture_status = getattr(group, "pictureStatus", "") or ""
            return {
                "id": group_id,
                "name": name,
                "picture_status": picture_status,
            }
        except Exception as exc:
            self.last_error = f"group summary failed: {exc}"
            logger.warning(self.last_error)
            return None

    def get_joined_groups(self) -> List[Dict[str, Any]]:
        if not self.check_login_status():
            return []

        try:
            client = self._get_linepy_client()
            group_ids = list(client.getGroupIdsJoined() or [])
        except Exception as exc:
            self.last_error = f"list joined groups failed: {exc}"
            logger.warning(self.last_error)
            return []

        groups: List[Dict[str, Any]] = []
        for group_id in group_ids:
            summary = self.get_group_summary(str(group_id))
            groups.append(summary or {"id": str(group_id), "name": "", "picture_status": ""})
        return groups

    def send_to_multiple_groups(self, group_ids: List[str], message: str) -> Dict[str, Any]:
        results = {"success": True, "sent_count": 0, "failed_count": 0, "details": []}

        for group_id in group_ids:
            result = self.send_text_message(group_id, message)

            if result["success"]:
                results["sent_count"] += 1
            else:
                results["failed_count"] += 1
                results["success"] = False

            results["details"].append(
                {
                    "group_id": group_id,
                    "success": result["success"],
                    "error": result.get("error"),
                    "mode": result.get("mode"),
                }
            )

        return results

    def logout(self) -> bool:
        try:
            self.is_logged_in = False
            self.auth_token = None
            self.last_login_at = None
            self.last_error = None
            self._linepy_client = None
            self._linepy_auth_token = None

            if self.session_file.exists():
                self.session_file.unlink()

            logger.info("Logged out successfully")
            return True
        except Exception as exc:
            self.last_error = f"Error during logout: {exc}"
            logger.error(self.last_error)
            return False


line_client = LinePersonalClient()
