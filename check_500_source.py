#!/usr/bin/env python3
"""Identify what produces HTTP 500 under concurrency."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[laravel.log errors in test window (15:39-15:41), non-EADDRINUSE]")
print(run(
    "awk '/^\\[2026-08-25 15:(39|40|41)/{p=1} p&&/production.ERROR/&&!/EADDRINUSE/{print; c++} c>=3{exit}' "
    "~/uni-activity/storage/logs/laravel.log | cut -c1-300"
))
print()

print("[nginx: does it intercept errors / which upstream?]")
print(run("grep -n 'proxy_pass\\|error_page\\|proxy_intercept' ~/nginx/conf/nginx.conf 2>/dev/null | head -10"))
print()

print("[serve worker logs tail]")
print(run("tail -5 ~/uni-activity/serve-8000.log ~/uni-activity/serve-8002.log ~/uni-activity/serve-8003.log 2>/dev/null"))
print()

print("[reproduce: 8 parallel curls, show codes]")
print(run(
    "for i in $(seq 1 8); do curl -s -o /dev/null -w '%{http_code}\
' -m 10 http://127.0.0.1:8080/ & done; wait"
))
print()

ssh.close()