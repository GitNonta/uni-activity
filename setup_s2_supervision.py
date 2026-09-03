#!/usr/bin/env python3
"""Install termux-services supervision + internal sshd watchdog on S2."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=180):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[1] Install termux-services (runit)")
print(run(
    "dpkg -s termux-services >/dev/null 2>&1 && echo ALREADY-INSTALLED "
    "|| yes | pkg install -y termux-services 2>&1 | tail -4"
))
print()

print("[2] Start runsvdir if not running")
print(run(
    "pgrep -x runsvdir >/dev/null && echo RUNSVDIR-ALIVE "
    "|| (setsid nohup runsvdir $PREFIX/var/service > /dev/null 2>&1 < /dev/null & sleep 3; pgrep -a runsvdir || echo STILL-NOT-RUNNING)"
))
print()

print("[3] Enable sshd under runit")
print(run("rm -f $PREFIX/var/service/sshd/down; $PREFIX/bin/sv up sshd 2>&1; sleep 1; $PREFIX/bin/sv status sshd 2>&1"))
print()

print("[4] Create internal sshd watchdog ~/watch_sshd.sh")
watchdog = (
    "#!/data/data/com.termux/files/usr/bin/bash\n"
    "# Auto-restart sshd if it dies (runs forever)\n"
    "LOG=\"$HOME/sshd-watchdog.log\"\n"
    "while true; do\n"
    "    if ! pgrep -x sshd >/dev/null 2>&1; then\n"
    "        sshd && echo \"[$(date '+%F %T')] sshd restarted\" >> \"$LOG\"\n"
    "    fi\n"
    "    sleep 30\n"
    "done\n"
)
lines = watchdog.split("\n")
body = "cat > $HOME/watch_sshd.sh << 'WEOF'\n" + "\n".join(lines) + "\nWEOF\nchmod +x $HOME/watch_sshd.sh && echo WATCHDOG-WRITTEN"
print(run(body))
print()

print("[5] Start watchdog if not running")
print(run(
    "pgrep -f watch_sshd >/dev/null && echo ALREADY-RUNNING "
    "|| (setsid nohup bash $HOME/watch_sshd.sh > /dev/null 2>&1 < /dev/null & sleep 1; pgrep -f watch_sshd && echo STARTED)"
))
print()

print("[6] Add watchdog to boot script if missing")
print(run(
    "grep -q watch_sshd ~/.termux/boot/start-cluster.sh "
    "|| sed -i '/# Queue worker watchdog/i \\\n"
    "# SSH daemon watchdog\\\n"
    "if ! pgrep -f \"watch_sshd[.]sh\" > /dev/null 2>&1; then\\\n"
    "    setsid bash \"$HOME/watch_sshd.sh\" > /dev/null 2>&1 < /dev/null &\\\n"
    "    log \"sshd watchdog started\"\\\n"
    "fi' ~/.termux/boot/start-cluster.sh; grep -c watch_sshd ~/.termux/boot/start-cluster.sh"
))

ssh.close()