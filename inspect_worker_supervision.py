#!/usr/bin/env python3
"""Inspect how the S1 queue worker is supervised before restarting it."""

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")


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


def main() -> None:
    ssh = connect(*S1)
    run = make_runner(ssh)

    print("[tmux sessions]")
    print(run("tmux ls 2>/dev/null || echo no-tmux"))

    print("\n[svc_queue.sh]")
    print(run("cat ~/svc_queue.sh 2>/dev/null || echo NO-SVC-QUEUE"))

    print("\n[worker parent chain]")
    print(run("ps -o pid,ppid,etime,cmd -p $(pgrep -f 'artisan queue:work' | head -1) 2>/dev/null"))
    print(run("for p in $(pgrep -f 'artisan queue:work'); do echo \"pid=$p ppid=$(awk '{print $4}' /proc/$p/stat)\"; done"))

    print("\n[failed job exceptions (last 3)]")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "DB::table('failed_jobs')->latest('id')->take(3)->get()"
        "->each(fn(\\$f) => print(substr(\\$f->exception, 0, 120) . PHP_EOL . '---' . PHP_EOL));"
        '" 2>&1 | tail -12'
    ))

    ssh.close()


if __name__ == "__main__":
    main()