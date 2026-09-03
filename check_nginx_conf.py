#!/usr/bin/env python3
"""Find real nginx config + error log; reproduce 500 with logging."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[running nginx + config path]")
print(run("ps -ef | grep 'nginx' | grep -v grep | head -3"))
print(run("nginx -t 2>&1 | head -3"))
print()

print("[full effective config: proxy/upstream/error_page]")
print(run("nginx -T 2>/dev/null | grep -nE 'proxy_pass|upstream|error_page|limit_req|server 127|server 192' | head -25"))
print()

print("[reproduce 8 parallel + capture nginx error log delta]")
D = chr(36)
before = run("wc -l < " + D + "(nginx -T 2>/dev/null | grep -m1 'error_log' "
             "| awk '{print $2}' | tr -d ';') 2>/dev/null || echo 0")
print("error_log lines before:", before)
print(run("for i in " + D + "(seq 1 8); do curl -s -o /dev/null -w '%{http_code}"
          + chr(92) + "n' -m 10 http://127.0.0.1:8080/ & done; wait"))
time.sleep(1)
delta_cmd = (
    "EL=" + D + "(nginx -T 2>/dev/null | grep -m1 'error_log' | awk '{print $2}' | tr -d ';'); "
    "tail -n +" + str(int(before or 0) + 1) + " " + D + "EL 2>/dev/null | head -10"
)
print(run(delta_cmd))
print()

ssh.close()