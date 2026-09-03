#!/usr/bin/env python3
"""Check why S2 monitor server failed to start."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[monitor_server.log tail]")
print(run("tail -25 ~/monitor_server.log 2>/dev/null || echo no-log"))
print()

print("[foreground test 6s]")
print(run(
    "cd ~/uni-activity && timeout 6 python -u py/monitor_server.py 2>&1 | head -30",
    timeout=30,
))
print()

print("[py/monitor dir + config PORT]")
print(run(
    "ls ~/uni-activity/py/ | head; "
    "grep -n 'PORT' ~/uni-activity/py/monitor/config.py | head -5"
))
print()

ssh.close()