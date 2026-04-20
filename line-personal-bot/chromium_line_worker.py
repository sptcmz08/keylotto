"""Chromium automation worker for sending LINE Personal messages via Chrome Extension."""

from __future__ import annotations

import asyncio
import base64
import logging
import os
import platform
import subprocess
import time
from pathlib import Path
from typing import Any, Dict, List, Optional

import uvicorn
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel

from playwright.async_api import async_playwright, BrowserContext, Page, Playwright

from config import Config

logging.basicConfig(
    level=getattr(logging, Config.LOG_LEVEL),
    format="%(asctime)s - %(name)s - %(levelname)s - %(message)s",
    handlers=[logging.StreamHandler()],
)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="LINE Chromium Automation Worker",
    description="Worker that controls the LINE Chrome Extension",
    version="1.0.0",
)

class WorkerSendRequest(BaseModel):
    group_id: str
    group_name: str
    messages: List[Dict[str, Any]]


def _capture_xvfb_display() -> Optional[bytes]:
    """Capture the xvfb virtual display using ImageMagick's import command.
    
    This is the most reliable way to screenshot Chrome running on xvfb
    because it captures the actual rendered pixels, not the DOM.
    """
    display = os.environ.get("DISPLAY", ":99")
    try:
        result = subprocess.run(
            ["import", "-window", "root", "-display", display, "png:-"],
            capture_output=True,
            timeout=10,
        )
        if result.returncode == 0 and len(result.stdout) > 1000:
            logger.info(f"xvfb display capture OK ({len(result.stdout)} bytes)")
            return result.stdout
        else:
            logger.warning(f"import command returned {result.returncode}, stderr: {result.stderr.decode()[:200]}")
            return None
    except FileNotFoundError:
        logger.warning("ImageMagick 'import' not found. Install with: apt install imagemagick")
        return None
    except subprocess.TimeoutExpired:
        logger.warning("xvfb display capture timed out")
        return None
    except Exception as e:
        logger.warning(f"xvfb display capture failed: {e}")
        return None


class LineChromiumAutomator:
    def __init__(self) -> None:
        self.playwright: Optional[Playwright] = None
        self.context: Optional[BrowserContext] = None
        self.page: Optional[Page] = None
        self.extension_id = "ophjlpahpchlmihnnnihgmmeilfjmjjc"
        self._lock = asyncio.Lock()
        self.ready = False

    async def start(self):
        logger.info("Starting Chromium Playwright context...")
        self.playwright = await async_playwright().start()
        
        ext_path = Config.CHROMIUM_EXTENSION_DIR
        user_data_dir = Config.CHROMIUM_USER_DATA_DIR
        
        Config.ensure_directories()

        if not ext_path.exists() or not (ext_path / "manifest.json").exists():
            logger.error("Extension not found at %s. Please run setup_extension.py", ext_path)
            return

        try:
            # We use standard Playwright Chromium but mask the User-Agent so LINE doesn't drop WebSockets
            real_ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
            self.context = await self.playwright.chromium.launch_persistent_context(
                user_data_dir=str(user_data_dir),
                headless=False,
                user_agent=real_ua,
                args=[
                    f"--disable-extensions-except={ext_path}",
                    f"--load-extension={ext_path}",
                    "--disable-blink-features=AutomationControlled",
                    # ── VPS / root flags ──
                    "--no-sandbox",
                    "--disable-setuid-sandbox",
                    "--disable-gpu",
                    "--disable-dev-shm-usage",
                    "--disable-software-rasterizer",
                    # ── LTSM fix: allow SharedArrayBuffer without cross-origin isolation ──
                    # ltsmSandbox.js uses SharedArrayBuffer (12x), WebAssembly (7x),
                    # crypto.subtle (14x) — SAB needs this flag in sandbox pages
                    "--enable-features=UnrestrictedSharedArrayBuffer,SharedArrayBuffer",
                ],
                no_viewport=True
            )
            
            logger.info("Waiting for extension to initialize...")
            
            # Use background pages / service workers to get the Dynamic Extension ID
            background_page = None
            for _ in range(40):
                if self.context.background_pages:
                    background_page = self.context.background_pages[0]
                    break
                elif self.context.service_workers:
                    background_page = self.context.service_workers[0]
                    break
                await asyncio.sleep(0.5)

            if background_page:
                self.extension_id = background_page.url.split("/")[2]
                logger.info(f"Detected Dynamic Extension ID: {self.extension_id}")
            else:
                logger.warning("Could not detect background page. Extension might fail to load.")

            # ─────────────────────────────────────────────────────────────────
            # Open LINE's popup window via the service worker, exactly as
            # clicking the extension icon would: chrome.windows.create()
            # ─────────────────────────────────────────────────────────────────
            self.page = None
            logger.info("Triggering LINE extension window via service worker...")
            try:
                async with self.context.expect_page(timeout=10000) as page_info:
                    await background_page.evaluate("""
                        chrome.windows.create({
                            url: chrome.runtime.getURL('index.html'),
                            focused: true,
                            width: 800,
                            height: 580,
                            type: 'popup'
                        })
                    """)
                self.page = await page_info.value
                logger.info(f"LINE popup window opened: {self.page.url}")
            except Exception as popup_err:
                logger.warning(f"Service-worker window.create failed ({popup_err}). Falling back to page.goto...")

            # Fallback: direct navigation
            if self.page is None:
                self.page = await self.context.new_page()
                extension_url = f"chrome-extension://{self.extension_id}/index.html"
                await self.page.goto(extension_url, wait_until="domcontentloaded")

            # Wire up console/error capture for debugging
            self.page.on("console", lambda msg: logger.debug(f"[EXT] {msg.type}: {msg.text}"))
            self.page.on("pageerror", lambda err: logger.warning(f"[EXT-ERR] {err}"))

            # Give the app time to render
            await asyncio.sleep(5)

            self.ready = True
            logger.info("Chromium environment ready. Please log in to LINE on the browser if not already.")

            # Quick test: can we capture the display?
            test_img = _capture_xvfb_display()
            if test_img:
                logger.info(f"✅ xvfb display capture test passed ({len(test_img)} bytes)")
            else:
                logger.warning("⚠️ xvfb display capture not available — screenshots will use Playwright fallback only")

        except Exception as e:
            logger.error("Failed to launch chromium: %s", e)

    async def stop(self):
        if self.context:
            await self.context.close()
        if self.playwright:
            await self.playwright.stop()
        self.ready = False

    async def status(self) -> Dict[str, Any]:
        return {
            "ready": self.ready,
            "platform": platform.platform(),
            "mode": "chromium_extension",
            "extension_loaded": self.ready
        }

    async def send(self, group_name: str, messages: List[Dict[str, Any]]) -> Dict[str, Any]:
        if not self.ready or not self.page:
            raise RuntimeError("Chromium worker is not initialized or extension is missing.")
            
        if not group_name.strip():
            raise RuntimeError("group_name is required for automation")

        async with self._lock:
            # We assume the user is logged into the extension.
            # We must be on the extension page
            url = self.page.url
            if self.extension_id not in url:
                await self.page.goto(f"chrome-extension://{self.extension_id}/index.html")
                await self.page.wait_for_timeout(2000)

            # Workflow inside the LINE extension:
            # 1. Focus search box and search for the group_name
            # The extension usually has an input placeholder "Search"
            logger.info("Searching for group: %s", group_name)
            search_input = self.page.get_by_placeholder("Search", exact=False).first
            
            # If search is not found, maybe they are not logged in
            if not await search_input.is_visible():
                raise RuntimeError("Cannot find 'Search' input. Are you logged in to LINE extension?")

            await search_input.click()
            await search_input.fill("")
            await search_input.fill(group_name)
            
            # Wait a tick for search results to appear
            await self.page.wait_for_timeout(1500)
            
            # 2. Click the result
            # Try to click the specific group name from the list
            # We look for the exact text of the group name
            result_item = self.page.locator(f"text='{group_name}'").first
            if not await result_item.is_visible():
                raise RuntimeError(f"Could not find a group matching '{group_name}' in search results.")
            
            await result_item.click()
            await self.page.wait_for_timeout(1000)

            details: List[Dict[str, Any]] = []
            
            # 3. Message box is usually the contenteditable div or textarea.
            # In LINE extension, it's typically a main textarea or contenteditable element.
            # It usually doesn't have an explicit placeholder or it might be "Enter a message".
            
            message_box = self.page.locator('[contenteditable="true"]').last
            
            if not await message_box.is_visible():
                # Fallback to textarea
                message_box = self.page.locator('textarea').last
                
            for message in messages:
                message_type = str(message.get("type", "text")).strip().lower()
                
                if message_type == "text":
                    text = str(message.get("text", "")).strip()
                    if not text:
                        continue
                    
                    await message_box.click()
                    await message_box.type(text, delay=10)
                    await self.page.wait_for_timeout(500)
                    await self.page.keyboard.press("Enter")
                    await self.page.wait_for_timeout(500)
                    details.append({"type": "text", "success": True})
                    continue

                if message_type == "image":
                    # For images, we can utilize the hidden input[type=file]
                    image_url = str(message.get("originalContentUrl") or message.get("previewImageUrl") or "").strip()
                    if not image_url:
                        continue
                        
                    # We need to download the image file first
                    import tempfile
                    import requests
                    from urllib.parse import urlparse
                    
                    response = requests.get(image_url, timeout=30)
                    response.raise_for_status()
                    suffix = Path(urlparse(image_url).path).suffix or ".jpg"
                    if len(suffix) > 10: suffix = ".jpg"
                    
                    temp_file = tempfile.NamedTemporaryFile(suffix=suffix, dir=Config.AUTOMATION_TEMP_DIR, delete=False)
                    temp_file.write(response.content)
                    temp_file.close()
                    
                    try:
                        # Attempt to set the file onto the hidden file input
                        file_inputs = self.page.locator('input[type="file"]')
                        count = await file_inputs.count()
                        if count > 0:
                            # It is usually the first or only file input used for images
                            await file_inputs.first.set_input_files(temp_file.name)
                            await self.page.wait_for_timeout(1500)
                            # Confirmation dialog might popup, press enter
                            await self.page.keyboard.press("Enter")
                            await self.page.wait_for_timeout(1000)
                            details.append({"type": "image", "success": True, "image_url": image_url})
                        else:
                            details.append({"type": "image", "success": False, "error": "file input not found"})
                    except Exception as e:
                        details.append({"type": "image", "success": False, "error": str(e)})
                    finally:
                        try:
                            Path(temp_file.name).unlink(missing_ok=True)
                        except:
                            pass
                    continue
                    
            return {
                "success": True,
                "mode": "chromium",
                "details": details,
                "timestamp": time.time(),
            }

automator = LineChromiumAutomator()

@app.on_event("startup")
async def startup_event():
    logger.info(f"Starting LINE Chromium Automation Worker on {Config.WORKER_HOST}:{Config.WORKER_PORT}")
    await automator.start()

@app.on_event("shutdown")
async def shutdown_event():
    await automator.stop()

def _require_token(x_worker_token: Optional[str]) -> None:
    expected = Config.WORKER_API_TOKEN.strip()
    if expected and x_worker_token != expected:
        raise HTTPException(status_code=401, detail="Invalid worker token")

@app.get("/")
async def root(x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    return {"service": "LINE Chromium Automation Worker", **(await automator.status())}

@app.get("/status")
async def status(x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    return await automator.status()

@app.get("/screenshot")
async def screenshot(x_worker_token: Optional[str] = Header(default=None)):
    """Take a screenshot of the LINE extension.
    
    PRIMARY method: Capture the entire xvfb display using ImageMagick's 'import'.
    This is 100% reliable because it captures actual rendered pixels on the virtual
    screen, completely bypassing the Playwright DOM (which can show blank pages for
    Chrome extensions).
    
    FALLBACK: If ImageMagick is not available, use Playwright page.screenshot().
    """
    _require_token(x_worker_token)
    if not automator.ready:
        raise HTTPException(status_code=503, detail="Worker not ready")
    
    # ═══════════════════════════════════════════════════════════════════
    # PRIMARY: Capture xvfb display directly (guaranteed to work)
    # ═══════════════════════════════════════════════════════════════════
    image_bytes = _capture_xvfb_display()
    if image_bytes:
        return {"screenshot_base64": base64.b64encode(image_bytes).decode('utf-8')}

    # ═══════════════════════════════════════════════════════════════════
    # FALLBACK: Playwright page.screenshot (may show blank for extensions)
    # ═══════════════════════════════════════════════════════════════════
    logger.info("Falling back to Playwright page.screenshot()")
    if not automator.page:
        raise HTTPException(status_code=503, detail="No page available")
    
    try:
        image_bytes = await automator.page.screenshot(type="png", full_page=True)
        return {"screenshot_base64": base64.b64encode(image_bytes).decode('utf-8')}
    except Exception as e:
        logger.exception("Screenshot failed")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/debug")
async def debug_info(x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    """Debug endpoint - returns current page URL and display info."""
    _require_token(x_worker_token)
    display = os.environ.get("DISPLAY", "not set")
    
    page_url = "N/A"
    page_title = "N/A"
    content_len = 0
    if automator.page:
        try:
            page_url = automator.page.url
            page_title = await automator.page.title()
            content_len = len(await automator.page.content())
        except Exception:
            pass

    # Test display capture
    test_img = _capture_xvfb_display()

    return {
        "current_url": page_url,
        "page_title": page_title,
        "content_length": content_len,
        "extension_id": automator.extension_id,
        "display": display,
        "xvfb_capture_works": test_img is not None,
        "xvfb_capture_size": len(test_img) if test_img else 0,
        "ready": automator.ready,
    }

@app.post("/send")
async def send(request: WorkerSendRequest, x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    try:
        return await automator.send(request.group_name, request.messages)
    except Exception as exc:
        logger.exception("Automation send failed")
        raise HTTPException(status_code=400, detail={"error": str(exc)}) from exc


class LoginRequest(BaseModel):
    email: str
    password: str


@app.post("/login")
async def login(request: LoginRequest, x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    """Login to LINE using email and password.
    
    Fills in the login form on the LINE extension page automatically.
    Session will be persisted in chromium_data/ for future use.
    """
    _require_token(x_worker_token)
    if not automator.ready or not automator.page:
        raise HTTPException(status_code=503, detail="Worker not ready")
    
    try:
        page = automator.page
        logger.info("Attempting LINE login with email/password...")
        
        # Wait for the login form to be visible
        try:
            await page.wait_for_selector('input[name="tid"]', timeout=10000)
        except Exception:
            # Try alternative selectors
            try:
                await page.wait_for_selector('input[type="email"], input[placeholder*="Email"], input[placeholder*="email"]', timeout=5000)
            except Exception:
                return {"success": False, "error": "Login form not found. The extension may already be logged in or not loaded."}
        
        # Fill email field
        email_input = page.locator('input[name="tid"]').first
        if await email_input.count() == 0:
            email_input = page.locator('input[type="email"], input[placeholder*="Email"], input[placeholder*="email"]').first
        
        await email_input.click()
        await email_input.fill("")
        await email_input.fill(request.email)
        await page.wait_for_timeout(500)
        
        # Fill password field  
        password_input = page.locator('input[name="tpasswd"]').first
        if await password_input.count() == 0:
            password_input = page.locator('input[type="password"]').first
        
        await password_input.click()
        await password_input.fill("")
        await password_input.fill(request.password)
        await page.wait_for_timeout(500)
        
        # Click login button
        login_btn = page.locator('button:has-text("Log in"), button:has-text("เข้าสู่ระบบ")').first
        if await login_btn.count() == 0:
            login_btn = page.locator('button[type="submit"]').first
        
        await login_btn.click()
        logger.info("Login form submitted, waiting for response...")
        
        # Wait for navigation or page change (login processing)
        await page.wait_for_timeout(5000)
        
        # Check if login succeeded or if it prompted for a verification code
        is_pin_screen = False
        pin_code = ""
        try:
            # LINE usually shows an h1 or some text like "Verification code" and a big 4-6 digit number
            pin_code_el = page.locator('.PinCode, .mdCMN01PinCode, .mdMN01PinCode, [class*="PinCode"], .pincode').first
            if await pin_code_el.count() > 0:
                is_pin_screen = True
                pin_code = await pin_code_el.text_content()
                pin_code = str(pin_code).strip()
        except Exception:
            pass
            
        if is_pin_screen:
            logger.info(f"Login requires PIN verification: {pin_code}")
            screenshot_bytes = _capture_xvfb_display()
            result = {
                "success": True, 
                "message": f"กรุณานำรหัส {pin_code} ไปป้อนในแอป LINE บนมือถือของคุณเพื่อยืนยันการเข้าระบบ",
                "pin_code": pin_code
            }
            if screenshot_bytes:
                result["screenshot_base64"] = base64.b64encode(screenshot_bytes).decode('utf-8')
            return result
        
        # Check if still on the login form (this means it failed)
        still_has_login = False
        try:
            email_check = page.locator('input[name="tid"], input[type="email"]').first
            still_has_login = await email_check.is_visible(timeout=3000)
        except Exception:
            still_has_login = False
        
        if still_has_login:
            # We must be careful because the right side of the screen always has
            # a red error for the broken QR code ("Unable to log in at this time")
            # We want to find errors related to the email form specifically
            error_text = ""
            try:
                # The login form is usually on the left side, or under the form inputs
                error_els = await page.locator('.MdTxtAlert, .error, [class*="error"], [class*="alert"]').all()
                for el in error_els:
                    text = await el.text_content() or ""
                    # Skip the known QR code error which is a false positive
                    if "Unable to log in at this time" in text or "ไม่สามารถเข้าสู่ระบบ" in text:
                        continue
                    if text.strip() != "":
                        error_text = text.strip()
                        break
            except Exception:
                pass
                
            if error_text:
                # Actual form error found (e.g. invalid password)
                screenshot_bytes = _capture_xvfb_display()
                result = {
                    "success": False, 
                    "error": error_text,
                    "hint": "Check email/password or try again"
                }
                if screenshot_bytes:
                    result["screenshot_base64"] = base64.b64encode(screenshot_bytes).decode('utf-8')
                return result

        logger.info("✅ LINE login successful!")
        
        # Take screenshot of logged-in state
        await page.wait_for_timeout(3000)
        screenshot_bytes = _capture_xvfb_display()
        result = {"success": True, "message": "Login successful! Session saved."}
        if screenshot_bytes:
            result["screenshot_base64"] = base64.b64encode(screenshot_bytes).decode('utf-8')
        return result
        
    except Exception as e:
        logger.exception("Login failed")
        raise HTTPException(status_code=500, detail=str(e))


if __name__ == "__main__":
    uvicorn.run("chromium_line_worker:app", host=Config.WORKER_HOST, port=Config.WORKER_PORT, log_level=Config.LOG_LEVEL.lower())

