#!/usr/bin/env python3
"""Start S2 workers via detached launcher script; verify."""

import time

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[current state]")
print(run("pgrep -af 'artisan serve' | grep -v pgrep || echo none"))
print()

print("[write launcher]")
launcher = (
    "#!/data/data/com.termux/files/usr/bin/bash" + chr(10)
    + "cd $HOME/uni-activity || exit 1" + chr(10)
    + "for p in 8000 8002 8003; do" + chr(10)
    + "  if ! (echo > /dev/tcp/127.0.0.1/$p) 2>/dev/null; then" + chr(10)
    + "    setsid nohup php artisan serve --host 0.0.0.0 --port $p"
    + " > serve-$p.log 2>&1 < /dev/null &" + chr(10)
    + "  fi" + chr(10)
    + "done" + chr(10)
    + "echo launcher-done" + chr(10)
)
sftp = ssh.open_sftp()
with sftp.open("/data/data/com.termux/files/home/start_web_workers_s2.sh", "w") as f:
    f.write(launcher)
sftp.chmod("/data/data/com.termux/files/home/start_web_workers_s2.sh", 0o755)
sftp.close()
print(run("bash ~/start_web_workers_s2.sh"))
print()

print("[wait 10s]")
time.sleep(10)
print("[workers]")
print(run("pgrep -af 'artisan serve' | grep -v pgrep"))
print()

print("[verify x4 each]")
for port in ("8000", "8002", "8003"):
    print(run(f"for i in 1 2 3 4; do curl -s -o /dev/null -w '%{{http_code}} ' "
              f"-m 10 http://127.0.0.1:{port}/; done; echo"))
print()

print("[serve log tail]")
print(run("tail -3 ~/uni-activity/serve-8000.log"))
print()

ssh.close()