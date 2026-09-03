#!/usr/bin/env python3
"""Check S2 (192.168.1.140) reachability from S1 (192.168.1.222)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)

CHECKS = [
    ("ping S2", "ping -c 2 -W 2 192.168.1.140 2>&1 | tail -3"),
    ("SSH port 8022", "timeout 4 sh -c 'echo > /dev/tcp/192.168.1.140/8022' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
    ("Web port 8000", "timeout 4 sh -c 'echo > /dev/tcp/192.168.1.140/8000' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
    ("AI port 8001", "timeout 4 sh -c 'echo > /dev/tcp/192.168.1.140/8001' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
    ("HTTP timing to S2 web", "curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' --max-time 6 http://192.168.1.140:8000/ 2>&1"),
    ("ARP/neigh table", "ip neigh show 2>/dev/null | grep '192.168.1.' || echo EMPTY"),
    ("S1 wlan0 IP", "ip addr show wlan0 2>/dev/null | grep inet"),
]

for label, cmd in CHECKS:
    _, o, _ = ssh.exec_command(cmd, timeout=20)
    out = o.read().decode("utf-8", errors="ignore").strip()
    print("[%s]" % label)
    print("  %s" % out)
    print()

ssh.close()