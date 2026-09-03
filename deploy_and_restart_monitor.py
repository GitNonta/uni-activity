#!/usr/bin/env python3
"""Deploy rebuilt monitor-ui dist to S1, restart monitor server, verify."""

import os
import stat
import time

import paramiko

HOST, PORT, USER, PW = "192.168.1.222", 8022, "u0_a175", "A2345678"
LOCAL_DIST = r"D:\projects\uni-activity\monitor-ui\dist"
REMOTE_DIST = "/data/data/com.termux/files/home/uni-activity/monitor-ui/dist"

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect(HOST, PORT, USER, PW, timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


# ── 1. Upload dist via SFTP ──────────────────────────────────────────────────
sftp = ssh.open_sftp()


def ensure_dir(path):
    try:
        sftp.stat(path)
    except FileNotFoundError:
        parts = path.strip("/").split("/")
        cur = ""
        for part in parts:
            cur += "/" + part
            try:
                sftp.stat(cur)
            except FileNotFoundError:
                sftp.mkdir(cur)


def upload_dir(local, remote):
    ensure_dir(remote)
    for entry in os.listdir(local):
        lpath = os.path.join(local, entry)
        rpath = remote + "/" + entry
        if os.path.isdir(lpath):
            upload_dir(lpath, rpath)
        else:
            sftp.put(lpath, rpath)
            print("uploaded:", rpath)


upload_dir(LOCAL_DIST, REMOTE_DIST)
sftp.close()

# ── 2. Restart monitor server ────────────────────────────────────────────────
NL = chr(10)
print(NL + "[old pid]")
print(run("pgrep -f 'py/monitor_server.py'"))

print(run(
    "pkill -f 'py/monitor_server.py'; sleep 2; "
    "cd ~/uni-activity && setsid nohup python py/monitor_server.py "
    ">> ~/monitor_server.log 2>&1 < /dev/null & sleep 3; "
    "pgrep -f 'py/monitor_server.py'"
))

time.sleep(4)

# ── 3. Verify collectors output directly ─────────────────────────────────────
VERIFY = (
    "import sys" + chr(10)
    + "sys.path.insert(0, '/data/data/com.termux/files/home/uni-activity/py')" + chr(10)
    + "from monitor import collectors" + chr(10)
    + "import json" + chr(10)
    + "print(json.dumps(collectors.get_services(), indent=1))" + chr(10)
    + "print('redis:', collectors.get_redis_stats())" + chr(10)
    + "print('queue:', collectors.get_queue_stats())" + chr(10)
)
cmd = "python3 - << 'PYEOF'" + chr(10) + VERIFY + "PYEOF"
_, o, e = ssh.exec_command(cmd, timeout=60)
print(NL + "[collectors live output]")
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)

# ── 4. Verify HTTP serves new bundle ─────────────────────────────────────────
q = chr(34)
print("[index.html bundle ref]")
print(run("curl -s http://127.0.0.1:9999/ | grep -o " + q + "assets/index-[A-Za-z0-9._-]*" + q))
print("[new asset status]")
print(run("curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:9999/assets/index-D44OyvcX.js"))
print()

ssh.close()