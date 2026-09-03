#!/usr/bin/env python3
"""Gather runtime facts needed to make the dashboard reflect reality."""

import paramiko


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=60):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


ssh = connect("192.168.1.222", "u0_a175", "A2345678")
run = make_runner(ssh)

print("[listening ports now]")
print(run("ss -ltn | awk 'NR>1 {print $4}' | grep -oE '[0-9]+$' | sort -n | uniq"))
print()

print("[processes: artisan / reverb / ai / postgres]")
print(run(
    "pgrep -af 'artisan serve' | head -5; echo ---; "
    "pgrep -af 'reverb' | head -3; echo ---; "
    "pgrep -af 'server.py|uvicorn|fastapi' | head -3; echo ---; "
    "pgrep -af 'postgres' | head -2"
))
print()

print("[env: redis/valkey password var names (values masked)]")
print(run(
    "grep -E '^(REDIS_|VALKEY|QUEUE_|CACHE_)' ~/uni-activity/.env | "
    "sed -E 's/(PASSWORD=).+/\\1***set***/'"
))
print()

print("[valkey auth test with env password]")
q = chr(34)
print(run(
    "PW=$(grep '^REDIS_PASSWORD=' ~/uni-activity/.env | cut -d= -f2); "
    "valkey-cli -p 6379 -a " + q + "$PW" + q + " --no-auth-warning ping; "
    "valkey-cli -p 6380 -a " + q + "$PW" + q + " --no-auth-warning ping"
))
print()

print("[queue keys on 6380]")
print(run(
    "PW=$(grep '^REDIS_PASSWORD=' ~/uni-activity/.env | cut -d= -f2); "
    "valkey-cli -p 6380 -a " + q + "$PW" + q + " --no-auth-warning keys 'queues:*' | head -10"
))
print()

ssh.close()