"""One-off deploy: upload Telegram anti-spam fix files and restart monitor."""
import os
import sys
import time
import importlib.util

_here = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("deploy_cf_base", os.path.join(_here, "deploy_cf.py"))

# Reuse credential loading from deploy_cf.py without executing its deploy steps:
sys.path.insert(0, _here)
import paramiko

def _load_env_file(path):
    env = {}
    try:
        with open(path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, _, val = line.partition("=")
                env[key.strip()] = val.strip().strip("\"'")
    except OSError:
        pass
    return env

_env = _load_env_file(os.path.join(_here, ".env"))
HOST = os.environ.get("DEPLOY_SSH_HOST",     _env.get("DEPLOY_SSH_HOST", "192.168.1.222"))
PORT = int(os.environ.get("DEPLOY_SSH_PORT", _env.get("DEPLOY_SSH_PORT", "8022")))
USER = os.environ.get("DEPLOY_SSH_USER",     _env.get("DEPLOY_SSH_USER", "u0_a175"))
PASSWORD = os.environ.get("DEPLOY_SSH_PASSWORD", _env.get("DEPLOY_SSH_PASSWORD", ""))
APP = "/data/data/com.termux/files/home/uni-activity"

if not PASSWORD:
    print("ℹ️  DEPLOY_SSH_PASSWORD not set — falling back to SSH key auth")

FILES = [
    "py/monitor_server.py",
    "py/monitor/config.py",
    "py/monitor/alerts.py",
    "py/monitor/telegram.py",
    "py/monitor/threads.py",
    "py/monitor/tg_commands.py",
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    if PASSWORD:
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    else:
        # Fallback: SSH key auth (agent/keys in ~/.ssh)
        client.connect(HOST, port=PORT, username=USER, timeout=10,
                       look_for_keys=True, allow_agent=False)
    print(f"✅ Connected to {USER}@{HOST}:{PORT}")
except Exception as e:
    print(f"❌ Connection failed: {e}")
    sys.exit(1)

def run(client, cmd, timeout=30):
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode(errors="replace").strip()
    err = stderr.read().decode(errors="replace").strip()
    return out, err

# 1. Upload changed files
sftp = client.open_sftp()
for f in FILES:
    local = os.path.join(_here, *f.split("/"))
    remote = f"{APP}/{f}"
    sftp.put(local, remote)
    print(f"  ⬆  {f}")
sftp.close()
print("✅ Upload complete")

# 2. Syntax check remotely before restarting (rollback-safe)
for f in FILES:
    out, err = run(client, f"cd {APP} && python -m py_compile {f} && echo OK_{f}")
    if not out.startswith("OK_"):
        print(f"❌ Remote syntax check failed for {f}: {err}")
        client.close()
        sys.exit(1)
print("✅ Remote syntax check passed")

# 3. Restart monitor (match any python invocation incl. 'python -u')
run(client, "pkill -f '[m]onitor_server.py' 2>/dev/null; sleep 2")
out, err = run(client,
    f"cd {APP} && nohup python -u py/monitor_server.py > storage/logs/monitor.log 2>&1 & echo $!")
print(f"✅ Restarted, PID: {out}")

time.sleep(5)
pid, _ = run(client, "pgrep -f '[m]onitor_server.py'")
log, _ = run(client, f"tail -5 {APP}/storage/logs/monitor.log 2>/dev/null")
port, _ = run(client, "ss -tlnp 2>/dev/null | grep 9999")
print(f"Process: {'✅ Running' if pid else '❌ Not running'} (PID: {pid})")
print(f"Port 9999: {'✅ Listening' if port else '❌ Not listening'}")
print(f"Log:\n{log}")

client.close()
print("\n✅ Deploy Telegram anti-spam fix complete!")
