#!/usr/bin/env python3
"""Inspect S2 Termux boot/ssh setup before configuring persistent SSH."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] boot dir contents")
print(run("ls -la ~/.termux/boot/ 2>/dev/null || echo NO-BOOT-DIR"))
print()
print("[2] start-cluster.sh (head)")
print(run("head -40 ~/.termux/boot/start-cluster.sh 2>/dev/null || echo NO-SCRIPT"))
print()
print("[3] does boot script start sshd?")
print(run("grep -n 'sshd' ~/.termux/boot/start-cluster.sh 2>/dev/null || echo NO-SSHD-IN-BOOT"))
print()
print("[4] .bashrc / .profile sshd lines")
print(run("grep -n 'sshd' ~/.bashrc ~/.profile 2>/dev/null || echo NONE"))
print()
print("[5] termux-services installed?")
print(run("ls $PREFIX/var/service/ 2>/dev/null || echo NO-RUNIT-SERVICES"))
print()
print("[6] current sshd process")
print(run("pgrep -a sshd || echo NOT-RUNNING"))

ssh.close()