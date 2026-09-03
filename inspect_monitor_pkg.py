#!/usr/bin/env python3
"""Inspect py/monitor package: config, service detection, stats collector."""

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

print("[package files]")
print(run("ls -la ~/uni-activity/py/monitor/"))
print()

print("[config.py]")
print(run("cat ~/uni-activity/py/monitor/config.py"))
print()

print("[service definitions / checks]")
print(run(
    "grep -n 'services\\|SERVICE\\|valkey\\|redis\\|artisan\\|reverb\\|queue\\|nginx\\|cloudflared' "
    "~/uni-activity/py/monitor/*.py | head -80"
))
print()

ssh.close()