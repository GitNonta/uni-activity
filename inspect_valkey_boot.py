#!/usr/bin/env python3
"""Check boot persistence + package state before switching to full Valkey."""

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

    print("[~/.termux/boot contents]")
    print(run('ls -la ~/.termux/boot/ 2>/dev/null; echo ---; '
              'for f in ~/.termux/boot/*; do echo "== $f"; cat "$f" 2>/dev/null; done'))
    print()

    print("[dpkg redis/valkey state]")
    print(run("dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $1, $2, $3}'"))
    print()

    print("[php redis module check]")
    print(run("php -m 2>/dev/null | grep -iE '^redis$|igbinary' || echo NO-REDIS-MODULE; "
              "php --ini 2>/dev/null | head -5"))
    print()

    print("[valkey process cmdline + cwd]")
    print(run(
        'for p in $(pgrep valkey-server); do '
        'echo "== pid $p"; cat /proc/$p/cmdline 2>/dev/null | tr "\\0" " "; echo; '
        'ls -l /proc/$p/cwd 2>/dev/null; done'
    ))
    print()

    print("[valkey data dirs]")
    print(run("ls -la ~/valkey-data ~/valkey-data2 2>/dev/null"))
    print()

    ssh.close()


inspect("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)")
print()
inspect("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")