#!/usr/bin/env python3
"""Final full-Valkey verification: watchdog identity/persistence + all services."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[S1-1] Watchdog cmdline (fresh connection)")
print(run(
    "for p in $(pgrep -f 'watch_valkey'); do "
    "echo \"PID $p: $(tr '\\0' ' ' < /proc/$p/cmdline 2>/dev/null)\"; done"
))
print()

print("[S1-2] Valkey instances")
print(run(
    "for p in 6379 6380; do "
    "echo \"port $p: $(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning ping) "
    "ver=$(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning INFO server | grep -m1 valkey_version | tr -d '\\r') "
    "aof=$(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning CONFIG GET appendonly | tail -1) "
    "keys=$(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning dbsize)\"; done"
))
print()

print("[S1-3] No redis binaries/processes")
print(run("command -v redis-server redis-cli || echo NO-REDIS-BINARIES; pgrep -x redis-server || echo NO-REDIS-PROCESS"))
print()

print("[S1-4] Laravel: cache round-trip + queue ping")
print(run(
    "cd ~/uni-activity && php artisan tinker --execute=\""
    "Cache::store('redis')->put('final_check','ok',60); "
    "echo 'cache='.Cache::store('redis')->get('final_check'); "
    "echo ' queue='.Queue::size('default');\" 2>&1 | tail -1"
))
print()

print("[S1-5] Queue worker")
print(run("pgrep -f 'artisan queue:work' >/dev/null && echo WORKER-ALIVE || echo NO-WORKER"))
ssh.close()

print()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run2(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[S2-1] valkey-status.sh (remote check against S1)")
print(run2("bash $HOME/valkey-status.sh"))
print()

print("[S2-2] Laravel cache over network to S1 valkey")
print(run2(
    "cd ~/uni-activity && php artisan tinker --execute=\""
    "Cache::store('redis')->put('s2_final','ok',60); "
    "echo 'cache='.Cache::store('redis')->get('s2_final');\" 2>&1 | tail -1"
))
print()

print("[S2-3] Queue worker")
print(run2("pgrep -f 'artisan queue:work' >/dev/null && echo WORKER-ALIVE || echo NO-WORKER"))
ssh.close()