#!/usr/bin/env python3
"""Inspect capacity levers before optimizing: RAM, opcache, caches, php workers."""

import paramiko

NODES = [
    ("S1", "192.168.1.222", 8022, "u0_a175", "A2345678"),
    ("S2", "192.168.1.140", 8022, "u0_a135", "A23457"),
]

for name, host, port, user, pw in NODES:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, port, user, pw, timeout=15)

    def run(cmd, timeout=60):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"

    print(f"===== {name} ({host}) =====")
    print("[memory]")
    print(run("free -m | head -3"))
    print()
    print("[opcache enabled?]")
    print(run("php -m | grep -i opcache || echo no-opcache"))
    print(run("php -i 2>/dev/null | grep -E 'opcache.enable |opcache.memory' | head -3"))
    print()
    print("[laravel caches present?]")
    print(run("ls ~/uni-activity/bootstrap/cache/ 2>/dev/null"))
    print()
    print("[current web workers]")
    print(run("pgrep -fc 'artisan serve'"))
    print()
    print("[load]")
    print(run("uptime"))
    print()

    ssh.close()