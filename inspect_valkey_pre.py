#!/usr/bin/env python3
"""Final pre-migration checks: S2 valkey pkg + start-valkey.sh, S1 details."""

import paramiko


def inspect(host, user, pw, label):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)

    def run(cmd, timeout=30):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"

    print("=" * 60)
    print(label)
    print("=" * 60)
    print(run("dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $1, $2, $3}'"))
    print()
    print(run("cat ~/start-valkey.sh 2>/dev/null"))
    print()
    print(run("cat ~/valkey-status.sh 2>/dev/null"))
    print()
    ssh.close()


inspect("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("=" * 60)
print("S1 extras")
print("=" * 60)
print("[valkey pkg files / cli]")
print(run("which valkey-server valkey-cli; valkey-server --version"))
print()
print("[persistence settings]")
print(run("valkey-cli -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning CONFIG GET appendonly dir dbfilename save 2>/dev/null | head -12"))
print()
print("[migrate script present?]")
print(run("ls -la ~/uni-activity/scripts/migrate_redis_to_valkey.php 2>/dev/null || echo NO-MIGRATE-SCRIPT"))
print()
print("[dbsize]")
print(run("valkey-cli -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning DBSIZE; valkey-cli -p 6380 -a 'UniActivityRedis2026!' --no-auth-warning DBSIZE"))
print()
print("[boot script tail]")
print(run("tail -40 ~/.termux/boot/start-cluster.sh"))
ssh.close()