"""
Configuration manager for LINE Personal Bot
"""
import os
import json
from typing import List, Dict, Any
from dotenv import load_dotenv

load_dotenv()

class Config:
    """Application configuration"""
    
    # API Settings
    API_HOST = os.getenv('API_HOST', '0.0.0.0')
    API_PORT = int(os.getenv('API_PORT', '5000'))
    
    # LINE Settings
    LINE_PERSONAL_ENABLED = os.getenv('LINE_PERSONAL_ENABLED', 'true').lower() == 'true'
    SESSION_FILE = os.getenv('SESSION_FILE', 'line_session.pkl')
    
    # Logging
    LOG_LEVEL = os.getenv('LOG_LEVEL', 'INFO')
    LOG_FILE = os.getenv('LOG_FILE', '/var/log/line-bot/app.log')
    
    # CORS
    ALLOWED_ORIGINS = os.getenv('ALLOWED_ORIGINS', '*').split(',')
    
    # Settings file for groups and schedules
    SETTINGS_FILE = 'settings.json'
    
    @classmethod
    def load_settings(cls) -> Dict[str, Any]:
        """Load settings from JSON file"""
        try:
            with open(cls.SETTINGS_FILE, 'r', encoding='utf-8') as f:
                return json.load(f)
        except FileNotFoundError:
            # Return default settings
            return {
                "groups": [],
                "schedules": [],
                "auto_send": False,
                "last_login": None
            }
    
    @classmethod
    def save_settings(cls, settings: Dict[str, Any]) -> bool:
        """Save settings to JSON file"""
        try:
            with open(cls.SETTINGS_FILE, 'w', encoding='utf-8') as f:
                json.dump(settings, f, ensure_ascii=False, indent=2)
            return True
        except Exception as e:
            print(f"Error saving settings: {e}")
            return False
    
    @classmethod
    def add_group(cls, group_id: str, group_name: str = "") -> bool:
        """Add a group to settings"""
        settings = cls.load_settings()
        
        # Check if group already exists
        for group in settings["groups"]:
            if group["id"] == group_id:
                return False
        
        settings["groups"].append({
            "id": group_id,
            "name": group_name,
            "active": True,
            "added_at": None
        })
        
        return cls.save_settings(settings)
    
    @classmethod
    def get_active_groups(cls) -> List[str]:
        """Get list of active group IDs"""
        settings = cls.load_settings()
        return [g["id"] for g in settings["groups"] if g.get("active", True)]
    
    @classmethod
    def remove_group(cls, group_id: str) -> bool:
        """Remove a group from settings"""
        settings = cls.load_settings()
        settings["groups"] = [g for g in settings["groups"] if g["id"] != group_id]
        return cls.save_settings(settings)
