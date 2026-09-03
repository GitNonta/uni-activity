#!/usr/bin/env python3
"""Why does S2 monitor still broadcast old data? Check running proc + files."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[running monitor proc details]")
print(run(
    "for p in $(pgrep -f 'monitor_serve[r].py'); do "
    "echo PID=$p; cat /proc/$p/cmdline | tr '\\0' ' '; echo; "
    "readlink -f /proc/$p/cwd; done"
))
print()

print("[patched markers present in file?]")
print(run("grep -n 'Web Workers\\|_valkey_cli\\|Swoole' ~/uni-activity/py/monitor/collectors.py | head"))
print()

print("[other copies of monitor_server/collectors]")
print(run(
    "find ~/uni-activity -name 'monitor_server.py' -o -name 'collectors.py' 2>/dev/null | head; "
    "ls ~/uni-activity/py/monitor/__pycache__/ 2>/dev/null"
))
print()

print("[who restarted it? watchdog processes]")
print(run("pgrep -af 'watchdog|start-cluster|supervis' | grep -v pgrep | head -5"))
print()

print("[file mtime]")
print(run("stat -c '%y %n' ~/uni-activity/py/monitor/collectors.py"))
print()

ssh.close()