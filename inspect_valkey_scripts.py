#!/usr/bin/env python3
"""Inspect S1 autostart/watchdog scripts that keep Valkey alive."""

import paramiko

CMDS = [
    ("autostart.sh", "cat ~/autostart.sh"),
    ("watchdog.sh", "cat ~/watchdog.sh"),
    ("start.sh", "cat ~/start.sh 2>/dev/null | head -60"),
    ("restart_services.sh", "cat ~/restart_services.sh 2>/dev/null | head -80"),
    ("valkey proc cmdline", "for p in $(pgrep -f valkey-server); do echo \"PID $p:\"; tr '\\0' ' ' < /proc/$p/cmdline; echo; done"),
    ("valkey6379 config used", "valkey-cli -p 6379 -a 'UniActivityRedis2026!' config get dir logfile dbfilename requirepass save appendonly 2>/dev/null"),
    ("valkey6380 config used", "valkey-cli -p 6380 -a 'UniActivityRedis2026!' config get dir logfile dbfilename requirepass save appendonly 2>/dev/null"),
]


def main() -> None:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)
    lines: list[str] = []
    for name, cmd in CMDS:
        _, o, e = ssh.exec_command(cmd, timeout=30)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        lines.append(f"\n===== [{name}] =====\n{out or err or '(no output)'}")
    ssh.close()
    report = "\n".join(lines)
    with open("s1_scripts_report.txt", "w", encoding="utf-8") as f:
        f.write(report)
    print(report)


if __name__ == "__main__":
    main()