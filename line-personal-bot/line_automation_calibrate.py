"""Interactive helper to capture LINE desktop automation coordinates."""

from __future__ import annotations

import platform


TARGETS = [
    (
        "SEARCH",
        "Move the mouse to the center of the search box on the left, then press Enter.",
        ("WORKER_SEARCH_BOX_X", "WORKER_SEARCH_BOX_Y"),
    ),
    (
        "MESSAGE",
        "Move the mouse to the center of the message input box at the bottom, then press Enter.",
        ("WORKER_MESSAGE_BOX_X", "WORKER_MESSAGE_BOX_Y"),
    ),
    (
        "ATTACH",
        "Move the mouse to the center of the paperclip/attach button, then press Enter.",
        ("WORKER_ATTACH_BUTTON_X", "WORKER_ATTACH_BUTTON_Y"),
    ),
]


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

    print(f"LINE window: {window.window_text()}")
    print(f"Window top-left: ({rect.left}, {rect.top})")
    print()
    print("Keep the LINE window in the same size/position that you will actually use.")
    print("This tool will capture 3 points and print ready-to-paste .env lines.")
    print()

    captured: list[tuple[str, int, int, tuple[str, str]]] = []
    for label, instruction, env_keys in TARGETS:
        print(f"[{label}] {instruction}")
        input("Press Enter when the mouse is in place...")

        x, y = pyautogui.position()
        rel_x = x - rect.left
        rel_y = y - rect.top

        print(f"Absolute: ({x}, {y})")
        print(f"Relative to LINE: ({rel_x}, {rel_y})")

        if rel_x < 0 or rel_y < 0:
            print("This point is outside the LINE window. Run the helper again and retry.")
            return 1

        captured.append((label, rel_x, rel_y, env_keys))
        print()

    print("Calibration complete. Paste these lines into your Windows .env:")
    for _, rel_x, rel_y, env_keys in captured:
        print(f"{env_keys[0]}={rel_x}")
        print(f"{env_keys[1]}={rel_y}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
