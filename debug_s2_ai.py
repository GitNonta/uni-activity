#!/usr/bin/env python3
"""Debug why S2 AI service (uvicorn :8001) is not running."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Find AI service logs")
print(run("ls -lt ~/uni-activity/storage/logs/ 2>/dev/null | head -10"))
print()
print("[2] ai-service log tail")
print(run("tail -30 ~/uni-activity/storage/logs/ai-service.log 2>/dev/null || echo NO-LOG"))
print()
print("[3] Try launching uvicorn manually to capture error")
manual = (
    "cd ~/uni-activity/ai_service && "
    "timeout 25 python -m uvicorn server:app --host 0.0.0.0 --port 8001 2>&1 | tail -20"
)
print(run(manual))

ssh.close()