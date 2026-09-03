#!/usr/bin/env python3
"""Find why S2 web workers return 500; fix; verify."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[S2 laravel.log last error]")
print(run(
    "grep 'production.ERROR' ~/uni-activity/storage/logs/laravel.log "
    "| grep -v EADDRINUSE | tail -2 | cut -c1-350"
))
print()

print("[S2 .env redis/db hosts]")
print(run("grep -E '^(REDIS_HOST|REDIS_PASSWORD|DB_HOST|CACHE_STORE|SESSION_DRIVER|QUEUE_CONNECTION)' "
          "~/uni-activity/.env"))
print()

print("[S2 connectivity: valkey 6379/6380 + pg on S1]")
print(run("timeout 2 bash -c 'echo > /dev/tcp/192.168.1.222/6379' && echo 6379-ok || echo 6379-fail; "
          "timeout 2 bash -c 'echo > /dev/tcp/192.168.1.222/6380' && echo 6380-ok || echo 6380-fail; "
          "timeout 2 bash -c 'echo > /dev/tcp/192.168.1.222/5432' && echo pg-ok || echo pg-fail"))
print()

print("[config cache check]")
print(run("ls -la ~/uni-activity/bootstrap/cache/config.php 2>/dev/null; "
          "grep -c 'REDIS_PASSWORD' ~/uni-activity/bootstrap/cache/config.php 2>/dev/null"))
print()

ssh.close()