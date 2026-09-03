#!/usr/bin/env python3
"""Find real cause of intermittent 500s + NOAUTH + reverb spawn loop."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[non-reverb errors in laravel.log (last 3)]")
print(run(
    "grep 'production.ERROR' ~/uni-activity/storage/logs/laravel.log "
    "| grep -v EADDRINUSE | tail -3 | cut -c1-400"
))
print()

print("[EADDRINUSE still recurring? last 2 timestamps]")
print(run("date '+%F %T'; grep 'EADDRINUSE' ~/uni-activity/storage/logs/laravel.log | tail -2 | cut -c1-80"))
print()

print("[NOAUTH occurrences]")
print(run("grep -c 'NOAUTH' ~/uni-activity/storage/logs/laravel.log"))
print(run("grep 'NOAUTH' ~/uni-activity/storage/logs/laravel.log | tail -1 | cut -c1-300"))
print()

print("[valkey auth check]")
auth_cmd = (
    "RPW=" + chr(36) + "(awk -F= '/^REDIS_PASSWORD=/{print " + chr(36) + "2}' "
    "~/uni-activity/.env | tr -d '" + chr(34) + chr(92) + "r'); "
    "valkey-cli -h 127.0.0.1 -p 6379 -a " + chr(36) + "RPW --no-auth-warning ping; "
    "valkey-cli -h 127.0.0.1 -p 6379 ping 2>&1 | head -1"
)
print(run(auth_cmd))
print()

print("[who spawns reverb repeatedly? check server_services_setup.sh]")
print(run("grep -n 'reverb' ~/server_services_setup.sh 2>/dev/null | head -10"))
print()

print("[watch_s2.sh reverb refs]")
print(run("grep -n 'reverb' ~/watch_s2.sh 2>/dev/null | head -10"))
print()

ssh.close()