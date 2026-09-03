#!/usr/bin/env python3
"""Diagnose S2 AI service state (S1 reports node :8001 down)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[pgrep server.py]")
print(run("pgrep -af 'server.py' | head -5 || echo none"))
print()

print("[all listeners]")
print(run("ss -ltn | head -20"))
print()

print("[ai env vars]")
print(run("grep -E '^AI_' ~/uni-activity/.env"))
print()

print("[ai_service dir + recent log]")
print(run("ls -la ~/uni-activity/ai_service/ | head -15"))
print(run("tail -15 ~/uni-activity/ai_service/server.log 2>/dev/null || echo no-log"))
print()

print("[how is AI started? boot/supervision scripts]")
print(run("grep -rn 'server.py\\|uvicorn' ~/.termux/boot/ ~/uni-activity/start-cluster.sh ~/start_*.sh 2>/dev/null | head -10"))
print()

ssh.close()