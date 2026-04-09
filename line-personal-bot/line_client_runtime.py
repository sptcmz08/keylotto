"""Runtime LINE personal client used by the API layer."""

import logging
import mimetypes
import pickle
import tempfile
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional
from urllib.parse import urlparse

import requests

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
        self._chrline_client = None
        self._chrline_auth_token: Optional[str] = None

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
        if self.send_mode == "automation":
            worker_status = self._get_automation_worker_status()
            return bool(worker_status.get("ready"))
        return self.is_logged_in and bool(self.auth_token)

    def get_status(self) -> Dict[str, Any]:
        worker_status = self._get_automation_worker_status() if self.send_mode == "automation" else {}
        return {
            "logged_in": self.check_login_status(),
            "session_file": str(self.session_file),
            "send_mode": self.send_mode,
            "last_login_at": self.last_login_at,
            "last_error": self.last_error,
            "has_auth_token": bool(self.auth_token),
            "transport_ready": self._transport_ready(),
            "chrline_device": Config.CHRLINE_DEVICE,
            "chrline_save_path": str(Config.CHRLINE_SAVE_PATH),
            "automation_worker_url": Config.AUTOMATION_WORKER_URL,
            "automation_worker_status": worker_status,
        }

    def set_session(self, auth_token: str, save: bool = True) -> Dict[str, Any]:
        cleaned_token = auth_token.strip()
        if not cleaned_token:
            return {"success": False, "error": "auth_token is required"}

        self.auth_token = cleaned_token
        self.is_logged_in = True
        self.last_login_at = datetime.now().isoformat()
        self.last_error = None
        self._reset_clients()

        if save and not self._save_session():
            return {"success": False, "error": self.last_error or "Failed to save session"}

        logger.info("Session updated successfully")
        return {
            "success": True,
            "logged_in": True,
            "last_login_at": self.last_login_at,
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
            return {"success": False, "error": "Not logged in. Set session or complete login first."}

        details: List[Dict[str, Any]] = []
        sent_count = 0

        try:
            for message in messages:
                result = self._send_single_message(normalized_group_id, message, group_name=group_name)
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

        if self.send_mode == "chrline":
            return self._send_text_via_chrline(group_id, message)

        if self.send_mode == "linepy":
            return self._send_text_via_linepy(group_id, message)

        return {
            "success": False,
            "error": "LINE personal transport is not implemented. Use LINE_SEND_MODE=mock for testing, CHRLINE for current QR login, or linepy only for legacy compatibility.",
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

        if self.send_mode == "chrline":
            return self._send_image_via_chrline(group_id, image_url)

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
        if self.send_mode == "automation":
            return bool(self._get_automation_worker_status().get("ready"))
        if self.send_mode in {"chrline", "linepy"}:
            return bool(self.auth_token)
        return False

    def _reset_clients(self) -> None:
        self._linepy_client = None
        self._linepy_auth_token = None
        self._chrline_client = None
        self._chrline_auth_token = None

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

    def _get_chrline_client(self):
        if self._chrline_client is not None and self._chrline_auth_token == self.auth_token:
            return self._chrline_client

        if not self.auth_token:
            raise RuntimeError("Missing auth token for CHRLINE transport")

        try:
            from CHRLINE import CHRLINE
        except ImportError as exc:
            raise RuntimeError(
                "CHRLINE is not installed. Run `pip install -r requirements.txt` in line-personal-bot first."
            ) from exc

        kwargs: Dict[str, Any] = {
            "authTokenOrEmail": self.auth_token,
            "device": Config.CHRLINE_DEVICE,
            "savePath": str(Config.CHRLINE_SAVE_PATH),
            "debug": Config.CHRLINE_DEBUG,
        }
        if Config.CHRLINE_VERSION:
            kwargs["version"] = Config.CHRLINE_VERSION
        if Config.CHRLINE_OS_NAME:
            kwargs["os_name"] = Config.CHRLINE_OS_NAME
        if Config.CHRLINE_OS_VERSION:
            kwargs["os_version"] = Config.CHRLINE_OS_VERSION

        client = CHRLINE(**kwargs)
        self._chrline_client = client
        self._chrline_auth_token = self.auth_token
        return client

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
            body: Any
            try:
                body = response.json()
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

    def _send_text_via_chrline(self, group_id: str, message: str) -> Dict[str, Any]:
        try:
            client = self._get_chrline_client()
            response = client.sendMessage(group_id, message)
            message_id = self._extract_chrline_message_id(response)
            return {
                "success": True,
                "message_id": message_id,
                "to": group_id,
                "mode": "chrline",
                "type": "text",
                "timestamp": datetime.now().isoformat(),
            }
        except Exception as exc:
            self._chrline_client = None
            self._chrline_auth_token = None
            return {
                "success": False,
                "error": f"CHRLINE send failed: {exc}",
                "mode": "chrline",
                "type": "text",
            }

    def _send_image_via_chrline(self, group_id: str, image_url: str) -> Dict[str, Any]:
        temp_path: Optional[Path] = None
        try:
            client = self._get_chrline_client()
            temp_path = self._download_image_for_chrline(image_url)
            response = client.sendImage(group_id, str(temp_path))
            return {
                "success": True,
                "message_id": self._extract_chrline_message_id(response),
                "mode": "chrline",
                "type": "image",
                "image_url": image_url,
                "timestamp": datetime.now().isoformat(),
            }
        except Exception as exc:
            self._chrline_client = None
            self._chrline_auth_token = None
            return {
                "success": False,
                "error": f"CHRLINE image send failed: {exc}",
                "mode": "chrline",
                "type": "image",
                "image_url": image_url,
            }
        finally:
            if temp_path and temp_path.exists():
                try:
                    temp_path.unlink()
                except OSError:
                    logger.debug("Could not remove temp image %s", temp_path)

    def _download_image_for_chrline(self, image_url: str) -> Path:
        import requests

        Config.ensure_directories()
        response = requests.get(image_url, timeout=30)
        response.raise_for_status()

        content_type = response.headers.get("Content-Type", "").split(";")[0].strip().lower()
        suffix = mimetypes.guess_extension(content_type) or Path(urlparse(image_url).path).suffix or ".jpg"
        if len(suffix) > 10:
            suffix = ".jpg"

        temp_file = tempfile.NamedTemporaryFile(
            prefix="line-personal-",
            suffix=suffix,
            dir=Config.CHRLINE_SAVE_PATH,
            delete=False,
        )
        temp_path = Path(temp_file.name)
        try:
            temp_file.write(response.content)
        finally:
            temp_file.close()
        return temp_path

    def get_group_summary(self, group_id: str) -> Optional[Dict[str, Any]]:
        if self.send_mode == "automation":
            for group in Config.load_settings().get("groups", []):
                if str(group.get("id", "")).strip() == group_id:
                    name = str(group.get("name", "")).strip()
                    return {"id": group_id, "name": name, "groupName": name, "picture_status": ""}
            return None

        if not self.check_login_status():
            return None

        if self.send_mode == "chrline":
            return self._get_group_summary_chrline(group_id)

        try:
            client = self._get_linepy_client()
            group = client.getGroup(group_id)
            name = getattr(group, "name", "") or ""
            picture_status = getattr(group, "pictureStatus", "") or ""
            return {
                "id": group_id,
                "name": name,
                "groupName": name,
                "picture_status": picture_status,
            }
        except Exception as exc:
            self.last_error = f"group summary failed: {exc}"
            logger.warning(self.last_error)
            return None

    def _get_group_summary_chrline(self, group_id: str) -> Optional[Dict[str, Any]]:
        try:
            client = self._get_chrline_client()
            response = client.getChats([group_id], withMembers=False, withInvitees=False)
            chat = self._extract_first_chat(response)
            if chat is None:
                return None

            name = self._pick_value(chat, "chatName", "name") or ""
            picture_status = self._pick_value(chat, "pictureStatus", "pictureStatusValue", "picturePath") or ""
            mid = self._pick_value(chat, "chatMid", "mid") or group_id
            return {
                "id": str(mid),
                "name": str(name),
                "groupName": str(name),
                "picture_status": str(picture_status),
            }
        except Exception as exc:
            self.last_error = f"group summary failed: {exc}"
            logger.warning(self.last_error)
            return None

    def get_joined_groups(self) -> List[Dict[str, Any]]:
        if self.send_mode == "automation":
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

        if not self.check_login_status():
            return []

        if self.send_mode == "chrline":
            return self._get_joined_groups_chrline()

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
            groups.append(summary or {"id": str(group_id), "name": "", "groupName": "", "picture_status": ""})
        return groups

    def _get_joined_groups_chrline(self) -> List[Dict[str, Any]]:
        try:
            client = self._get_chrline_client()
            mids_response = client.getAllChatMids()
            group_ids = self._extract_chat_mids(mids_response)
            if not group_ids:
                return []

            chats_response = client.getChats(group_ids, withMembers=False, withInvitees=False)
            chats = self._extract_chat_list(chats_response)
            if not chats:
                return [{"id": group_id, "name": "", "groupName": "", "picture_status": ""} for group_id in group_ids]

            groups: List[Dict[str, Any]] = []
            for chat in chats:
                mid = self._pick_value(chat, "chatMid", "mid")
                name = self._pick_value(chat, "chatName", "name") or ""
                picture_status = self._pick_value(chat, "pictureStatus", "pictureStatusValue", "picturePath") or ""
                if not mid:
                    continue
                groups.append(
                    {
                        "id": str(mid),
                        "name": str(name),
                        "groupName": str(name),
                        "picture_status": str(picture_status),
                    }
                )

            seen_ids = {group["id"] for group in groups}
            for group_id in group_ids:
                if group_id not in seen_ids:
                    groups.append({"id": group_id, "name": "", "groupName": "", "picture_status": ""})
            return groups
        except Exception as exc:
            self.last_error = f"list joined groups failed: {exc}"
            logger.warning(self.last_error)
            return []

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
        try:
            self.is_logged_in = False
            self.auth_token = None
            self.last_login_at = None
            self.last_error = None
            self._reset_clients()

            if self.session_file.exists():
                self.session_file.unlink()

            logger.info("Logged out successfully")
            return True
        except Exception as exc:
            self.last_error = f"Error during logout: {exc}"
            logger.error(self.last_error)
            return False

    def _extract_chrline_message_id(self, response: Any) -> Optional[str]:
        return self._pick_value(response, "id", "_id", "messageId")

    def _extract_chat_mids(self, response: Any) -> List[str]:
        for key in ("memberChatMids", "chatMids", "mids"):
            value = self._pick_value(response, key)
            if isinstance(value, list):
                return [str(item) for item in value if item]
        if isinstance(response, list):
            return [str(item) for item in response if isinstance(item, str)]
        return []

    def _extract_first_chat(self, response: Any) -> Optional[Any]:
        chats = self._extract_chat_list(response)
        return chats[0] if chats else None

    def _extract_chat_list(self, response: Any) -> List[Any]:
        for key in ("chats", "memberChats", "chatList"):
            value = self._pick_value(response, key)
            if isinstance(value, list):
                return value
        if isinstance(response, list):
            return response
        return []

    def _pick_value(self, value: Any, *keys: str) -> Any:
        candidates: List[Any] = [value]
        visited: set[int] = set()

        while candidates:
            current = candidates.pop(0)
            marker = id(current)
            if marker in visited:
                continue
            visited.add(marker)

            if isinstance(current, dict):
                for key in keys:
                    if key in current and current[key] not in (None, ""):
                        return current[key]
                candidates.extend(v for v in current.values() if isinstance(v, (dict, list, tuple)))
                continue

            for key in keys:
                attr = getattr(current, key, None)
                if attr not in (None, ""):
                    return attr

            if isinstance(current, (list, tuple)):
                candidates.extend(item for item in current if isinstance(item, (dict, list, tuple)) or hasattr(item, "__dict__"))
                continue

            if hasattr(current, "__dict__"):
                candidates.append(vars(current))

        return None


line_client = LinePersonalClient()
