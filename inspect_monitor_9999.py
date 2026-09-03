#!/usr/bin/env python3
"""Find what serves port 9999 on S1 and where its data comes from."""

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

print("[who listens on 9999]")
print(run("ss -tlnp 2>/dev/null | grep 9999 || netstat -tlnp 2>/dev/null | grep 9999"))
print()

print("[process details for pid on 9999]")
pid = run("ss -tlnp 2>/dev/null | grep 9999 | grep -oE 'pid=[0-9]+' | head -1 | cut -d= -f2")
if pid and pid.isdigit():
    print(run(f"cat /proc/{pid}/cmdline | tr '\\0' ' '; echo; readlink -f /proc/{pid}/cwd"))
else:
    print("(could not parse pid)")
print()

print("[search for monitor server scripts]")
print(run(
    "ls ~ ; echo ---; "
    "grep -rl '9999' ~/.termux/boot/ 2>/dev/null; "
    "ls ~/uni-activity/monitor-ui 2>/dev/null"
))
print()

print("[any node/python ws server scripts mentioning 9999]")
print(run(
    "grep -rln 9999 ~/uni-activity --include=*.js --include=*.py 2>/dev/null | head -10"
))
print()

print("[HTTP probe of 9999 root]")
print(run("curl -s http://127.0.0.1:9999/ | head -20"))
print()

print("[HTTP probe of 9999 /ws handshake-ish + other endpoints]")
print(run("curl -s -i http://127.0.0.1:9999/ws | head -8"))
print()

ssh.close()