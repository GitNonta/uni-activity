#!/usr/bin/env python3
"""Diagnose why monitor server did not stay up after restart."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[monitor_server.log tail]")
print(run("tail -30 ~/monitor_server.log"))
print()

print("[any monitor pid?]")
print(run("pgrep -af 'monitor_server' || echo none"))
print()

print("[port 9999 listening?]")
print(run("curl -s -o /dev/null -w '%{http_code}' -m 3 http://127.0.0.1:9999/"))
print()

ssh.close()