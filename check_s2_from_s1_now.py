#!/usr/bin/env python3
"""Check S2 reachability from S1 right now."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd):
    _, o, _ = ssh.exec_command(cmd, timeout=25)
    return o.read().decode(errors="ignore").strip() or "(no output)"


print("[ping S2]")
print(run("ping -c 3 -W 2 192.168.1.140 2>&1 | tail -2"))
print()
print("[ARP entry]")
print(run("ip neigh show | grep '192.168.1.140'"))
print()
print("[HTTP to S2 web]")
print(run("curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' --max-time 8 http://192.168.1.140:8000/health"))
print()
print("[AI health via S1 -> S2]")
print(run("curl -s --max-time 8 http://192.168.1.140:8001/health"))

ssh.close()