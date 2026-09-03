#!/usr/bin/env python3
"""Verify S2 AI service startup and final readiness."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[start-node2.log tail]")
print(run("tail -20 ~/uni-activity/storage/logs/start-node2.log 2>/dev/null"))
print()

for attempt in range(4):
    result = run("curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' --max-time 15 http://127.0.0.1:8001/")
    print("[AI :8001 attempt %d] %s" % (attempt + 1, result))
    if "000" not in result:
        break
    time.sleep(15)

print()
print("[uvicorn/AI process]")
print(run("ps aux 2>/dev/null | grep -E 'uvicorn|server:app' | grep -v grep || echo NOT-RUNNING"))
print()
print("[all listening app ports]")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE"))
print()
print("[queue worker]")
print(run("ps aux 2>/dev/null | grep 'queue:work' | grep -v grep || echo NOT-RUNNING"))

ssh.close()