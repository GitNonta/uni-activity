#!/usr/bin/env python3
"""Check runit supervision state using explicit service paths."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=40):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] supervise stat")
print(run("cat $PREFIX/var/service/sshd/supervise/stat 2>/dev/null; echo; cat $PREFIX/var/service/sshd/supervise/pid 2>/dev/null"))
print()

print("[2] sv with explicit path")
print(run("$PREFIX/bin/sv up $PREFIX/var/service/sshd 2>&1; sleep 2; $PREFIX/bin/sv status $PREFIX/var/service/sshd 2>&1"))
print()

print("[3] sshd processes")
print(run("pgrep -a sshd | head -4"))
print()

print("[4] runsv processes")
print(run("pgrep -a runsv | head -5"))

ssh.close()