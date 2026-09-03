#!/usr/bin/env python3
"""Troubleshoot recurring sshd death on S2; install proper supervision."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Is sshd running now?")
print(run("pgrep -a sshd || echo NOT-RUNNING"))
print()

print("[2] termux-services installed?")
print(run("dpkg -l 2>/dev/null | grep termux-services || dpkg -s termux-services 2>/dev/null | head -3 || echo NOT-INSTALLED"))
print()

print("[3] sv binary?")
print(run("ls -la $PREFIX/bin/sv $PREFIX/bin/runsvdir $PREFIX/bin/runsv 2>/dev/null || echo MISSING"))
print()

print("[4] runsvdir running?")
print(run("pgrep -a runsvdir || echo NOT-RUNNING"))
print()

print("[5] Install termux-services if missing")
print(run(
    "dpkg -s termux-services >/dev/null 2>&1 && echo ALREADY-INSTALLED "
    "|| (yes | pkg install -y termux-services 2>&1 | tail -5)"
))
print()

print("[6] Start runsvdir if not running")
print(run(
    "pgrep -x runsvdir >/dev/null && echo RUNSVDIR-ALIVE "
    "|| (setsid nohup runsvdir $PREFIX/var/service > /dev/null 2>&1 < /dev/null & sleep 2; pgrep -a runsvdir)"
))
print()

print("[7] Enable sshd service (no down marker) and bring up")
print(run("rm -f $PREFIX/var/service/sshd/down; $PREFIX/bin/sv up sshd 2>&1; sleep 1; $PREFIX/bin/sv status sshd 2>&1"))
print()

print("[8] Final state")
print(run("pgrep -a sshd | head -2"))

ssh.close()