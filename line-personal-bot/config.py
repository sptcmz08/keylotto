"""Configuration manager for the LINE Personal Bot."""

import json
import logging
import os
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List

from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env")

logger = logging.getLogger(__name__)


class Config:
    """Application configuration."""

    BASE_DIR = BASE_DIR
    DATA_DIR = Path(os.getenv("DATA_DIR", str(BASE_DIR))).expanduser()

    # API Settings
    API_HOST = os.getenv("API_HOST", "0.0.0.0")
    API_PORT = int(os.getenv("API_PORT", "5000"))

    # LINE Settings
    LINE_PERSONAL_ENABLED = os.getenv("LINE_PERSONAL_ENABLED", "true").lower() == "true"
    LINE_SEND_MODE = os.getenv("LINE_SEND_MODE", "disabled").strip().lower()
    LINEPY_APP_NAME = os.getenv("LINEPY_APP_NAME", "").strip() or None
    LINEPY_SYSTEM_NAME = os.getenv("LINEPY_SYSTEM_NAME", "KEYLOTTO").strip()
    CHRLINE_DEVICE = os.getenv("CHRLINE_DEVICE", "DESKTOPWIN").strip() or "DESKTOPWIN"
    CHRLINE_VERSION = os.getenv("CHRLINE_VERSION", "").strip() or None
    CHRLINE_OS_NAME = os.getenv("CHRLINE_OS_NAME", "").strip() or None
    CHRLINE_OS_VERSION = os.getenv("CHRLINE_OS_VERSION", "").strip() or None
    CHRLINE_QR_MODE = os.getenv("CHRLINE_QR_MODE", "auto").strip().lower() or "auto"
    CHRLINE_DEBUG = os.getenv("CHRLINE_DEBUG", "false").lower() == "true"
    AUTOMATION_WORKER_URL = os.getenv("AUTOMATION_WORKER_URL", "").strip().rstrip("/")
    AUTOMATION_WORKER_TOKEN = os.getenv("AUTOMATION_WORKER_TOKEN", "").strip()
    AUTOMATION_WORKER_TIMEOUT = int(os.getenv("AUTOMATION_WORKER_TIMEOUT", "60"))
    AUTOMATION_VERIFY_SSL = os.getenv("AUTOMATION_VERIFY_SSL", "false").lower() == "true"

    # Filesystem
    SESSION_FILE = Path(os.getenv("SESSION_FILE", str(DATA_DIR / "line_session.pkl"))).expanduser()
    SETTINGS_FILE = Path(os.getenv("SETTINGS_FILE", str(BASE_DIR / "settings.json"))).expanduser()
    LOG_FILE = Path(os.getenv("LOG_FILE", str(DATA_DIR / "logs" / "app.log"))).expanduser()
    CHRLINE_SAVE_PATH = Path(os.getenv("CHRLINE_SAVE_PATH", str(DATA_DIR / "chrline"))).expanduser()
    AUTOMATION_TEMP_DIR = Path(os.getenv("AUTOMATION_TEMP_DIR", str(DATA_DIR / "automation"))).expanduser()

    # Windows worker settings
    WORKER_HOST = os.getenv("WORKER_HOST", "127.0.0.1")
    WORKER_PORT = int(os.getenv("WORKER_PORT", "5001"))
    WORKER_API_TOKEN = os.getenv("WORKER_API_TOKEN", "").strip()
    WORKER_LINE_WINDOW_TITLE_REGEX = os.getenv("WORKER_LINE_WINDOW_TITLE_REGEX", ".*LINE.*").strip()
    WORKER_SEARCH_BOX_X = int(os.getenv("WORKER_SEARCH_BOX_X", "0"))
    WORKER_SEARCH_BOX_Y = int(os.getenv("WORKER_SEARCH_BOX_Y", "0"))
    WORKER_MESSAGE_BOX_X = int(os.getenv("WORKER_MESSAGE_BOX_X", "0"))
    WORKER_MESSAGE_BOX_Y = int(os.getenv("WORKER_MESSAGE_BOX_Y", "0"))
    WORKER_ATTACH_BUTTON_X = int(os.getenv("WORKER_ATTACH_BUTTON_X", "0"))
    WORKER_ATTACH_BUTTON_Y = int(os.getenv("WORKER_ATTACH_BUTTON_Y", "0"))
    WORKER_AFTER_FOCUS_DELAY = float(os.getenv("WORKER_AFTER_FOCUS_DELAY", "0.6"))
    WORKER_AFTER_SEARCH_DELAY = float(os.getenv("WORKER_AFTER_SEARCH_DELAY", "1.0"))
    WORKER_AFTER_OPEN_CHAT_DELAY = float(os.getenv("WORKER_AFTER_OPEN_CHAT_DELAY", "0.8"))
    WORKER_AFTER_TEXT_DELAY = float(os.getenv("WORKER_AFTER_TEXT_DELAY", "0.3"))
    WORKER_AFTER_ATTACH_DELAY = float(os.getenv("WORKER_AFTER_ATTACH_DELAY", "1.2"))
    WORKER_AFTER_IMAGE_SEND_DELAY = float(os.getenv("WORKER_AFTER_IMAGE_SEND_DELAY", "1.2"))

    # Logging
    LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()

    # CORS
    _origins = [origin.strip() for origin in os.getenv("ALLOWED_ORIGINS", "*").split(",")]
    ALLOWED_ORIGINS = [origin for origin in _origins if origin] or ["*"]

    @classmethod
    def ensure_directories(cls) -> None:
        """Create directories for configured files when needed."""
        for path in (
            cls.DATA_DIR,
            cls.SESSION_FILE.parent,
            cls.SETTINGS_FILE.parent,
            cls.LOG_FILE.parent,
            cls.CHRLINE_SAVE_PATH,
            cls.AUTOMATION_TEMP_DIR,
        ):
            path.mkdir(parents=True, exist_ok=True)

    @classmethod
    def default_settings(cls) -> Dict[str, Any]:
        return {
            "groups": [],
            "schedules": [],
            "auto_send": False,
            "last_login": None,
            "created_at": datetime.now().isoformat(),
            "updated_at": datetime.now().isoformat(),
        }

    @classmethod
    def load_settings(cls) -> Dict[str, Any]:
        """Load settings from JSON file."""
        cls.ensure_directories()

        try:
            with cls.SETTINGS_FILE.open("r", encoding="utf-8") as file:
                settings = json.load(file)
        except FileNotFoundError:
            settings = cls.default_settings()
            cls.save_settings(settings)
            return settings
        except json.JSONDecodeError as exc:
            logger.error("Invalid JSON in settings file %s: %s", cls.SETTINGS_FILE, exc)
            return cls.default_settings()

        defaults = cls.default_settings()
        defaults.update(settings)
        return defaults

    @classmethod
    def save_settings(cls, settings: Dict[str, Any]) -> bool:
        """Save settings to JSON file."""
        cls.ensure_directories()

        payload = cls.default_settings()
        payload.update(settings)
        payload["updated_at"] = datetime.now().isoformat()
        payload.setdefault("created_at", datetime.now().isoformat())

        try:
            with cls.SETTINGS_FILE.open("w", encoding="utf-8") as file:
                json.dump(payload, file, ensure_ascii=False, indent=2)
            return True
        except Exception as exc:
            logger.error("Error saving settings: %s", exc)
            return False

    @classmethod
    def add_group(cls, group_id: str, group_name: str = "") -> bool:
        """Add a group to settings."""
        normalized_group_id = group_id.strip()
        if not normalized_group_id:
            return False

        settings = cls.load_settings()
        if any(group.get("id") == normalized_group_id for group in settings.get("groups", [])):
            return False

        settings.setdefault("groups", []).append(
            {
                "id": normalized_group_id,
                "name": group_name.strip(),
                "active": True,
                "added_at": datetime.now().isoformat(),
            }
        )

        return cls.save_settings(settings)

    @classmethod
    def get_active_groups(cls) -> List[str]:
        """Get list of active group IDs."""
        settings = cls.load_settings()
        return [group["id"] for group in settings.get("groups", []) if group.get("active", True)]

    @classmethod
    def remove_group(cls, group_id: str) -> bool:
        """Remove a group from settings."""
        normalized_group_id = group_id.strip()
        settings = cls.load_settings()
        existing_count = len(settings.get("groups", []))
        settings["groups"] = [group for group in settings.get("groups", []) if group.get("id") != normalized_group_id]

        if len(settings["groups"]) == existing_count:
            return False

        return cls.save_settings(settings)


Config.ensure_directories()
