#!/usr/bin/env python3
"""Verify live stats flow into the shared cache the WebSocket broadcasts."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[wait 25s for stats collector cycle]")
time.sleep(25)

VERIFY = (
    "import sys" + chr(10)
    + "sys.path.insert(0, '/data/data/com.termux/files/home/uni-activity/py')" + chr(10)
    + "from monitor import config as cfg" + chr(10)
    + "import json" + chr(10)
    + "with cfg._stats_lock:" + chr(10)
    + "    snap = dict(cfg._stats_cache)" + chr(10)
    + "print('cache keys:', sorted(snap.keys()))" + chr(10)
    + "st = snap.get('stats', {})" + chr(10)
    + "print('services:', json.dumps(st.get('services', {}), indent=1))" + chr(10)
    + "am = st.get('advanced_metrics', {})" + chr(10)
    + "print('redis:', am.get('redis'))" + chr(10)
    + "print('queue:', am.get('queue'))" + chr(10)
)
cmd = "python3 - << 'PYEOF'" + chr(10) + VERIFY + "PYEOF"
_, o, e = ssh.exec_command(cmd, timeout=60)
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)

print("[server still alive?]")
print(run("pgrep -f 'monitor_serve[r].py' && curl -s -o /dev/null -w '%{http_code}' -m 3 http://127.0.0.1:9999/"))
print()

ssh.close()