#!/usr/bin/env python3
"""Check S2 AI service (proot ubuntu) startup log."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] AI server.log tail (from proot container)")
print(run(
    "proot-distro login ubuntu -- bash -c "
    "'tail -30 /data/data/com.termux/files/home/uni-activity/ai_service/server.log' 2>&1"
))
print()

print("[2] Is python still running?")
print(run("ps aux 2>/dev/null | grep 'server.py' | grep -v grep | head -3 || echo NOT-RUNNING"))
print()

print("[3] venv python exists?")
print(run(
    "proot-distro login ubuntu -- bash -c "
    "'ls -la /root/ai_project/venv/bin/python && /root/ai_project/venv/bin/python --version' 2>&1"
))

ssh.close()