#!/usr/bin/env python3
"""Operational readiness check for the two dual-node Termux servers."""

import sys
import time

import paramiko

LINE = "=" * 70

SERVERS = [
    {
        "name": "S1 (Phone 1 - Master Gateway)",
        "host": "192.168.1.222",
        "port": 8022,
        "user": "u0_a175",
        "password": "A2345678",
        "checks": [
            ("Web app :8000", "curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 http://127.0.0.1:8000/"),
            ("Load balancer :8088", "curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 http://127.0.0.1:8088/health-cluster"),
            ("Reverb WS :8082", "curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 http://127.0.0.1:8082/"),
            ("PostgreSQL :5432", "netstat -tlpn 2>/dev/null | grep ':5432' | head -1 || echo NOT-LISTENING"),
            ("Valkey/Redis :6379", "netstat -tlpn 2>/dev/null | grep ':6379' | head -1 || echo NOT-LISTENING"),
            ("Nginx :8080/:8088", "netstat -tlpn 2>/dev/null | grep -E ':(8080|8088)' | head -4 || echo NOT-LISTENING"),
            ("cloudflared tunnel", "pgrep -f cloudflared >/dev/null && echo RUNNING || echo NOT-RUNNING"),
            ("web workers (serve)", "pgrep -fc 'artisan serve' || echo 0"),
            ("queue worker", "pgrep -fc 'queue:work' || echo 0"),
            ("watchdog", "pgrep -f watch_web_workers >/dev/null && echo RUNNING || echo NOT-RUNNING"),
            ("AI service :8001", "curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 http://127.0.0.1:8001/ 2>/dev/null || echo UNREACHABLE"),
        ],
    },
    {
        "name": "S2 (Phone 2 - Worker/AI)",
        "host": "192.168.1.140",
        "port": 8022,
        "user": "u0_a135",
        "password": "A23457",
        "checks": [
            ("Web app :8000", "curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 http://127.0.0.1:8000/"),
            ("AI service :8001", "curl -s -o /dev/null -w '%{http_code} %{time_total}' --max-time 10 http://127.0.0.1:8001/ 2>/dev/null || echo UNREACHABLE"),
            ("web workers (serve)", "pgrep -fc 'artisan serve' || echo 0"),
            ("queue worker", "pgrep -fc 'queue:work' || echo 0"),
            ("watchdog", "pgrep -f watch_web_workers >/dev/null && echo RUNNING || echo NOT-RUNNING"),
            ("DB reach S1 :5432", "timeout 3 sh -c 'echo > /dev/tcp/192.168.1.222/5432' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
            ("Valkey reach S1 :6379", "timeout 3 sh -c 'echo > /dev/tcp/192.168.1.222/6379' 2>/dev/null && echo REACHABLE || echo UNREACHABLE"),
        ],
    },
]

COMMON = [
    ("uptime/load", "cat /proc/loadavg"),
    ("memory", "free -m 2>/dev/null | head -3"),
    ("disk home", "df -h ~ | tail -1"),
]


def connect(cfg):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(cfg["host"], cfg["port"], cfg["user"], cfg["password"], timeout=15)
    return ssh


def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd, timeout=20)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


def main():
    overall_ok = True
    for cfg in SERVERS:
        print()
        print(LINE)
        print("SERVER: %s  (%s@%s:%d)" % (cfg["name"], cfg["user"], cfg["host"], cfg["port"]))
        print(LINE)

        t0 = time.time()
        try:
            ssh = connect(cfg)
        except Exception as exc:
            print("[FAIL] SSH connection failed: %s" % exc)
            overall_ok = False
            continue
        print("[OK] SSH connected in %.2fs" % (time.time() - t0))

        for label, cmd in COMMON + cfg["checks"]:
            result = run(ssh, cmd)
            print()
            print("[%s]" % label)
            print("  %s" % result)

        ssh.close()

    print()
    print(LINE)
    print("READINESS CHECK COMPLETE")
    print(LINE)
    return 0 if overall_ok else 1


if __name__ == "__main__":
    sys.exit(main())