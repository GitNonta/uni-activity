#!/usr/bin/env python3
"""Recreate the runit sshd service on S2 correctly."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Inspect current state")
print(run("ls -la $PREFIX/var/service/ | head -10"))
print(run("ls -la $PREFIX/var/service/sshd/ 2>/dev/null || echo NO-DIR"))
print()

print("[2] Recreate sshd service via SFTP")
sftp = ssh.open_sftp()
base = "/data/data/com.termux/files/usr/var/service/sshd"
try:
    sftp.mkdir(base)
except IOError:
    pass
try:
    sftp.mkdir(base + "/log")
except IOError:
    pass

run_script = "\n".join([
    "#!/data/data/com.termux/files/usr/bin/sh",
    "exec sshd -D 2>&1",
]) + "\n"
with sftp.open(base + "/run", "w") as f:
    f.write(run_script)

log_script = "\n".join([
    "#!/data/data/com.termux/files/usr/bin/sh",
    'mkdir -p "$HOME/svlog/sshd"',
    'exec svlogd "$HOME/svlog/sshd"',
]) + "\n"
with sftp.open(base + "/log/run", "w") as f:
    f.write(log_script)
sftp.close()
print(run("chmod +x $PREFIX/var/service/sshd/run $PREFIX/var/service/sshd/log/run && echo SCRIPTS-WRITTEN"))
print()

print("[3] Bring service up")
print(run("$PREFIX/bin/sv up sshd 2>&1; sleep 2; $PREFIX/bin/sv status sshd 2>&1"))
print()

print("[4] Verify sshd processes")
print(run("pgrep -a sshd | head -4"))

ssh.close()