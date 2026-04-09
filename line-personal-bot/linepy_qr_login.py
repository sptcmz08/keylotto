"""Interactive helper to obtain and store a LINE Personal auth token."""

import traceback
from typing import Any, Dict

from config import Config
from line_client_runtime import line_client


def _chrline_kwargs() -> Dict[str, Any]:
    kwargs: Dict[str, Any] = {
        "device": Config.CHRLINE_DEVICE,
        "savePath": str(Config.CHRLINE_SAVE_PATH),
        "debug": Config.CHRLINE_DEBUG,
        "noLogin": True,
    }
    if Config.CHRLINE_VERSION:
        kwargs["version"] = Config.CHRLINE_VERSION
    if Config.CHRLINE_OS_NAME:
        kwargs["os_name"] = Config.CHRLINE_OS_NAME
    if Config.CHRLINE_OS_VERSION:
        kwargs["os_version"] = Config.CHRLINE_OS_VERSION
    return kwargs


def _print_qr_steps(generator) -> None:
    for step in generator:
        print(step)


def _login_with_chrline() -> int:
    try:
        from CHRLINE import CHRLINE
    except ImportError:
        print("CHRLINE is not installed. Run `pip install -r requirements.txt` first.")
        return 1

    print("Starting LINE QR login with CHRLINE...")
    print(f"Device: {Config.CHRLINE_DEVICE}")
    print(f"QR mode: {Config.CHRLINE_QR_MODE}")
    print(f"Save path: {Config.CHRLINE_SAVE_PATH}")

    try:
        client = CHRLINE(**_chrline_kwargs())
        qr_mode = Config.CHRLINE_QR_MODE
        if qr_mode == "secure":
            _print_qr_steps(client.requestSQR3())
        elif qr_mode == "legacy":
            _print_qr_steps(client.requestSQR())
        elif qr_mode == "v2":
            _print_qr_steps(client.requestSQR2())
        else:
            try:
                _print_qr_steps(client.requestSQR3())
            except Exception as secure_exc:
                print(f"Secure QR login failed, fallback to token-v3 QR: {secure_exc}")
                _print_qr_steps(client.requestSQR2())
    except Exception as exc:
        print(f"CHRLINE login failed: {exc!r}")
        print("Traceback:")
        print(traceback.format_exc())
        return 1

    auth_token = getattr(client, "authToken", "") or ""
    if not auth_token:
        print("Login completed but no auth token was returned.")
        return 1

    result = line_client.set_session(auth_token, save=True)
    if not result["success"]:
        print(f"Failed to save session: {result.get('error', 'unknown error')}")
        return 1

    print("Login successful.")
    print(f"Session saved to: {line_client.session_file}")
    print(f"Auth token: {auth_token}")
    return 0


def _login_with_linepy() -> int:
    try:
        from linepy import LINE
    except ImportError:
        print("linepy is not installed. Run `pip install -r requirements.txt` first.")
        return 1

    print("Starting LINE QR login with linepy...")
    print("Scan the QR code or open the login URL in your LINE app.")
    print(f"System name: {Config.LINEPY_SYSTEM_NAME}")
    print(f"App name: {Config.LINEPY_APP_NAME or '(linepy default)'}")

    try:
        kwargs = {"showQr": True, "systemName": Config.LINEPY_SYSTEM_NAME}
        if Config.LINEPY_APP_NAME:
            kwargs["appName"] = Config.LINEPY_APP_NAME
        client = LINE(**kwargs)
    except Exception as exc:
        print(f"linepy login failed: {exc!r}")
        print("Traceback:")
        print(traceback.format_exc())
        print("Hint: linepy uses an older login flow and often fails with current LINE endpoints.")
        return 1

    auth_token = getattr(client, "authToken", "")
    if not auth_token:
        print("Login completed but no auth token was returned.")
        return 1

    result = line_client.set_session(auth_token, save=True)
    if not result["success"]:
        print(f"Failed to save session: {result.get('error', 'unknown error')}")
        return 1

    print("Login successful.")
    print(f"Session saved to: {line_client.session_file}")
    print(f"Auth token: {auth_token}")
    return 0


def main() -> int:
    if Config.LINE_SEND_MODE == "linepy":
        return _login_with_linepy()
    return _login_with_chrline()


if __name__ == "__main__":
    raise SystemExit(main())
