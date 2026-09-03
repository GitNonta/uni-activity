#!/usr/bin/env python3
"""Find the reverb spawn loop (tmux session?) and capture load-test error types."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[tmux sessions]")
print(run("tmux ls 2>/dev/null"))
print()

print("[tmux reverb pane content]")
print(run("tmux capture-pane -t reverb -p 2>/dev/null | tail -20 || echo no-tmux-reverb"))
print()

print("[all bash loops mentioning reverb]")
print(run("ps -ef | grep -E 'bash|zsh|sh ' | grep -v grep | grep -vE 'sshd|pts/' | head -20"))
print()

print("[watch_web_workers.sh content]")
print(run("cat ~/watch_web_workers.sh 2>/dev/null | head -40"))
print()

ssh.close()