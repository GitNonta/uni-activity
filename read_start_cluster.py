#!/usr/bin/env python3
"""Read start-cluster.sh supervision logic for reverb restart loop."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[start-cluster.sh full]")
print(run("cat ~/.termux/boot/start-cluster.sh"))
print()

print("[reverb-related processes]")
print(run("ps -ef | grep -i reverb | grep -v grep | head -10"))
print()

print("[recent reverb spawn attempts - who is parent?]")
print(run("ls -la ~/uni-activity/storage/logs/reverb.log && tail -5 ~/uni-activity/storage/logs/reverb.log"))
print()

ssh.close()