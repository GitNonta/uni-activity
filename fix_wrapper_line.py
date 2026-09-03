#!/usr/bin/env python3
"""Repair the mangled setsid line in ~/start-cluster.sh on both nodes."""

import base64
import paramiko

NL = chr(10)

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

setsid nohup bash "$BOOT" >> /dev/null 2>&1 < /dev/null &
echo "Cluster startup launched in background (pid $!)"
echo "Follow progress:  tail -f $LOG"
"""


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=120):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


def repair(host, user, pw, label):
    print("#" * 62)
    print("#", label)
    print("#" * 62)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)

    b64 = base64.b64encode(WRAPPER.encode()).decode()
    print(run("echo " + b64 + " | base64 -d > ~/start-cluster.sh && chmod +x ~/start-cluster.sh && echo REWRITTEN"))
    print()

    print("[verify setsid line]")
    print(run("grep -n 'setsid nohup' ~/start-cluster.sh"))
    print()

    print("[test launcher returns immediately]")
    print(run("time bash ~/start-cluster.sh"))
    print()

    print("[services alive]")
    print(run(
        "pgrep -f 'artisan serve' >/dev/null && echo WEB-WORKERS-UP || echo NO-WEB-WORKERS; "
        "pgrep -f 'artisan queue:work' >/dev/null && echo QUEUE-WORKER-UP || echo NO-QUEUE-WORKER"
    ))
    print()
    ssh.close()


repair("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)")
print()
repair("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")
print()
print("REPAIR DONE")