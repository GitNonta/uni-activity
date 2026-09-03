#!/usr/bin/env python3
"""Probe S2 web workers (in nginx upstream) — suspected source of 500s."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


for port in ("8000", "8002", "8003"):
    print(f"[S2 direct :{port} x3]")
    print(run(f"for i in 1 2 3; do curl -s -o /dev/null -w '%{{http_code}} ' "
              f"-m 10 http://192.168.1.140:{port}/; done; echo"))
print()

print("[S2 worker processes]")
print(run("ssh -p 8022 -o StrictHostKeyChecking=no u0_a135@192.168.1.140 "
          + chr(34) + "pgrep -af 'artisan serve' | grep -v pgrep" + chr(34)
          + " 2>/dev/null || echo no-ssh"))
print()

print("[nginx error log tail]")
print(run("tail -6 /data/data/com.termux/files/usr/var/log/sv/nginx/current 2>/dev/null | cut -c1-200"))
print()

ssh.close()