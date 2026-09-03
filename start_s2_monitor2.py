#!/usr/bin/env python3
"""Start S2 monitor server via launcher script + verify."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[write launcher]")
print(run(
    "cat > ~/start_monitor_s2.sh <<'EOF'" + chr(10)
    + "#!/data/data/com.termux/files/usr/bin/sh" + chr(10)
    + "cd $HOME/uni-activity" + chr(10)
    + "exec python -u py/monitor_server.py >> $HOME/monitor_server.log 2>&1" + chr(10)
    + "EOF" + chr(10)
    + "chmod +x ~/start_monitor_s2.sh && echo launcher-ok"
))
print()

print("[launch detached]")
print(run("nohup setsid ~/start_monitor_s2.sh < /dev/null > /dev/null 2>&1 & echo launched"))
time.sleep(6)

print("[pid]")
print(run("pgrep -af 'monitor_serve[r].py' || echo not-running"))
print()

print("[log tail]")
print(run("tail -5 ~/monitor_server.log 2>/dev/null || echo no-log"))
print()

q = chr(34)
print("[http status]")
print(run("curl -s -o /dev/null -w '%{http_code}' -m 3 http://127.0.0.1:9999/"))
print("[index bundle]")
print(run("curl -s -m 3 http://127.0.0.1:9999/ | grep -o " + q + "assets/index-[A-Za-z0-9._-]*" + q))
print()

ssh.close()