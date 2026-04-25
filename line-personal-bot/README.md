# LINE Personal Bot API

This service sends messages from a LINE personal account by delegating work to a
machine where LINE Desktop is already logged in.

There is no supported personal-account Messaging API. CHRLINE and linepy have
been removed from this project because current LINE QR/private API login flows
are unreliable and can be rejected by LINE. Production use should rely on
`LINE_SEND_MODE=automation`.

## Architecture

`PHP / cron` -> `api.py on VPS` -> `Windows worker` -> `LINE Desktop`

The LINE login stays in LINE Desktop on the Windows worker machine. The VPS does
not store a LINE auth token.

## VPS Setup

```bash
cd /var/www/vhosts/imzshop97.com/httpdocs/line-personal-bot
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
```

Configure `.env`:

```env
LINE_PERSONAL_ENABLED=true
LINE_SEND_MODE=automation
AUTOMATION_WORKER_URL=http://127.0.0.1:5001
AUTOMATION_WORKER_TOKEN=change-me
AUTOMATION_WORKER_TIMEOUT=60
AUTOMATION_VERIFY_SSL=false
```

Run directly for testing:

```bash
source venv/bin/activate
python api.py
```

Production should run `api.py` with supervisor or systemd.

## Windows Worker

Use a Windows machine with LINE Desktop open and already logged in.

```powershell
cd D:\key_lotto\line-personal-bot
py -3.10 -m venv venv-worker
.\venv-worker\Scripts\Activate.ps1
pip install -U pip setuptools wheel
pip install -r .\requirements-worker.txt
copy .env.worker.example .env
```

Set a shared token in the Windows `.env`:

```env
WORKER_API_TOKEN=change-me
```

Calibrate LINE Desktop coordinates:

```powershell
python .\line_automation_calibrate.py
```

Copy the reported coordinate values into the Windows `.env`, then start the
worker:

```powershell
python .\windows_line_worker.py
```

Check worker readiness:

```powershell
Invoke-RestMethod http://127.0.0.1:5001/status -Headers @{ "X-Worker-Token" = "change-me" }
```

## Tunnel

If the VPS cannot reach the Windows machine directly, keep a reverse SSH tunnel
open from Windows to the VPS:

```powershell
.\start_worker_tunnel.ps1 -ServerHost YOUR_VPS_IP -ServerUser root
```

With the tunnel running, the VPS can call the worker at:

```env
AUTOMATION_WORKER_URL=http://127.0.0.1:5001
```

## Status

```bash
curl http://127.0.0.1:5000/status
```

Ready state should show:

```json
{
  "send_mode": "automation",
  "transport_ready": true,
  "logged_in": true
}
```

## Important Notes

- The target LINE group must have a `group_name` because automation searches
  LINE Desktop by room name.
- Keep the Windows machine unlocked and LINE Desktop visible enough for UI
  automation.
- If LINE Desktop UI changes, run calibration again.
- Use `LINE_SEND_MODE=mock` only for integration testing without sending.
