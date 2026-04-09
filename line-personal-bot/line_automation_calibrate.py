"""Helper to capture mouse coordinates relative to the LINE desktop window."""

from __future__ import annotations

import platform
import time


def main() -> int:
    if platform.system() != "Windows":
        print("This calibration helper only runs on Windows.")
        return 1

    try:
        import pyautogui
        from pywinauto import Desktop
    except ImportError:
        print("Missing dependencies. Install requirements-worker.txt first.")
        return 1

    windows = Desktop(backend="uia").windows(title_re=".*LINE.*", visible_only=True)
    if not windows:
        print("Could not find a visible LINE window.")
        return 1

    window = windows[0]
    rect = window.rectangle()

    print("Move the mouse over the target inside LINE and press Ctrl+C when done.")
    print(f"LINE window: {window.window_text()}")
    print(f"Window top-left: ({rect.left}, {rect.top})")
    print("Tip: note the relative coordinates for the search box, message box, and attach button.")

    try:
        while True:
            x, y = pyautogui.position()
            rel_x = x - rect.left
            rel_y = y - rect.top
            print(f"\rAbsolute: ({x:4d}, {y:4d})  Relative to LINE: ({rel_x:4d}, {rel_y:4d})", end="", flush=True)
            time.sleep(0.1)
    except KeyboardInterrupt:
        print()
        print("Calibration ended.")
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
