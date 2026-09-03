#!/usr/bin/env python3
"""Check if the S1 watchdog revived S2."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Watchdog log")
print(run("tail -15 ~/s2-watchdog.log"))
print()

print("[2] S2 web from S1")
print(run("curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' --max-time 10 http://192.168.1.140:8000/health"))
print()

print("[3] S2 AI from S1")
print(run("curl -s --max-time 10 http://192.168.1.140:8001/health"))

ssh.close()