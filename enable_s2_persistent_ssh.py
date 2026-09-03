#!/usr/bin/env python3
"""Enable persistent sshd on S2 via runit service (continued)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[3] Write log/run via shell heredoc")
heredoc = (
    "mkdir -p $PREFIX/var/service/sshd/log && "
    "cat > $PREFIX/var/service/sshd/log/run << 'EOF'\n"
    "#!/data/data/com.termux/files/usr/bin/sh\n"
    'mkdir -p "$HOME/svlog/sshd"\n'
    'exec svlogd "$HOME/svlog/sshd"\n'
    "EOF\n"
    "chmod +x $PREFIX/var/service/sshd/log/run && echo LOG-RUN-WRITTEN"
)
print(run(heredoc))
print()

print("[4] Bring service up")
print(run("sv up sshd 2>&1; sleep 1; sv status sshd 2>&1"))
print()

print("[5] Verify sshd listening")
print(run("pgrep -a sshd | head -3"))
print()

print("[6] Add idempotent sshd+wake-lock guard to .profile as extra safety")
guard = (
    "if ! pgrep -x sshd >/dev/null 2>&1; then sshd; fi; "
    "termux-wake-lock >/dev/null 2>&1; true"
)
check = run("grep -q 'pgrep -x sshd' ~/.profile 2>/dev/null && echo YES || echo NO")
if check == "YES":
    print("ALREADY-PRESENT")
else:
    cmd = "printf '\\n# keep SSH alive\\n%s\\n' >> ~/.profile && echo ADDED" % guard
    print(run(cmd))

print()
print("[7] Confirm down marker absent")
print(run("ls $PREFIX/var/service/sshd/ ; test -f $PREFIX/var/service/sshd/down && echo DOWN-STILL-EXISTS || echo NO-DOWN-MARKER"))

ssh.close()