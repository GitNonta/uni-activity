#!/usr/bin/env python3
"""Deep inspection of Valkey setup on S1: supervision, config, persistence."""

import paramiko

LINE = "=" * 70


def connect(host, port, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, port, user, pw, timeout=20)
    return ssh


def run(ssh, cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


def main():
    ssh = connect("192.168.1.222", 8022, "u0_a175", "A2345678")

    checks = [
        ("valkey processes", "ps aux | grep valkey-server | grep -v grep"),
        ("redis-server processes", "ps aux | grep redis-server | grep -v grep || echo NONE-RUNNING"),
        ("redis binaries installed", "ls -la $PREFIX/bin/ 2>/dev/null | grep -E 'redis|valkey' || echo NONE"),
        ("runit services dir", "ls ~/sv/ 2>/dev/null || ls $PREFIX/var/service/ 2>/dev/null || echo NO-SV-DIR"),
        ("runit service contents", "for d in ~/sv/*/; do echo \"--- $d\"; cat \"$d/run\" 2>/dev/null; done || echo NONE"),
        ("termux boot scripts", "ls ~/.termux/boot/ 2>/dev/null && for f in ~/.termux/boot/*; do echo \"--- $f\"; head -40 \"$f\"; done || echo NO-BOOT-SCRIPTS"),
        ("valkey config files", "find ~ -maxdepth 4 -name '*valkey*.conf' -o -maxdepth 4 -name 'valkey*.conf' 2>/dev/null | head -10"),
        ("6379 INFO server", "valkey-cli -h 127.0.0.1 -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning INFO server 2>&1 | grep -E 'valkey_version|redis_version|process_id|uptime|config_file' "),
        ("6379 persistence", "valkey-cli -h 127.0.0.1 -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning INFO persistence 2>&1 | grep -E 'aof_enabled|rdb_last_save|loading:'"),
        ("6380 INFO server", "valkey-cli -h 127.0.0.1 -p 6380 -a 'UniActivityRedis2026!' --no-auth-warning INFO server 2>&1 | grep -E 'valkey_version|redis_version|process_id|uptime|config_file'"),
        ("6380 persistence", "valkey-cli -h 127.0.0.1 -p 6380 -a 'UniActivityRedis2026!' --no-auth-warning INFO persistence 2>&1 | grep -E 'aof_enabled|rdb_last_save|loading:'"),
        ("6379 requirepass set?", "valkey-cli -h 127.0.0.1 -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning CONFIG GET requirepass 2>&1 | tail -2"),
        ("6380 requirepass set?", "valkey-cli -h 127.0.0.1 -p 6380 -a 'UniActivityRedis2026!' --no-auth-warning CONFIG GET requirepass 2>&1 | tail -2"),
        ("6379 keyspace", "valkey-cli -h 127.0.0.1 -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning INFO keyspace 2>&1"),
        ("6380 keyspace", "valkey-cli -h 127.0.0.1 -p 6380 -a 'UniActivityRedis2026!' --no-auth-warning INFO keyspace 2>&1"),
        ("how started (cmdline)", "for pid in $(pgrep -f valkey-server); do echo \"--- PID $pid\"; cat /proc/$pid/cmdline | tr '\\0' ' '; echo; done"),
        ("watchdog scripts", "ls ~/*.sh ~/watch* 2>/dev/null | head -20; pgrep -af watchdog || echo NO-WATCHDOG"),
    ]

    print(LINE)
    print("S1 DEEP VALKEY INSPECTION")
    print(LINE)
    for label, cmd in checks:
        print()
        print("[%s]" % label)
        print(run(ssh, cmd))

    ssh.close()
    print()
    print(LINE)
    print("DONE")
    print(LINE)


if __name__ == "__main__":
    main()