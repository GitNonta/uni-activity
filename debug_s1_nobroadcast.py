#!/usr/bin/env python3
"""Debug why S1 monitor did not broadcast after restart."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[monitor log tail]")
print(run("tail -30 ~/monitor_server.log"))
print()

print("[proc alive]")
print(run("pgrep -f 'monitor_serve[r].py' || echo dead"))
print()

print("[threads state via py-spy? no - check cpu]")
print(run("cat /proc/$(pgrep -f 'monitor_serve[r].py' | head -1)/status | grep -E 'State|Threads'"))
print()

print("[ai listeners on S1]")
print(run("netstat -ltn 2>/dev/null | grep -E ':(8001|9999)' || cat /proc/net/tcp | awk '{print $2}' | grep -i ':1F41\\|:270F' | head"))
print()

ssh.close()