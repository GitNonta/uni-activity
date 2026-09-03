#!/usr/bin/env python3
"""Finalize S2 auto-setup: allow-external-apps + final verification."""

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


print("[1] Set allow-external-apps=true")
print(run(
    "touch ~/.termux/termux.properties && "
    "grep -q '^allow-external-apps[ ]*=[ ]*true' ~/.termux/termux.properties "
    "|| sed -i 's/^# allow-external-apps = true/allow-external-apps = true/' ~/.termux/termux.properties; "
    "grep -n 'allow-external-apps' ~/.termux/termux.properties"
))
print(run("termux-reload-settings 2>/dev/null; echo RELOADED"))
print()

print("[2] Waiting 75s for AI model load + worker bind...")
time.sleep(75)

print("[3] Listening ports")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE"))
print()

print("[4] HTTP checks")
for port in ("8000", "8001"):
    print("  :%s -> %s" % (port, run(
        "curl -s -o /dev/null -w 'HTTP %%{http_code} total=%%{time_total}s' --max-time 15 http://127.0.0.1:%s/health 2>&1" % port
    )))
    print("  :%s root -> %s" % (port, run(
        "curl -s -o /dev/null -w 'HTTP %%{http_code} total=%%{time_total}s' --max-time 15 http://127.0.0.1:%s/ 2>&1" % port
    )))

ssh.close()