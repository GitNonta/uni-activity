#!/usr/bin/env python3
"""Find what reverted collectors.py on S2 at 14:17."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[auto updater / sync processes]")
print(run("pgrep -af 'auto_update|sync|updater|rsync|git' | grep -v pgrep | head -10"))
print()

print("[svc_auto_updater script head]")
print(run("head -40 ~/svc_auto_updater.sh 2>/dev/null || echo none"))
print()

print("[is uni-activity a git repo? recent reflog]")
print(run(
    "cd ~/uni-activity && git rev-parse --is-inside-work-tree 2>/dev/null && "
    "git log --oneline -3 2>/dev/null && git status --short py/ 2>/dev/null | head"
))
print()

print("[cron/boot entries mentioning update/sync]")
print(run(
    "grep -rn 'auto_update\\|git pull\\|rsync' ~/.termux/boot/ ~/start-cluster.sh "
    "~/uni-activity/start-cluster.sh 2>/dev/null | head -10"
))
print()

ssh.close()