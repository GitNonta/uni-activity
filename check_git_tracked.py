#!/usr/bin/env python3
"""Check which monitor files are git-tracked (auto-sync wipes uncommitted edits)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[tracked: collectors.py]")
print(run("cd ~/uni-activity && git ls-files py/monitor/collectors.py"))
print("[tracked: monitor-ui/dist]")
print(run("cd ~/uni-activity && git ls-files monitor-ui/dist | head"))
print()

print("[git-sync log tail]")
print(run("tail -6 ~/uni-activity/storage/logs/git-sync.log 2>/dev/null || echo none"))
print()

print("[auto_sync interval in threads.py]")
print(run("sed -n '40,70p' ~/uni-activity/py/monitor/threads.py"))
print()

ssh.close()