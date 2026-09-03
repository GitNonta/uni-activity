#!/usr/bin/env python3
"""Inspect S2 current services + automation scripts before enabling full auto mode."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Running app processes")
print(run("ps aux 2>/dev/null | grep -E 'artisan|watch_web|server.py|uvicorn' | grep -v grep || echo NONE"))
print()
print("[2] Listening ports")
print(run("netstat -tlpn 2>/dev/null | grep LISTEN | grep -E ':(8000|8001|8002|8003)' || echo NONE"))
print()
print("[3] HTTP checks")
for port in ("8000", "8001"):
    print("  :%s -> %s" % (port, run(
        "curl -s -o /dev/null -w 'HTTP %{http_code} total=%{time_total}s' --max-time 8 http://127.0.0.1:%s/ 2>&1"
    ) if False else run(
        "curl -s -o /dev/null -w 'HTTP %%{http_code} total=%%{time_total}s' --max-time 8 http://127.0.0.1:%s/ 2>&1" % port
    )))
print()
print("[4] Full start-cluster.sh")
print(run("cat ~/.termux/boot/start-cluster.sh"))
print()
print("[5] start_web_workers.sh")
print(run("cat ~/start_web_workers.sh 2>/dev/null || echo MISSING"))
print()
print("[6] watchdog script location")
print(run("ls ~/uni-activity/scripts/ 2>/dev/null | head -20; find ~ -maxdepth 2 -name '*watchdog*' 2>/dev/null"))
print()
print("[7] proot distro available?")
print(run("command -v proot-distro && proot-distro list 2>/dev/null | grep -A2 ubuntu | head -6 || echo NO-PROOT"))

ssh.close()