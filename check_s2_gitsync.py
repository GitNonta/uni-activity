#!/usr/bin/env python3
"""Find git-pull/auto-update logic inside S2 monitor code."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[git usage in py/]")
print(run("grep -rn 'git pull\\|git fetch\\|git checkout\\|git reset\\|subprocess.*git' ~/uni-activity/py/ 2>/dev/null | grep -v Binary | head -15"))
print()

print("[auto_update_tunnel_url.py head]")
print(run("head -30 ~/uni-activity/py/auto_update_tunnel_url.py"))
print()

print("[threads.py sync/update refs]")
print(run("grep -n 'update\\|sync\\|git' ~/uni-activity/py/monitor/threads.py | head -10"))
print()

print("[shell scripts with git pull in home]")
print(run("grep -ln 'git pull' ~/*.sh ~/uni-activity/*.sh 2>/dev/null; grep -n 'git pull' ~/uni-activity/start-cluster.sh 2>/dev/null | head -5"))
print()

ssh.close()