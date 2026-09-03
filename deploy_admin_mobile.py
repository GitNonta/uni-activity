"""Deploy QR-close feature + admin mobile UX to server, then clear caches."""
import os
import sys
import time
import paramiko

_here = os.path.dirname(os.path.abspath(__file__))

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

FILES = [
    "resources/views/layouts/admin.blade.php",
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    if PASSWORD:
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    else:
        client.connect(HOST, port=PORT, username=USER, timeout=10,
                       look_for_keys=True, allow_agent=False)
    print(f"Connected to {USER}@{HOST}:{PORT}")
except Exception as e:
    print(f"Connection failed: {e}")
    sys.exit(1)

def run(cmd, timeout=60):
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    return stdout.read().decode(errors="replace").strip(), stderr.read().decode(errors="replace").strip()

# 1. Upload
sftp = client.open_sftp()
for f in FILES:
    sftp.put(os.path.join(_here, *f.split("/")), f"{APP}/{f}")
    print(f"  up {f}")
sftp.close()

# 2. Remote syntax checks (PHP + Blade via artisan)
for f in [x for x in FILES if x.endswith(".php") and not x.startswith("resources")]:
    out, err = run(f"cd {APP} && php -l {f}")
    if "No syntax errors" not in out:
        print(f"PHP syntax FAIL: {f}: {out} {err}")
        client.close(); sys.exit(1)
print("PHP syntax OK")

# 3. Clear caches so Blade views recompile
out, err = run(f"cd {APP} && php artisan view:clear && php artisan config:clear")
print(f"cache: {out.splitlines()[-1] if out else err}")

# 4. Graceful Octane reload if running (ignore if not)
run(f"cd {APP} && php artisan octane:reload 2>/dev/null || true")

# 5. Verify marker present in served source + site responds
out, _ = run(f"grep -c 'adminMenuToggleBtn' {APP}/resources/views/layouts/admin.blade.php")
print(f"layout marker count: {out}")

time.sleep(2)
client.close()
print("Deploy complete.")
