#!/usr/bin/env python3
"""Inspect pid on 9999 and ~/app.py (monitor server) data sources."""

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

print("[pid 19134 cmdline + cwd]")
print(run("cat /proc/19134/cmdline | tr '\\0' ' '; echo; readlink -f /proc/19134/cwd"))
print()

print("[app.py size + head]")
print(run("wc -l ~/app.py; head -60 ~/app.py"))
print()

print("[what does app.py collect? grep key collectors]")
print(run(
    "grep -n 'def collect\\|services\\|valkey\\|redis\\|192.168\\|8000\\|8001\\|8002\\|8003\\|9999\\|broadcast\\|send_data\\|json.dumps' "
    "~/app.py | head -60"
))
print()

print("[monitor_server.log tail]")
print(run("tail -15 ~/monitor_server.log"))
print()

ssh.close()