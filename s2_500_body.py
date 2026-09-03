#!/usr/bin/env python3
"""Get actual 500 response body from S2 worker."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[500 response body]")
print(run("curl -s -m 10 http://127.0.0.1:8000/ | head -30"))
print()

print("[serve-8000.log tail]")
print(run("tail -15 ~/uni-activity/serve-8000.log 2>/dev/null"))
print()

print("[laravel.log tail (any type)]")
print(run("tail -6 ~/uni-activity/storage/logs/laravel.log | cut -c1-250"))
print()

print("[APP_KEY set?]")
print(run("grep -c '^APP_KEY=base64' ~/uni-activity/.env"))
print()

ssh.close()