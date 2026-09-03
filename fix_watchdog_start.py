#!/usr/bin/env python3
"""Properly start the valkey watchdog (self-match-safe) and verify it stays up."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


# NOTE: use 'bash.*watch_valkey[.]sh' so the invoking shell's own cmdline
# (containing the literal bracket pattern) never self-matches.
START = r"""
if ! pgrep -f 'bash.*watch_valkey[.]sh' >/dev/null 2>&1; then
    setsid nohup bash "$HOME/watch_valkey.sh" >/dev/null 2>&1 </dev/null &
    sleep 2
fi
echo "--- processes ---"
pgrep -af 'bash.*watch_valkey[.]sh' || echo NOT-RUNNING
"""

print("[1] Start watchdog (self-match-safe)")
print(run(START))
print()

print("[2] Re-check after 5s (still alive?)")
print(run("sleep 5; pgrep -af 'bash.*watch_valkey[.]sh' || echo NOT-RUNNING"))
print()

print("[3] Boot script valkey sections")
print(run("grep -n -A4 'Valkey watchdog' ~/.termux/boot/start-cluster.sh"))
print()

print("[4] Watchdog log")
print(run("cat $HOME/valkey-watchdog.log 2>/dev/null || echo EMPTY"))

ssh.close()