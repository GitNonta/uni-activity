#!/usr/bin/env python3
"""Quick verification that S2 SSH is up and responsive."""

import time

import paramiko

t0 = time.time()
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)
print("SSH connected in %.2fs" % (time.time() - t0))

for cmd in [
    "pgrep -x sshd >/dev/null && echo SSHD-RUNNING || echo SSHD-NOT-RUNNING",
    "echo ALIVE-$(date '+%T')",
]:
    _, o, _ = ssh.exec_command(cmd, timeout=10)
    print(o.read().decode(errors="ignore").strip())

ssh.close()