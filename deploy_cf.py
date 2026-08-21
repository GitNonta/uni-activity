import os
import sys
import time
import paramiko


def _load_env_file(path: str) -> dict:
    """Read KEY=VALUE pairs from a .env-style file (no external deps)."""
    env = {}
    try:
        with open(path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, _, val = line.partition("=")
                key = key.strip()
                val = val.strip().strip('"\'')
                if key:
                    env[key] = val
    except OSError:
        pass
    return env


# ── Credentials — NEVER hardcode. Read from env vars first, then .env ────────
_env = _load_env_file(os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env"))

HOST     = os.environ.get("DEPLOY_SSH_HOST", _env.get("DEPLOY_SSH_HOST", "192.168.1.222"))
PORT     = int(os.environ.get("DEPLOY_SSH_PORT", _env.get("DEPLOY_SSH_PORT", "8022")))
USER     = os.environ.get("DEPLOY_SSH_USER", _env.get("DEPLOY_SSH_USER", "u0_a175"))
PASSWORD = os.environ.get("DEPLOY_SSH_PASSWORD", _env.get("DEPLOY_SSH_PASSWORD", ""))
TG_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", _env.get("TELEGRAM_BOT_TOKEN", ""))
TG_CHAT  = os.environ.get("TELEGRAM_CHAT_ID",  _env.get("TELEGRAM_CHAT_ID", ""))

APP = "/data/data/com.termux/files/home/uni-activity"

if not PASSWORD:
    print("❌ Missing DEPLOY_SSH_PASSWORD — ตั้งค่าใน .env หรือ env var ก่อนรัน")
    sys.exit(1)
if not TG_TOKEN or not TG_CHAT:
    print("⚠️  Missing TELEGRAM_BOT_TOKEN/TELEGRAM_CHAT_ID — จะไม่ส่งข้อความแจ้งเตือน")


def run(client, cmd, timeout=30):
    _, stdout, _ = client.exec_command(cmd, timeout=timeout)
    return stdout.read().decode(errors="replace").strip()


client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

print("⏳ Upload monitor_server.py...")
sftp = client.open_sftp()
sftp.put(os.path.join(os.path.dirname(os.path.abspath(__file__)), "py", "monitor_server.py"),
         f"{APP}/py/monitor_server.py")
sftp.close()
print("✅ Upload สำเร็จ")

print("⏳ Restart monitor_server.py...")
run(client, "pkill -f 'python py/monitor_server.py' 2>/dev/null; sleep 2")
cmd_env = ""
if TG_TOKEN:
    cmd_env = f"TELEGRAM_BOT_TOKEN={TG_TOKEN} TELEGRAM_CHAT_ID={TG_CHAT} "
out = run(client,
    f"cd {APP} && {cmd_env}"
    f"nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 & echo $!")
print(f"✅ PID: {out}")
time.sleep(5)

pid = run(client, "pgrep -f 'python py/monitor_server.py'")
log = run(client, f"tail -3 {APP}/storage/logs/monitor.log 2>/dev/null")
print(f"Process: {'✅ Running' if pid else '❌ Not running'} (PID: {pid})")
print(f"Log: {log}")

# ทดสอบ /tunnel command โดยตรง
if TG_TOKEN and TG_CHAT:
    print("\n⏳ ทดสอบ Cloudflare status...")
    test = run(client,
        f"cd {APP} && python3 -c \"\""
        f"import subprocess, json, urllib.request;"
        f"procs = subprocess.run(['pgrep','-a','cloudflared'], capture_output=True, text=True).stdout.strip();"
        f"cf_count = len([l for l in procs.splitlines() if l]);"
        f"payload = json.dumps({{'chat_id':'{TG_CHAT}','text':'🌐 Cloudflare Commands พร้อมแล้ว!\\\\n━━━━━━━━━━━━━━━━━━━━\\\\nProcesses: ' + str(cf_count) + ' running\\\\n\\\\nลองพิมพ์ /tunnel ได้เลยครับ','parse_mode':'HTML'}}).encode();"
        f"req = urllib.request.Request('https://api.telegram.org/bot{TG_TOKEN}/sendMessage', data=payload, headers={{'Content-Type':'application/json'}});"
        f"r = urllib.request.urlopen(req, timeout=10);"
        f"print('OK' if r.status==200 else 'FAIL')"
        f"\" 2>&1",
        timeout=15)
    print(f"  → {test}")
else:
    print("\n⏳ ข้ามการทดสอบ Telegram (ไม่มี TOKEN/CHAT_ID)")

client.close()
print("\n✅ Deploy Cloudflare commands สำเร็จ!")
