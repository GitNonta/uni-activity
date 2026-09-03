#!/usr/bin/env python3
"""Diagnose why `bash start-cluster.sh` blocks forever in Termux."""

import paramiko


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=60):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


def diag(host, user, pw, label):
    print("#" * 62)
    print("#", label)
    print("#" * 62)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)

    print("[stuck start-cluster processes]")
    print(run("pgrep -af 'start-cluster[.]sh' || echo NONE-RUNNING"))
    print()

    print("[process tree of any stuck instance]")
    print(run(
        'for p in $(pgrep -f "start-cluster[.]sh"); do '
        'echo "== pid $p:"; ps -o pid,ppid,stat,etime,args -p $p; '
        'ps --ppid $p -o pid,stat,etime,args 2>/dev/null; done'
    ))
    print()

    print("[start_web_workers.sh content]")
    print(run("cat ~/start_web_workers.sh 2>/dev/null || echo MISSING"))
    print()

    print("[watch scripts (first lines)]")
    print(run("head -8 ~/watch_web_workers.sh ~/watch_queue_workers.sh ~/watch_sshd.sh ~/watch_s2.sh 2>/dev/null"))
    print()

    print("[foreground bash sessions holding tty]")
    print(run("ps -eo pid,ppid,stat,tty,etime,args | grep -E 'bash|zsh' | grep -v grep"))
    print()

    print("[wake locks held]")
    print(run("cat /sys/power/wake_lock 2>/dev/null || echo NO-PERM"))
    print()

    ssh.close()


diag("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)")
print()
diag("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")