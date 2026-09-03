#!/usr/bin/env python3
"""From S1: measure latency to S2 AI node :8001/health."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[S1 env AI config]")
print(run("grep -E '^AI_' ~/uni-activity/.env"))
print()

print("[health x3 with timing]")
for i in range(3):
    print(run(
        "curl -s -o /dev/null -w 'try%d: http=%{http_code} total=%{time_total}s' "
        "-m 10 http://192.168.1.140:8001/health; echo"
    ))
print()

print("[tcp connect timing]")
print(run("curl -s -o /dev/null -w 'connect=%{time_connect}s ttfb=%{time_starttransfer}s' -m 10 http://192.168.1.140:8001/health; echo"))
print()

ssh.close()