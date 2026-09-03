#!/usr/bin/env python3
"""Verify S2 -> S1 database/redis connectivity properly."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] PostgreSQL :5432 via /dev/tcp")
print(run(
    "(echo > /dev/tcp/192.168.1.222/5432) 2>/dev/null && echo PG-REACHABLE || echo PG-UNREACHABLE"
))
print()

print("[2] Valkey :6379 via /dev/tcp")
print(run(
    "(echo > /dev/tcp/192.168.1.222/6379) 2>/dev/null && echo VALKEY-REACHABLE || echo VALKEY-UNREACHABLE"
))
print()

print("[3] Laravel actually working? (DB-backed page)")
print(run(
    "curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' --max-time 15 http://127.0.0.1:8000/login"
))
print()

print("[4] Queue worker alive + recent log")
print(run("pgrep -f 'queue:work' >/dev/null && echo QUEUE-ALIVE || echo QUEUE-DEAD"))
print(run("tail -3 ~/uni-activity/storage/logs/queue.log 2>/dev/null"))

ssh.close()