"""Chromium automation worker for sending LINE Personal messages via Chrome Extension."""

from __future__ import annotations

import asyncio
import logging
import platform
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
                    "--disable-blink-features=AutomationControlled"
                ],
                no_viewport=True
            )
            
            logger.info("Waiting for extension to initialize...")
            
            # Use background pages to get the Dynamic Extension ID reliably
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

            # Navigate directly to the LINE interface
            self.page = await self.context.new_page()
            extension_url = f"chrome-extension://{self.extension_id}/index.html"
            await self.page.goto(extension_url)
            
            self.ready = True
            logger.info("Chromium environment ready. Please log in to LINE on the browser if not already.")
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
    _require_token(x_worker_token)
    if not automator.ready or not automator.page:
        raise HTTPException(status_code=503, detail="Worker not ready")
    
    import base64
    try:
        extension_url = f"chrome-extension://{automator.extension_id}/index.html"
        
        # Always navigate to extension page to ensure fresh render
        if automator.extension_id not in automator.page.url:
            logger.info("Navigating to extension page for screenshot...")
            await automator.page.goto(extension_url, wait_until="domcontentloaded")

        # Wait for page to fully render (networkidle or timeout 5s)
        try:
            await automator.page.wait_for_load_state("networkidle", timeout=5000)
        except Exception:
            pass  # Timeout is fine, still take screenshot

        # Extra small delay for JS-rendered content (Vue/React)
        await asyncio.sleep(2)
        
        image_bytes = await automator.page.screenshot(type="png", full_page=True)
        return {"screenshot_base64": base64.b64encode(image_bytes).decode('utf-8')}
    except Exception as e:
        logger.exception("Screenshot failed")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/debug")
async def debug_info(x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    """Debug endpoint - returns current page URL and title for diagnosing blank screens"""
    _require_token(x_worker_token)
    if not automator.ready or not automator.page:
        raise HTTPException(status_code=503, detail="Worker not ready")
    try:
        url = automator.page.url
        title = await automator.page.title()
        content_len = len(await automator.page.content())
        return {
            "current_url": url,
            "page_title": title,
            "content_length": content_len,
            "extension_id": automator.extension_id,
            "ready": automator.ready,
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/send")
async def send(request: WorkerSendRequest, x_worker_token: Optional[str] = Header(default=None)) -> Dict[str, Any]:
    _require_token(x_worker_token)
    try:
        return await automator.send(request.group_name, request.messages)
    except Exception as exc:
        logger.exception("Automation send failed")
        raise HTTPException(status_code=400, detail={"error": str(exc)}) from exc

if __name__ == "__main__":
    uvicorn.run("chromium_line_worker:app", host=Config.WORKER_HOST, port=Config.WORKER_PORT, log_level=Config.LOG_LEVEL.lower())
