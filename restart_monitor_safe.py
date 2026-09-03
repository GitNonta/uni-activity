#!/usr/bin/env python3
"""Restart monitor server safely (bracket-pattern avoids pkill self-match)."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[kill old instance]")
print(run("pkill -f 'monitor_serve[r].py'; sleep 2; pgrep -f 'monitor_serve[r].py' || echo killed"))
print()

print("[start detached]")
print(run(
    "cd ~/uni-activity && setsid nohup python -u py/monitor_server.py "
    ">> ~/monitor_server.log 2>&1 < /dev/null & sleep 5; "
    "pgrep -f 'monitor_serve[r].py'"
))
print()

time.sleep(3)

print("[pid + port check]")
print(run("pgrep -af 'monitor_serve[r].py'"))
print(run("curl -s -o /dev/null -w '%{http_code}' -m 3 http://127.0.0.1:9999/"))
print()

print("[index.html bundle ref]")
q = chr(34)
print(run("curl -s http://127.0.0.1:9999/ | grep -o " + q + "assets/index-[A-Za-z0-9._-]*" + q))
print("[new asset status]")
print(run("curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:9999/assets/index-D44OyvcX.js"))
print()

print("[log tail]")
print(run("tail -8 ~/monitor_server.log"))
print()

ssh.close()