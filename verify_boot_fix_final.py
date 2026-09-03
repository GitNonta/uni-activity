#!/usr/bin/env python3
"""Final check: detached startup completes; nothing stays attached to a tty."""

import paramiko


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


def check(host, user, pw, label, marker):
    print("#" * 62)
    print("#", label)
    print("#" * 62)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)

    print("[wait for startup completion]")
    print(run(
        "for i in $(seq 1 45); do "
        "tail -3 ~/boot-cluster.log | grep -q 'safe to close this terminal' && break; sleep 2; done; "
        "tail -4 ~/boot-cluster.log"
    ))
    print()

    print("[no start-cluster process left running (script exits)]")
    print(run("pgrep -af '[.]termux/boot/start-cluster[.]sh' || echo SCRIPT-EXITED-CLEANLY"))
    print()

    print("[all long-running services are detached (tty = ?)]")
    print(run(
        "ps -eo stat,tty,args | grep -E 'valkey-server|artisan serve|artisan queue:work|watch_' "
        "| grep -v grep | awk '{print $1, $2}' | sort | uniq -c"
    ))
    print()
    ssh.close()


check("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)", "Phone 1")
print()
check("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)", "Phone 2")
print()
print("ALL CHECKS DONE")