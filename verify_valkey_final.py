#!/usr/bin/env python3
"""Final comprehensive verification of the full-Valkey switch."""

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")
S2 = ("192.168.1.140", "u0_a135", "A23457")


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


def main() -> None:
    print("#" * 64)
    print("# S1 (192.168.1.222) — master datastore node")
    print("#" * 64)
    ssh = connect(*S1)
    run = make_runner(ssh)

    print("\n[packages — only valkey should remain]")
    print(run("dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $1, $2, $3}'"))
    print(run("which redis-server redis-cli 2>/dev/null || echo NO-REDIS-BINARIES"))

    print("\n[valkey processes + version]")
    print(run("pgrep -a valkey-server"))
    print(run("valkey-cli -p 6379 -a UniActivityRedis2026! --no-auth-warning INFO server | grep -E '^valkey_version'"))

    print("\n[health: ping / persistence / memory per instance]")
    print(run(
        'for p in 6379 6380; do '
        'C="valkey-cli -p $p -a UniActivityRedis2026! --no-auth-warning"; '
        'echo "port $p: $($C ping) ao=$($C CONFIG GET appendonly | tail -1) '
        'fsync=$($C CONFIG GET appendfsync | tail -1) '
        'maxmem=$($C CONFIG GET maxmemory | tail -1) '
        'policy=$($C CONFIG GET maxmemory-policy | tail -1) '
        'keys=$($C dbsize)"; done'
    ))

    print("\n[watchdog running?]")
    print(run("pgrep -f 'watch_valkey[.]sh' >/dev/null && echo WATCHDOG-RUNNING || echo WATCHDOG-NOT-RUNNING"))
    print(run("tail -3 ~/valkey-watchdog.log 2>/dev/null || echo no-log-yet"))

    print("\n[boot wiring]")
    print(run("grep -c 'watch_valkey' ~/.termux/boot/start-cluster.sh && grep -m1 'port 6379' ~/.termux/boot/start-cluster.sh | head -c 200; echo"))

    print("\n[Laravel over Valkey: cache + lock + session]")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "Cache::store('redis')->put('fv_cache','ok',60); "
        "echo 'cache='.Cache::store('redis')->get('fv_cache'); "
        "\\$l=Cache::store('redis')->lock('fv_lock',10); \\$l->block(5); "
        "echo ' lock=acquired'; \\$l->release(); "
        "echo ' session='.config('session.driver');"
        '" 2>&1 | tail -2'
    ))

    print("\n[queue dispatch -> processed via :6380]")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "echo 'queued='.Illuminate\\Support\\Facades\\Queue::push(function () { file_put_contents(storage_path('logs/fv_queue_test.txt'), date('c')); });"
        '" 2>&1 | tail -2; sleep 12; '
        'cat ~/uni-activity/storage/logs/fv_queue_test.txt 2>/dev/null && rm -f ~/uni-activity/storage/logs/fv_queue_test.txt || echo QUEUE-JOB-NOT-PROCESSED-YET'
    ))
    ssh.close()

    print()
    print("#" * 64)
    print("# S2 (192.168.1.140) — worker node (remote Valkey client)")
    print("#" * 64)
    ssh = connect(*S2)
    run = make_runner(ssh)

    print("\n[no local redis/valkey server should be running]")
    print(run("pgrep -a 'redis-server|valkey-server' || echo NO-LOCAL-DATASTORE-CORRECT"))

    print("\n[cross-node status]")
    print(run("bash ~/valkey-status.sh"))

    print("\n[Laravel cache round-trip via S1 Valkey]")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "Cache::store('redis')->put('fv_s2','ok',60); "
        "echo 'cache='.Cache::store('redis')->get('fv_s2');"
        '" 2>&1 | tail -2'
    ))

    print("\n[queue worker]")
    print(run("pgrep -af 'artisan queue:work' | head -1 || echo NO-WORKER"))
    ssh.close()

    print()
    print("=" * 64)
    print("FINAL VERIFICATION DONE")
    print("=" * 64)


if __name__ == "__main__":
    main()