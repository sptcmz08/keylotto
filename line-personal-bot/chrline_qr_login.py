"""Backward-friendly CHRLINE QR login entrypoint."""

from linepy_qr_login import _login_with_chrline


if __name__ == "__main__":
    raise SystemExit(_login_with_chrline())
