#!/usr/bin/env python3
"""Fix sshd runit service on S2 using full paths (no multiline shell strings)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Inspect log/run symlink")
print(run("ls -la $PREFIX/var/service/sshd/log/ ; readlink -f $PREFIX/var/service/sshd/log/run"))
print()

print("[2] Recreate log/run as a real script via SFTP")
logrun_lines = [
    "#!/data/data/com.termux/files/usr/bin/sh",
    'mkdir -p "$HOME/svlog/sshd"',
    'exec svlogd "$HOME/svlog/sshd"',
]
content = chr(10).join(logrun_lines) + chr(10)
sftp = ssh.open_sftp()
target = "/data/data/com.termux/files/usr/var/service/sshd/log/run"
try:
    sftp.remove(target)
except IOError:
    pass
with sftp.open(target, "w") as f:
    f.write(content)
sftp.close()
print(run("chmod +x $PREFIX/var/service/sshd/log/run && echo LOG-RUN-WRITTEN"))
print()

print("[3] sv up with full path")
print(run("$PREFIX/bin/sv up sshd 2>&1; sleep 1; $PREFIX/bin/sv status sshd 2>&1"))
print()

print("[4] Verify sshd process")
print(run("pgrep -a sshd | head -3"))
print()

print("[5] Is runsvdir running (runit supervisor)?")
print(run("pgrep -a runsvdir || pgrep -a runsv || echo NO-RUNSVDIR"))

ssh.close()