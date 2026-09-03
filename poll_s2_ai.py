#!/usr/bin/env python3
"""Poll S2 AI service until healthy (max ~3 min)."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


for i in range(9):
    result = run(
        "curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' "
        "--max-time 10 http://127.0.0.1:8001/health 2>&1"
    )
    print("[attempt %d] AI :8001 -> %s" % (i + 1, result))
    if "200" in result:
        break
    time.sleep(20)

print()
print("[server.log mtime + tail]")
print(run("ls -la ~/uni-activity/ai_service/server.log"))
print(run("tail -5 ~/uni-activity/ai_service/server.log"))
print()

print("[FINAL: all ports]")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE"))

ssh.close()