#!/usr/bin/env python3
"""Start monitor server in foreground briefly to see any startup error."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[which setsid/nohup]")
print(run("which setsid nohup python python3"))
print()

print("[foreground test: 6s run]")
print(run(
    "cd ~/uni-activity && timeout 6 python py/monitor_server.py 2>&1 | head -30",
    timeout=30,
))
print()

ssh.close()