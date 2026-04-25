"""Runtime LINE Personal client for desktop automation only."""

import logging
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

import requests

from config import Config

logger = logging.getLogger(__name__)


class LinePersonalClient:
    """Client that delegates personal-account sending to a logged-in LINE Desktop worker."""

    def __init__(self, session_file: Optional[Path] = None):
        self.session_file = Path(session_file or Config.SESSION_FILE)
        self.last_error: Optional[str] = None
        self.send_mode = Config.LINE_SEND_MODE

    def check_login_status(self) -> bool:
        if self.send_mode == "automation":
            worker_status = self._get_automation_worker_status()
            if "logged_in" in worker_status:
                return bool(worker_status.get("logged_in"))
            return bool(worker_status.get("ready"))
        return self.send_mode == "mock"

    def get_status(self) -> Dict[str, Any]:
        worker_status = self._get_automation_worker_status() if self.send_mode == "automation" else {}
        return {
            "logged_in": self.check_login_status(),
            "session_file": "LINE Desktop session is managed on the worker machine",
            "send_mode": self.send_mode,
            "last_login_at": None,
            "last_error": self.last_error,
            "has_auth_token": False,
            "transport_ready": self._transport_ready(),
            "automation_worker_url": Config.AUTOMATION_WORKER_URL,
            "automation_worker_status": worker_status,
        }

    def set_session(self, auth_token: str, save: bool = True) -> Dict[str, Any]:
        return {
            "success": False,
            "error": "Token login is disabled. Use LINE_SEND_MODE=automation with a logged-in LINE Desktop worker.",
            "send_mode": self.send_mode,
        }

    def send_text_message(self, group_id: str, message: str, group_name: Optional[str] = None) -> Dict[str, Any]:
        return self.send_messages(group_id, [{"type": "text", "text": message}], group_name=group_name)

    def send_messages(
        self,
        group_id: str,
        messages: List[Dict[str, Any]],
        group_name: Optional[str] = None,
    ) -> Dict[str, Any]:
        normalized_group_id = group_id.strip()
        if not normalized_group_id:
            return {"success": False, "error": "group_id is required"}
        if not isinstance(messages, list) or not messages:
            return {"success": False, "error": "messages is required"}

        if not self.check_login_status():
            return {
                "success": False,
                "error": "LINE Desktop worker is not ready. Keep LINE Desktop logged in and the worker/tunnel running.",
                "mode": self.send_mode,
            }

        details: List[Dict[str, Any]] = []
        sent_count = 0
        for message in messages:
            result = self._send_single_message(normalized_group_id, message, group_name=group_name)
            details.append(result)
            if result["success"]:
                sent_count += 1
                continue

            self.last_error = result.get("error")
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
        return {
            "success": True,
            "mode": details[-1].get("mode", self.send_mode) if details else self.send_mode,
            "sent_count": sent_count,
            "failed_count": 0,
            "details": details,
            "timestamp": datetime.now().isoformat(),
        }

    def _send_single_message(
        self,
        group_id: str,
        message: Dict[str, Any],
        group_name: Optional[str] = None,
    ) -> Dict[str, Any]:
        message_type = str(message.get("type", "text")).strip().lower()

        if message_type == "text":
            text = str(message.get("text", "")).strip()
            if text == "":
                return {"success": False, "error": "text message is empty", "mode": self.send_mode}
            return self._send_text_via_transport(group_id, text, group_name=group_name)

        if message_type == "image":
            image_url = str(message.get("originalContentUrl") or message.get("previewImageUrl") or "").strip()
            if image_url == "":
                return {"success": False, "error": "image url is required", "mode": self.send_mode}
            return self._send_image_via_transport(group_id, image_url, group_name=group_name)

        return {"success": False, "error": f"Unsupported message type: {message_type}", "mode": self.send_mode}

    def _send_text_via_transport(self, group_id: str, message: str, group_name: Optional[str] = None) -> Dict[str, Any]:
        if self.send_mode == "mock":
            logger.info("[MOCK] Would send to group %s: %s", group_id, message[:120])
            return {
                "success": True,
                "message_id": f"mock_{int(time.time())}",
                "mode": "mock",
                "type": "text",
                "timestamp": datetime.now().isoformat(),
            }

        if self.send_mode == "automation":
            return self._send_text_via_automation(group_id, message, group_name=group_name)

        return {
            "success": False,
            "error": "Unsupported LINE_SEND_MODE. Use automation, mock, or disabled.",
            "mode": self.send_mode,
        }

    def _send_image_via_transport(
        self,
        group_id: str,
        image_url: str,
        group_name: Optional[str] = None,
    ) -> Dict[str, Any]:
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

        if self.send_mode == "automation":
            return self._send_image_via_automation(group_id, image_url, group_name=group_name)

        return {
            "success": False,
            "error": "Unsupported LINE_SEND_MODE. Use automation, mock, or disabled.",
            "mode": self.send_mode,
        }

    def _transport_ready(self) -> bool:
        if self.send_mode == "mock":
            return True
        if self.send_mode == "automation":
            worker_status = self._get_automation_worker_status()
            if "logged_in" in worker_status:
                return bool(worker_status.get("logged_in"))
            return bool(worker_status.get("ready"))
        return False

    def _automation_headers(self) -> Dict[str, str]:
        headers = {"Accept": "application/json"}
        if Config.AUTOMATION_WORKER_TOKEN:
            headers["X-Worker-Token"] = Config.AUTOMATION_WORKER_TOKEN
        return headers

    def _get_automation_worker_status(self) -> Dict[str, Any]:
        if not Config.AUTOMATION_WORKER_URL:
            return {"ready": False, "error": "AUTOMATION_WORKER_URL is not configured"}

        try:
            response = requests.get(
                Config.AUTOMATION_WORKER_URL + "/status",
                headers=self._automation_headers(),
                timeout=min(Config.AUTOMATION_WORKER_TIMEOUT, 15),
                verify=Config.AUTOMATION_VERIFY_SSL,
            )
            response.raise_for_status()
            payload = response.json()
            if isinstance(payload, dict):
                return payload
            return {"ready": False, "error": "Invalid automation worker response"}
        except Exception as exc:
            return {"ready": False, "error": str(exc)}

    def _send_text_via_automation(self, group_id: str, message: str, group_name: Optional[str] = None) -> Dict[str, Any]:
        target_name = (group_name or "").strip()
        if target_name == "":
            return {
                "success": False,
                "error": "automation mode requires group_name for desktop targeting",
                "mode": "automation",
                "type": "text",
            }

        return self._post_to_automation_worker(
            group_id=group_id,
            group_name=target_name,
            messages=[{"type": "text", "text": message}],
        )

    def _send_image_via_automation(
        self,
        group_id: str,
        image_url: str,
        group_name: Optional[str] = None,
    ) -> Dict[str, Any]:
        target_name = (group_name or "").strip()
        if target_name == "":
            return {
                "success": False,
                "error": "automation mode requires group_name for desktop targeting",
                "mode": "automation",
                "type": "image",
            }

        return self._post_to_automation_worker(
            group_id=group_id,
            group_name=target_name,
            messages=[{"type": "image", "originalContentUrl": image_url, "previewImageUrl": image_url}],
        )

    def _post_to_automation_worker(
        self,
        group_id: str,
        group_name: str,
        messages: List[Dict[str, Any]],
    ) -> Dict[str, Any]:
        if not Config.AUTOMATION_WORKER_URL:
            return {"success": False, "error": "AUTOMATION_WORKER_URL is not configured", "mode": "automation"}

        payload = {
            "group_id": group_id,
            "group_name": group_name,
            "messages": messages,
        }
        try:
            response = requests.post(
                Config.AUTOMATION_WORKER_URL + "/send",
                json=payload,
                headers=self._automation_headers(),
                timeout=Config.AUTOMATION_WORKER_TIMEOUT,
                verify=Config.AUTOMATION_VERIFY_SSL,
            )
            try:
                body: Any = response.json()
            except Exception:
                body = {"error": response.text}

            if response.status_code < 200 or response.status_code >= 300:
                error = body.get("detail") if isinstance(body, dict) else None
                if isinstance(error, dict):
                    error = error.get("error") or str(error)
                if not error and isinstance(body, dict):
                    error = body.get("error")
                return {
                    "success": False,
                    "error": error or response.text,
                    "mode": "automation",
                    "status_code": response.status_code,
                }

            if isinstance(body, dict):
                body.setdefault("mode", "automation")
                return body
            return {"success": True, "mode": "automation", "timestamp": datetime.now().isoformat()}
        except Exception as exc:
            return {"success": False, "error": f"automation worker failed: {exc}", "mode": "automation"}

    def get_group_summary(self, group_id: str) -> Optional[Dict[str, Any]]:
        for group in Config.load_settings().get("groups", []):
            if str(group.get("id", "")).strip() == group_id:
                name = str(group.get("name", "")).strip()
                return {"id": group_id, "name": name, "groupName": name, "picture_status": ""}
        return None

    def get_joined_groups(self) -> List[Dict[str, Any]]:
        groups = []
        for group in Config.load_settings().get("groups", []):
            name = str(group.get("name", "")).strip()
            groups.append(
                {
                    "id": str(group.get("id", "")).strip(),
                    "name": name,
                    "groupName": name,
                    "picture_status": "",
                }
            )
        return groups

    def send_to_multiple_groups(self, group_ids: List[str], message: str) -> Dict[str, Any]:
        results = {"success": True, "sent_count": 0, "failed_count": 0, "details": []}
        group_name_map = {
            str(group.get("id", "")).strip(): str(group.get("name", "")).strip()
            for group in Config.load_settings().get("groups", [])
        }

        for group_id in group_ids:
            result = self.send_text_message(group_id, message, group_name=group_name_map.get(group_id))

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
        self.last_error = None
        return True


line_client = LinePersonalClient()
