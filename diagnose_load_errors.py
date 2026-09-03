#!/usr/bin/env python3
"""Diagnose load-test errors: status codes via nginx log + direct probes."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[nginx access log: LoadTest status distribution]")
print(run(
    "grep 'LoadTest' /data/data/com.termux/files/home/nginx/logs/access.log 2>/dev/null "
    "| awk '{print $9}' | sort | uniq -c | sort -rn | head "
    "|| echo no-access-log"
))
print()

print("[find nginx logs]")
print(run("ls ~/nginx/logs/ 2>/dev/null; ls ~/uni-activity/storage/logs/*.log 2>/dev/null | head"))
print()

print("[recent access log tail]")
print(run("tail -8 ~/nginx/logs/access.log 2>/dev/null || true"))
print()

print("[nginx error log tail]")
print(run("tail -8 ~/nginx/logs/error.log 2>/dev/null || true"))
print()

print("[rate limit config?]")
print(run("grep -rn 'limit_req\\|limit_conn\\|worker_connections' ~/nginx/conf/nginx.conf ~/uni-activity/docker/nginx* 2>/dev/null | head"))
print()

print("[sequential probe x10]")
print(run("for i in $(seq 1 10); do curl -s -o /dev/null -w '%{http_code} %{time_total}s\
' -m 10 http://127.0.0.1:8080/; done"))
print()

print("[true loadavg]")
print(run("uptime; cat /proc/loadavg"))
print()

ssh.close()