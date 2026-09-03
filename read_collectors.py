#!/usr/bin/env python3
"""Read exact sections of py/monitor/collectors.py to plan precise edits."""

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

print("[get_services + helpers: lines 600-730]")
print(run("sed -n '600,730p' ~/uni-activity/py/monitor/collectors.py"))
print()

print("[redis/queue stats: lines 810-870]")
print(run("sed -n '810,870p' ~/uni-activity/py/monitor/collectors.py"))
print()

print("[valkey-cli available?]")
print(run("which valkey-cli redis-cli; valkey-cli -p 6379 ping 2>&1; valkey-cli -p 6380 ping 2>&1"))
print()

ssh.close()