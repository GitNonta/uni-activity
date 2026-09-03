#!/usr/bin/env python3
"""Verify full-Valkey switch state on both nodes."""

import paramiko


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


def check_s1():
    print("#" * 62)
    print("# S1 (192.168.1.222)")
    print("#" * 62)
    ssh = connect("192.168.1.222", "u0_a175", "A2345678")
    run = make_runner(ssh)

    print("[redis package state]")
    print(run("dpkg -l redis 2>/dev/null | tail -1; dpkg -s redis 2>/dev/null | grep -E '^Status' || echo PURGED"))
    print()

    print("[valkey processes]")
    print(run("pgrep -a valkey-server || echo NONE"))
    print()

    print("[ping + appendonly + dbsize]")
    print(run(
        'for p in 6379 6380; do '
        'echo "port $p: $(valkey-cli -p $p -a UniActivityRedis2026! --no-auth-warning ping) '
        'ao=$(valkey-cli -p $p -a UniActivityRedis2026! --no-auth-warning CONFIG GET appendonly | tail -1) '
        'keys=$(valkey-cli -p $p -a UniActivityRedis2026! --no-auth-warning dbsize)"; done'
    ))
    print()

    print("[watchdog script + process]")
    print(run("ls -la ~/watch_valkey.sh 2>/dev/null && head -3 ~/watch_valkey.sh; "
              "pgrep -f 'watch_valkey[.]sh' >/dev/null && echo WATCHDOG-RUNNING || echo WATCHDOG-NOT-RUNNING"))
    print()

    print("[boot script wiring]")
    print(run("grep -n 'watch_valkey' ~/.termux/boot/start-cluster.sh || echo NOT-IN-BOOT-SCRIPT"))
    print()

    print("[Laravel cache round-trip]")
    s1_tinker = ('cd ~/uni-activity && php artisan tinker --execute="'
                 "Cache::store('redis')->put('valkey_verify_s1','ok',60); "
                 "echo 'cache='.Cache::store('redis')->get('valkey_verify_s1');"
                 '" 2>&1 | tail -2')
    print(run(s1_tinker))
    print()
    ssh.close()


def check_s2():
    print()
    print("#" * 62)
    print("# S2 (192.168.1.140)")
    print("#" * 62)
    ssh = connect("192.168.1.140", "u0_a135", "A23457")
    run = make_runner(ssh)

    print("[valkey-cli installed?]")
    print(run("command -v valkey-cli && valkey-cli --version | head -1 || echo NOT-INSTALLED"))
    print()

    print("[remote status check]")
    print(run("bash ~/valkey-status.sh 2>/dev/null || echo NO-STATUS-SCRIPT"))
    print()

    print("[stale local scripts removed?]")
    print(run("ls ~/start-valkey.sh 2>/dev/null && echo STILL-PRESENT || echo CLEAN"))
    print()

    print("[Laravel cache round-trip via S1]")
    s2_tinker = ('cd ~/uni-activity && php artisan tinker --execute="'
                 "Cache::store('redis')->put('valkey_verify_s2','ok',60); "
                 "echo 'cache='.Cache::store('redis')->get('valkey_verify_s2');"
                 '" 2>&1 | tail -2')
    print(run(s2_tinker))
    print()

    print("[queue worker alive?]")
    print(run("pgrep -f 'artisan queue:work' >/dev/null && echo WORKER-ALIVE || echo NO-WORKER"))
    print()
    ssh.close()


check_s1()
check_s2()
print()
print("VERIFY DONE")