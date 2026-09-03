#!/usr/bin/env python3
"""Check S2 AI service history log and python environment state."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] ai_service/server.log tail")
print(run("tail -25 ~/uni-activity/ai_service/server.log 2>/dev/null || echo NO-LOG"))
print()
print("[2] python & pip versions")
print(run("python --version; python -m pip --version 2>&1"))
print()
print("[3] uvicorn binary anywhere?")
print(run("which uvicorn; ls ~/.local/bin/ 2>/dev/null | head; command -v uvicorn || echo NO-UVICORN-BINARY"))
print()
print("[4] full pip list count")
print(run("python -m pip list 2>/dev/null | wc -l"))

ssh.close()