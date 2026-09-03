#!/usr/bin/env python3
"""Probe valkey auth precisely via remote python (no shell quoting traps)."""

import paramiko

PROBE = r"""
import pathlib, subprocess

home = pathlib.Path.home()
env = {}
for line in (home / "uni-activity" / ".env").read_text().splitlines():
    if "=" in line and not line.strip().startswith("#"):
        k, v = line.split("=", 1)
        env[k.strip()] = v.strip().strip('"').strip("'")

pw = env.get("REDIS_PASSWORD", "")
print("pw_len:", len(pw))

for port in (6379, 6380):
    r = subprocess.run(
        ["valkey-cli", "-p", str(port), "-a", pw, "--no-auth-warning", "ping"],
        capture_output=True, text=True, timeout=5,
    )
    print(f"port {port} ping:", (r.stdout or r.stderr).strip())

# queue keys on 6380
r = subprocess.run(
    ["valkey-cli", "-p", "6380", "-a", pw, "--no-auth-warning",
     "keys", "queues:*"],
    capture_output=True, text=True, timeout=5,
)
print("queue keys:", (r.stdout or r.stderr).strip()[:300])

# llen default queue
r = subprocess.run(
    ["valkey-cli", "-p", "6380", "-a", pw, "--no-auth-warning",
     "llen", "queues:default"],
    capture_output=True, text=True, timeout=5,
)
print("llen queues:default:", (r.stdout or r.stderr).strip())
"""


def connect(host, user, pw_ssh):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, pw_ssh, timeout=20)
    return ssh


ssh = connect("192.168.1.222", "u0_a175", "A2345678")
NL = chr(10)
cmd = "python3 - << 'PYEOF'" + NL + PROBE + NL + "PYEOF"
_, o, e = ssh.exec_command(cmd, timeout=30)
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)
ssh.close()