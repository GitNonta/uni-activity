#!/usr/bin/env python3
"""Find the correct Python env / venv for the S2 AI service."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] How start_dual_node.sh launches AI service")
print(run("grep -n -A5 -i 'ai' ~/uni-activity/scripts/start_dual_node.sh | head -40"))
print()
print("[2] Look for venvs")
print(run("ls -d ~/uni-activity/ai_service/*venv* ~/uni-activity/*venv* ~/venv* 2>/dev/null || echo NO-VENV-DIR"))
print()
print("[3] pip list (system python)")
print(run("python -m pip list 2>/dev/null | grep -iE 'fastapi|uvicorn|onnx|insightface|opencv|numpy' || echo NONE-FOUND"))
print()
print("[4] ai_service dir contents")
print(run("ls ~/uni-activity/ai_service/ | head -20"))

ssh.close()