#!/usr/bin/env python3
"""Read the full 79-line py/monitor_server.py and find the real module."""

import paramiko


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=60):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


ssh = connect("192.168.1.222", "u0_a175", "A2345678")
run = make_runner(ssh)

print("[full py/monitor_server.py]")
print(run("cat ~/uni-activity/py/monitor_server.py"))
print()

print("[py dir listing]")
print(run("ls -la ~/uni-activity/py/ | head -30"))
print()

print("[monitor_server.log: when did the 1269 error happen? count lines]")
print(run("wc -l ~/monitor_server.log; grep -c 'UnboundLocalError' ~/monitor_server.log"))
print()

print("[last modified times]")
print(run(
    "stat -c '%y %n' ~/uni-activity/py/monitor_server.py ~/monitor_server.log "
    "~/uni-activity/monitor-ui/dist/assets/* 2>/dev/null"
))
print()

ssh.close()