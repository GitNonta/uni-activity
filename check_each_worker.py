#!/usr/bin/env python3
"""Probe each web worker directly to find the broken one."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[worker processes]")
print(run("pgrep -af 'artisan serve' | grep -v pgrep"))
print()

for port in ("8000", "8002", "8003"):
    print(f"[direct :{port} x4]")
    print(run(f"for i in 1 2 3 4; do curl -s -o /dev/null -w '%{{http_code}} ' "
              f"-m 10 http://127.0.0.1:{port}/; done; echo"))
print()

print("[nginx upstream block]")
print(run("grep -rn -A6 'upstream' ~/nginx/conf/nginx.conf 2>/dev/null | head -20"))
print()

ssh.close()