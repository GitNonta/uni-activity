#!/usr/bin/env python3
"""More S2 facts: other services, AI port, boot script for monitor."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[other services: cloudflared/nginx/postgres/queue worker/sshd]")
print(run(
    "pgrep -af 'cloudflared' | grep -v pgrep | head -3; echo ---; "
    "pgrep -af 'nginx' | grep -v pgrep | head -3; echo ---; "
    "pgrep -af 'postgres' | grep -v pgrep | head -3; echo ---; "
    "pgrep -af 'queue:work' | grep -v pgrep | head -3; echo ---; "
    "pgrep -af 'sshd' | grep -v pgrep | head -3"
))
print()

print("[AI service port]")
print(run("grep -nE 'port|PORT' ~/uni-activity/ai_service/server.py | head -10"))
print()

print("[boot scripts referencing monitor]")
print(run("ls ~/.termux/boot/ 2>/dev/null; grep -rl 'monitor_server' ~/.termux/boot/ ~/uni-activity/*.sh 2>/dev/null | head"))
print()

print("[monitor log on S2]")
print(run("ls -la ~/monitor_server.log 2>/dev/null && tail -5 ~/monitor_server.log"))
print()

print("[remote valkey reachable from S2]")
print(run(
    "PW=$(grep '^REDIS_PASSWORD=' ~/uni-activity/.env | cut -d= -f2); "
    "valkey-cli -h 192.168.1.222 -p 6379 -a $PW --no-auth-warning ping 2>&1; "
    "valkey-cli -h 192.168.1.222 -p 6380 -a $PW --no-auth-warning llen queues:default 2>&1"
))
print()

ssh.close()