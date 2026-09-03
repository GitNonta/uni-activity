#!/usr/bin/env python3
"""Detailed service diagnostics on S2 (192.168.1.140)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)

CHECKS = [
    ("listening ports", "netstat -tlpn 2>/dev/null | grep LISTEN | head -15"),
    ("artisan serve processes", "ps aux 2>/dev/null | grep -E 'artisan|queue' | grep -v grep || echo NONE"),
    ("php/python procs", "ps aux 2>/dev/null | grep -E 'php|python' | grep -v grep | head -10 || echo NONE"),
    ("project dir exists", "ls ~/uni-activity/artisan 2>/dev/null && echo OK || echo MISSING"),
    ("recent watchdog log", "tail -5 ~/uni-activity/storage/logs/web-watchdog.log 2>/dev/null || echo NO-LOG"),
    ("ping S1", "ping -c 2 -W 2 192.168.1.222 2>&1 | tail -2"),
    ("tcp S1:5432 retry", "timeout 5 sh -c 'echo > /dev/tcp/192.168.1.222/5432' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
    ("tcp S1:6379 retry", "timeout 5 sh -c 'echo > /dev/tcp/192.168.1.222/6379' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
]

for label, cmd in CHECKS:
    _, o, _ = ssh.exec_command(cmd, timeout=25)
    out = o.read().decode("utf-8", errors="ignore").strip()
    print("[%s]" % label)
    print("  %s" % out)
    print()

ssh.close()