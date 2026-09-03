#!/usr/bin/env python3
"""Bring up all S2 services via start-cluster.sh and verify."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Run start-cluster.sh (idempotent)")
print(run("bash ~/.termux/boot/start-cluster.sh && echo CLUSTER-SCRIPT-DONE", timeout=120))
print()

print("[2] Waiting 30s for workers...")
time.sleep(30)

print("[3] Listening ports")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE"))
print()

print("[4] Processes")
print(run("ps aux 2>/dev/null | grep -E 'artisan|watch_web|watch_queue|server.py' | grep -v grep | head -10 || echo NONE"))
print()

print("[5] HTTP checks")
for port in ("8000", "8001"):
    print("  :%s -> %s" % (port, run(
        "curl -s -o /dev/null -w 'HTTP %%{http_code} total=%%{time_total}s' --max-time 10 http://127.0.0.1:%s/ 2>&1" % port
    )))
print()

print("[6] Enable allow-external-apps for remote ADB recovery")
print(run(
    "mkdir -p ~/.termux && "
    "(grep -q '^allow-external-apps=true' ~/.termux/termux.properties 2>/dev/null "
    "|| echo 'allow-external-apps=true' >> ~/.termux/termux.properties) "
    "&& cat ~/.termux/termux.properties"
))
print()

print("[7] boot-cluster.log tail")
print(run("tail -15 ~/boot-cluster.log"))

ssh.close()