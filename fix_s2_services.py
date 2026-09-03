#!/usr/bin/env python3
"""Verify S1 ports from S2 (bash /dev/tcp), then start node2 services."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Test S1 ports using bash explicitly")
for port in ("5432", "6379", "8000"):
    cmd = (
        "bash -c 'echo > /dev/tcp/192.168.1.222/%s' 2>/dev/null "
        "&& echo PORT-%s-REACHABLE || echo PORT-%s-UNREACHABLE"
    ) % (port, port, port)
    print("  S1:%s -> %s" % (port, run(cmd)))

print()
print("[2] Check watchdog process detail")
print("  " + run("ps aux 2>/dev/null | grep watch_web_workers | grep -v grep || echo NOT-RUNNING"))

print()
print("[3] Starting node2 cluster services...")
start_cmd = (
    "cd ~/uni-activity && nohup bash scripts/start_dual_node.sh node2 192.168.1.222 "
    "> storage/logs/start-node2.log 2>&1 & echo STARTED-PID-$!"
)
print("  " + run(start_cmd))

print()
print("[4] Waiting 25s for services to come up...")
time.sleep(25)

print("[5] Listening ports after start:")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE-LISTENING"))

print()
print("[6] HTTP timing checks:")
for port in ("8000", "8001"):
    print(
        "  :%s -> %s"
        % (port, run("curl -s -o /dev/null -w 'HTTP %%{http_code} total=%%{time_total}s' --max-time 10 http://127.0.0.1:%s/" % port))
    )

ssh.close()