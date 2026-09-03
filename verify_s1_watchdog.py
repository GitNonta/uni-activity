#!/usr/bin/env python3
"""Verify the S1 watchdog is truly running and watching S2."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] watch_s2.sh processes (ps)")
print(run("ps aux 2>/dev/null | grep 'watch_s2' | grep -v grep || echo NOT-RUNNING"))
print()

print("[2] Start it for real if needed")
print(run(
    "ps aux 2>/dev/null | grep 'bash.*watch_s2[.]sh' | grep -v grep >/dev/null "
    "&& echo CONFIRMED-RUNNING "
    "|| (setsid nohup bash ~/watch_s2.sh > /dev/null 2>&1 < /dev/null & sleep 2; "
    "ps aux 2>/dev/null | grep 'bash.*watch_s2[.]sh' | grep -v grep && echo NOW-STARTED)"
))
print()

print("[3] Watchdog log")
print(run("tail -10 ~/s2-watchdog.log 2>/dev/null || echo NO-LOG-YET"))
print()

print("[4] adb devices from S1")
print(run("adb devices 2>/dev/null"))

ssh.close()