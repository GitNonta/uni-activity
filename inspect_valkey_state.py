#!/usr/bin/env python3
"""Inspect current Redis/Valkey usage across both servers."""

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
    print("[server processes]")
    print(run("ps aux 2>/dev/null | grep -E 'valkey|redis' | grep -v grep || echo NONE"))
    print()
    print("[.env cache/queue/session/redis lines]")
    print(run("grep -E '^(CACHE_|QUEUE_|SESSION_|REDIS_|VALKEY_|BROADCAST)' ~/uni-activity/.env 2>/dev/null"))
    print()
    print("[config/database.php redis client]")
    print(run("grep -n "'client'" ~/uni-activity/config/database.php | head -3"))
    print()
    print("[phpredis extension]")
    print(run("php -m 2>/dev/null | grep -iE 'redis|igbinary' || echo NO-PHPREDIS"))
    print()
    print("[installed packages]")
    print(run("dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $2, $3}'"))
    print()
    print("[composer predis?]")
    print(run("grep -E 'predis|valkey' ~/uni-activity/composer.json"))
    print()
    ssh.close()


inspect("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)")
print()
inspect("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")