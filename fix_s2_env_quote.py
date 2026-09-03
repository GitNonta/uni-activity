#!/usr/bin/env python3
"""Quote MONITOR_SERVICES in S2 .env (spaces broke dotenv -> 500s), restart workers, verify."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)


def run(cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


Q = chr(34)
value = ("Web Workers (artisan serve),Datastore (Valkey),Queue Store (Valkey),"
         "PostgreSQL Database,Queue Worker,AI Biometrics Face Service,SSH / SFTP Server")

print("[rewrite MONITOR_SERVICES quoted]")
cmd = (
    "sed -i '/^MONITOR_SERVICES=/d' ~/uni-activity/.env && "
    "printf '%s" + chr(92) + "n' 'MONITOR_SERVICES=" + Q + value + Q + "' >> ~/uni-activity/.env && "
    "grep '^MONITOR_SERVICES=' ~/uni-activity/.env"
)
print(run(cmd))
print()

print("[clear config cache + restart workers]")
print(run("cd ~/uni-activity && php artisan config:clear 2>&1 | tail -1"))
print(run(
    "pkill -f 'artisan serve' 2>/dev/null; sleep 2; "
    "cd ~/uni-activity && "
    "setsid nohup php artisan serve --host 0.0.0.0 --port 8000 > serve-8000.log 2>&1 < /dev/null & "
    "setsid nohup php artisan serve --host 0.0.0.0 --port 8002 > serve-8002.log 2>&1 < /dev/null & "
    "setsid nohup php artisan serve --host 0.0.0.0 --port 8003 > serve-8003.log 2>&1 < /dev/null & "
    "sleep 6; pgrep -fc 'artisan serve'"
))
print()

print("[verify workers x4 each]")
for port in ("8000", "8002", "8003"):
    print(run(f"for i in 1 2 3 4; do curl -s -o /dev/null -w '%{{http_code}} ' "
              f"-m 10 http://127.0.0.1:{port}/; done; echo"))
print()

ssh.close()