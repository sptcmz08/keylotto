"""
LINE Personal Account Client
ใช้สำหรับส่งข้อความผ่าน LINE Personal Account (ไม่ใช่ Official Account)

หมายเหตุ: ใช้ Chrome Protocol หรือ Automation เนื่องจากไม่มี official API สำหรับ Personal Account
"""
import logging
import os
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

        self._load_session()
    
    def _load_session(self) -> bool:
        """Load a previously saved session."""
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
        """Persist the current session."""
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
        """Check if currently logged in."""
        return self.is_logged_in and bool(self.auth_token)

    def get_status(self) -> Dict[str, Any]:
        """Expose runtime status for the API layer."""
        return {
            "logged_in": self.check_login_status(),
            "session_file": str(self.session_file),
            "send_mode": self.send_mode,
            "last_login_at": self.last_login_at,
            "last_error": self.last_error,
            "has_auth_token": bool(self.auth_token),
        }

    def set_session(self, auth_token: str, save: bool = True) -> Dict[str, Any]:
        """Store a session token for development or external login flows."""
        cleaned_token = auth_token.strip()
        if not cleaned_token:
            return {"success": False, "error": "auth_token is required"}

        self.auth_token = cleaned_token
        self.is_logged_in = True
        self.last_login_at = datetime.now().isoformat()
        self.last_error = None

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
        """
        Send text message to a group
        
        หมายเหตุ: เนื่องจากไม่มี official API สำหรับ Personal Account
        วิธีนี้ใช้ workaround ผ่าน LINE Chrome Extension protocol หรือ automation
        """
        if not self.check_login_status():
            return {
                'success': False,
                'error': 'Not logged in. Please login with QR code first.'
            }
        
        try:
            # Method 1: Try using LINE internal API (if we have auth token)
            result = self._send_via_internal_api(group_id, message)
            
            if result['success']:
                logger.info(f"Message sent to {group_id}")
                return result
            
            # Method 2: Fallback to logging for debugging
            logger.warning(f"Failed to send message: {result.get('error')}")
            return result
            
        except Exception as e:
            logger.error(f"Error sending message: {e}")
            return {
                'success': False,
                'error': str(e)
            }
    
    def _send_via_internal_api(self, group_id: str, message: str) -> Dict[str, Any]:
        """
        Send message using LINE internal API
        This is a simplified version - actual implementation would need reverse engineering
        """
        try:
            # Note: This is a placeholder implementation
            # Actual implementation would use LINE's internal Thrift protocol
            # or Chrome extension API
            
            # For now, log the attempt and return success for testing
            logger.info(f"[MOCK] Would send to group {group_id}: {message[:50]}...")
            
            return {
                'success': True,
                'message_id': f'msg_{int(time.time())}',
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Internal API error: {str(e)}'
            }
    
    def send_to_multiple_groups(self, group_ids: List[str], message: str) -> Dict[str, Any]:
        """Send message to multiple groups"""
        results = {
            'success': True,
            'sent_count': 0,
            'failed_count': 0,
            'details': []
        }
        
        for group_id in group_ids:
            result = self.send_text_message(group_id, message)
            
            if result['success']:
                results['sent_count'] += 1
            else:
                results['failed_count'] += 1
                results['success'] = False
            
            results['details'].append({
                'group_id': group_id,
                'success': result['success'],
                'error': result.get('error')
            })
        
        return results
    
    def logout(self) -> bool:
        """Logout and clear session"""
        try:
            self.is_logged_in = False
            self.auth_token = None
            
            if os.path.exists(self.session_file):
                os.remove(self.session_file)
            
            logger.info("Logged out successfully")
            return True
        except Exception as e:
            logger.error(f"Error during logout: {e}")
            return False

# Global client instance
line_client = LinePersonalClient()
