#!/usr/bin/env python3
"""Start S2 web workers robustly and verify."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)


def run(cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[clean slate]")
print(run("pkill -f 'artisan serve' 2>/dev/null; sleep 1; echo cleaned"))
print()

for port in ("8000", "8002", "8003"):
    cmd = (
        "cd ~/uni-activity && nohup php artisan serve --host 0.0.0.0 --port "
        + port + " > serve-" + port + ".log 2>&1 < /dev/null & echo started-" + port
    )
    print(run(cmd))
    time.sleep(1)

print("[wait 8s]")
time.sleep(8)
print(run("pgrep -af 'artisan serve' | grep -v pgrep"))
print()

print("[verify x4 each]")
for port in ("8000", "8002", "8003"):
    print(run(f"for i in 1 2 3 4; do curl -s -o /dev/null -w '%{{http_code}} ' "
              f"-m 10 http://127.0.0.1:{port}/; done; echo"))
print()

print("[serve log tails]")
print(run("tail -3 ~/uni-activity/serve-8000.log"))
print()

ssh.close()