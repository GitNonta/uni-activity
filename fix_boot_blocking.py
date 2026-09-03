#!/usr/bin/env python3
"""Fix `bash start-cluster.sh` blocking the terminal.

Problem: running start-cluster.sh in the foreground keeps the Termux
session busy for minutes (silent network wait + sequential startups),
so the user cannot safely close the terminal / turn off the screen.

Fix:
 1. Boot script now prints live progress (log() tees to stdout).
 2. Network wait shows a countdown message.
 3. Completion hint tells the user the terminal can be closed.
 4. New ~/start-cluster.sh wrapper launches everything DETACHED and
    returns in ~1 second - terminal is free immediately.
"""

import paramiko

NL = chr(10)


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=180):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


def write_remote_file(run, path, content):
    body = (
        "cat > " + path + " << 'XEOF'" + NL
        + content.rstrip(NL) + NL
        + "XEOF" + NL
        + "chmod +x " + path + " && echo WRITTEN:" + path
    )
    print(run(body))


# Patch script sent to remote python3 via stdin (avoids shell quoting issues).
PATCHER = r"""
import pathlib

p = pathlib.Path.home() / ".termux/boot/start-cluster.sh"
s = p.read_text()
orig = s
lines = s.split(chr(10))

# 1. log() prints live progress to the terminal as well as the log file
new_log = 'log() { local m="[$(date ' + "'" + '+%F %T' + "'" + ')] $*"; echo "$m"; echo "$m" >> "$LOG"; }'
for i, ln in enumerate(lines):
    if ln.startswith("log() {") and ">>" in ln and "$LOG" in ln:
        lines[i] = new_log
        break

# 2. Show a message while waiting for the network (up to 120s of silence)
for i, ln in enumerate(lines):
    if "ping -c 1 -W 1 1.1.1.1" in ln:
        lines.insert(i + 1, '    [ $((i % 5)) -eq 0 ] && echo "waiting for network... ($((i*2))s)"')
        break

s = chr(10).join(lines)

# 3. Completion hint at the end of the script
hint = (
    chr(10)
    + 'echo ""' + chr(10)
    + 'echo "=== Cluster startup finished - safe to close this terminal ==="' + chr(10)
    + 'echo "Services keep running in background. Logs: ~/boot-cluster.log"' + chr(10)
)
if "safe to close this terminal" not in s:
    s = s.rstrip(chr(10)) + hint

if s != orig:
    p.write_text(s)
    print("BOOT-SCRIPT-PATCHED")
else:
    print("BOOT-SCRIPT-ALREADY-PATCHED")
"""

WRAPPER = r"""#!/data/data/com.termux/files/usr/bin/bash
# start-cluster.sh — launch cluster startup DETACHED so the terminal
# frees instantly. Safe to close Termux / turn off the screen right after.
#
# Usage:      bash ~/start-cluster.sh      (returns in ~1 second)
# Progress:   tail -f ~/boot-cluster.log
BOOT="$HOME/.termux/boot/start-cluster.sh"
LOG="$HOME/boot-cluster.log"

if pgrep -f '[.]termux/boot/start-cluster[.]sh' > /dev/null 2>&1; then
    echo "Cluster startup already running."
    echo "Follow progress:  tail -f $LOG"
    exit 0
fi

setsid nohup bash "$BOOT" >> "$LOG" 2>&1 < /dev/null &
echo "Cluster startup launched in background (pid $!)"
echo "Follow progress:  tail -f $LOG"
"""


def fix(host, user, pw, label):
    print("#" * 62)
    print("#", label)
    print("#" * 62)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)

    print("[1] Backup current boot script")
    print(run(
        "cp ~/.termux/boot/start-cluster.sh "
        "~/.termux/boot/start-cluster.sh.bak-$(date +%Y%m%d%H%M%S) "
        "&& echo BACKED-UP"
    ))
    print()

    print("[2] Patch boot script (live progress + completion hint)")
    print(run("python3 - << 'PYEOF'" + PATCHER + NL + "PYEOF"))
    print()

    print("[3] Write detached launcher ~/start-cluster.sh")
    write_remote_file(run, "$HOME/start-cluster.sh", WRAPPER)
    print()

    print("[4] Test: launcher must return immediately")
    print(run("time bash ~/start-cluster.sh"))
    print()

    print("[5] Startup progress (last 12 lines)")
    print(run("sleep 8; tail -12 ~/boot-cluster.log"))
    print()

    print("[6] Key services still alive")
    print(run(
        "pgrep -a valkey-server | head -2; "
        "pgrep -f 'artisan serve' >/dev/null && echo WEB-WORKERS-UP || echo NO-WEB-WORKERS; "
        "pgrep -f 'artisan queue:work' >/dev/null && echo QUEUE-WORKER-UP || echo NO-QUEUE-WORKER"
    ))
    print()
    ssh.close()


fix("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)")
print()
fix("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")
print()
print("FIX DONE")