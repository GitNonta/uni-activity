#!/usr/bin/env python3
"""Verify S2 auto-setup results."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Listening ports")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE"))
print()

print("[2] Processes")
print(run("ps aux 2>/dev/null | grep -E 'artisan|watch_web|watch_queue|server.py' | grep -v grep | head -12 || echo NONE"))
print()

print("[3] HTTP checks")
for port in ("8000", "8001"):
    print("  :%s -> %s" % (port, run(
        "curl -s -o /dev/null -w 'HTTP %%{http_code} total=%%{time_total}s' --max-time 10 http://127.0.0.1:%s/ 2>&1" % port
    )))
print()

print("[4] termux.properties")
print(run("cat ~/.termux/termux.properties 2>/dev/null || echo MISSING"))
print()

print("[5] boot-cluster.log tail")
print(run("tail -20 ~/boot-cluster.log"))

ssh.close()