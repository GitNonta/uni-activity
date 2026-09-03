#!/usr/bin/env python3
"""Pull key parts of py/monitor_server.py from S1."""

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

print("[file size]")
print(run("wc -l ~/uni-activity/py/monitor_server.py"))
print()

print("[structure: defs and threading references]")
print(run(
    "grep -n 'def \\|import threading\\|threading\\.' ~/uni-activity/py/monitor_server.py | head -80"
))
print()

print("[valkey/redis/services data sources]")
print(run(
    "grep -n 'valkey\\|redis\\|6379\\|6380\\|services\\[' ~/uni-activity/py/monitor_server.py | head -50"
))
print()

print("[around line 1269 (the bug)]")
print(run("sed -n '1230,1290p' ~/uni-activity/py/monitor_server.py"))
print()

ssh.close()