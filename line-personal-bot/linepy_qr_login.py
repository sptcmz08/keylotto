"""Interactive helper to obtain and store a LINE Personal auth token with linepy."""

from config import Config
from line_client_runtime import line_client


def main() -> int:
    try:
        from linepy import LINE
    except ImportError:
        print("linepy is not installed. Run `pip install -r requirements.txt` first.")
        return 1

    print("Starting LINE QR login...")
    print("Scan the QR code or open the login URL in your LINE app.")

    try:
        kwargs = {"showQr": True, "systemName": Config.LINEPY_SYSTEM_NAME}
        if Config.LINEPY_APP_NAME:
            kwargs["appName"] = Config.LINEPY_APP_NAME
        client = LINE(**kwargs)
    except Exception as exc:
        print(f"Login failed: {exc}")
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


if __name__ == "__main__":
    raise SystemExit(main())
