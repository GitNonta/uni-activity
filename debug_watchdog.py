#!/usr/bin/env python3
"""Debug why watch_valkey.sh dies: syntax-check + short foreground run."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] File exists + head")
print(run("ls -la $HOME/watch_valkey.sh; head -5 $HOME/watch_valkey.sh"))
print()

print("[2] Syntax check")
print(run("bash -n $HOME/watch_valkey.sh && echo SYNTAX-OK || echo SYNTAX-ERROR"))
print()

print("[3] Foreground run 4s (capture errors)")
print(run("timeout 4 bash $HOME/watch_valkey.sh; echo EXIT-CODE=$?"))
print()

print("[4] Start detached, then inspect /proc after 3s")
print(run(
    "setsid nohup bash $HOME/watch_valkey.sh >/tmp/wv.out 2>&1 </dev/null & "
    "sleep 3; "
    "for p in $(pgrep -f 'watch_valkey'); do echo \"PID $p: $(tr '\\0' ' ' < /proc/$p/cmdline)\"; done; "
    "cat /tmp/wv.out"
))
print()

print("[5] Check again after another 8s")
print(run("sleep 8; for p in $(pgrep -f 'watch_valkey'); do echo \"PID $p alive\"; done; echo done"))

ssh.close()