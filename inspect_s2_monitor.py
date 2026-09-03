#!/usr/bin/env python3
"""Inspect S2 (192.168.1.140) monitor server + runtime stack facts."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[port 9999 process]")
print(run("pgrep -af 'monitor_server' | grep -v pgrep || echo none"))
print()

print("[collectors.py exists + get_services snippet]")
print(run(
    "ls -la ~/uni-activity/py/monitor/collectors.py 2>/dev/null && "
    "grep -n 'def get_services\\|Swoole\\|redis-cli\\|valkey' "
    "~/uni-activity/py/monitor/collectors.py | head -20"
))
print()

print("[processes: artisan / reverb / ai / valkey]")
print(run(
    "pgrep -af 'artisan serve' | head -5; echo ---; "
    "pgrep -af 'reverb:start' | head -2; echo ---; "
    "pgrep -af 'server.py|uvicorn|fastapi|insightface' | grep -v pgrep | head -5; echo ---; "
    "pgrep -af 'valkey-server|redis-server' | grep -v pgrep"
))
print()

print("[env redis vars (masked)]")
print(run(
    "grep -E '^(REDIS_|QUEUE_|CACHE_)' ~/uni-activity/.env | "
    "sed -E 's/(PASSWORD=).+/[REDACTED]/'"
))
print()

print("[dist bundle ref served on 9999]")
q = chr(34)
print(run("curl -s -m 3 http://127.0.0.1:9999/ | grep -o " + q + "assets/index-[A-Za-z0-9._-]*" + q))
print()

print("[valkey-cli available + auth test]")
print(run(
    "which valkey-cli redis-cli; "
    "PW=$(grep '^REDIS_PASSWORD=' ~/uni-activity/.env | cut -d= -f2); echo pw_len=${#PW}"
))
print()

ssh.close()