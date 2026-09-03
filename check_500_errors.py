#!/usr/bin/env python3
"""Find cause of intermittent HTTP 500 on / via laravel.log."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[laravel.log size + last error]")
print(run("ls -la ~/uni-activity/storage/logs/laravel.log"))
print(run("grep -n 'ERROR' ~/uni-activity/storage/logs/laravel.log | tail -3"))
print()

print("[last exception block]")
print(run("tail -40 ~/uni-activity/storage/logs/laravel.log"))
print()

print("[what does / actually serve? headers]")
print(run("curl -s -D - -o /dev/null -m 10 http://127.0.0.1:8080/ | head -8"))
print()

print("[direct to worker :8000]")
print(run("curl -s -o /dev/null -w '%{http_code} %{time_total}s' -m 10 http://127.0.0.1:8000/; echo"))
print()

ssh.close()