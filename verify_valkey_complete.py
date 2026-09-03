#!/usr/bin/env python3
"""End-to-end verification of the full-Valkey switchover on S1 & S2.

Checks:
  1. No legacy redis-server processes / binaries in use
  2. Both Valkey instances healthy (PING, persistence, auth)
  3. Laravel talks to Valkey: PING, cache put/get, atomic lock, queue size
  4. Queue workers alive on both nodes
"""

import paramiko

LINE = "=" * 70

TINKER_S1 = r"""
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
$ok = true;
try {
    $pong = Redis::connection('default')->ping();
    echo 'redis_ping=' . ($pong ? 'OK' : 'FAIL') . PHP_EOL;
} catch (\Throwable $e) { echo 'redis_ping=FAIL: ' . $e->getMessage() . PHP_EOL; $ok = false; }
try {
    Cache::store('redis')->put('valkey_switch_test', 'hello-from-laravel', 120);
    $v = Cache::store('redis')->get('valkey_switch_test');
    echo 'cache_put_get=' . ($v === 'hello-from-laravel' ? 'OK' : 'FAIL') . PHP_EOL;
    Cache::store('redis')->forget('valkey_switch_test');
} catch (\Throwable $e) { echo 'cache_put_get=FAIL: ' . $e->getMessage() . PHP_EOL; $ok = false; }
try {
    $lock = Cache::lock('valkey_switch_lock', 15);
    $got = $lock->get();
    echo 'atomic_lock=' . ($got ? 'OK' : 'FAIL') . PHP_EOL;
    if ($got) { $lock->release(); }
} catch (\Throwable $e) { echo 'atomic_lock=FAIL: ' . $e->getMessage() . PHP_EOL; $ok = false; }
try {
    echo 'queue_connection=' . config('queue.default') . PHP_EOL;
    echo 'queue_size_default=' . Queue::size('default') . PHP_EOL;
} catch (\Throwable $e) { echo 'queue_check=FAIL: ' . $e->getMessage() . PHP_EOL; $ok = false; }
echo 'cache_store=' . config('cache.default') . PHP_EOL;
echo 'session_driver=' . config('session.driver') . PHP_EOL;
echo 'redis_client=' . config('database.redis.client') . PHP_EOL;
echo $ok ? 'TINKER_ALL_OK' : 'TINKER_HAS_FAILURES';
"""

TINKER_S2 = TINKER_S1  # S2 .env points REDIS_HOST at S1 over LAN


def connect(host, port, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, port, user, pw, timeout=20)
    return ssh


def run(ssh, cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


def valkey_checks(ssh, label):
    print()
    print("[%s: valkey health]" % label)
    for port in (6379, 6380):
        ping = run(ssh, "valkey-cli -h 127.0.0.1 -p %d -a 'UniActivityRedis2026!' "
                        "--no-auth-warning PING 2>&1" % port)
        aof = run(ssh, "valkey-cli -h 127.0.0.1 -p %d -a 'UniActivityRedis2026!' "
                       "--no-auth-warning INFO persistence 2>&1 | grep -E '^aof_enabled|^aof_last_write_status'"
                       % port).replace("\n", ", ")
        ver = run(ssh, "valkey-cli -h 127.0.0.1 -p %d -a 'UniActivityRedis2026!' "
                       "--no-auth-warning INFO server 2>&1 | grep '^valkey_version'" % port)
        print("  :%d PING=%s | %s | %s" % (port, ping, ver, aof))


def main():
    # ── S1 ────────────────────────────────────────────────────────────────
    print(LINE)
    print("S1 (192.168.1.222) — MASTER")
    print(LINE)
    s1 = connect("192.168.1.222", 8022, "u0_a175", "A2345678")

    print("[legacy redis-server processes]")
    print("  " + run(s1, "ps aux | grep '[r]edis-server' || echo NONE-RUNNING"))
    print("[redis package status]")
    print("  " + run(s1, "dpkg -l redis 2>/dev/null | tail -1"))
    print("[valkey-server processes]")
    print("  " + run(s1, "pgrep -af valkey-server | head -4"))

    valkey_checks(s1, "S1")

    print()
    print("[S1: clear laravel config/route caches]")
    print(run(s1, "cd ~/uni-activity && php artisan config:clear && php artisan route:clear"))

    print()
    print("[S1: Laravel functional test via tinker]")
    print(run(s1, "cd ~/uni-activity && php artisan tinker --execute=%s" % TINKER_S1))

    print()
    print("[S1: queue worker]")
    print("  " + run(s1, "pgrep -af 'artisan queue:work' | head -2 || echo NOT-RUNNING"))
    s1.close()

    # ── S2 ────────────────────────────────────────────────────────────────
    print()
    print(LINE)
    print("S2 (192.168.1.140) — WORKER/AI")
    print(LINE)
    try:
        s2 = connect("192.168.1.140", 8022, "u0_a135", "A23457")
    except Exception as exc:
        print("[FAIL] SSH to S2 failed: %s" % exc)
        return

    print("[reach S1 valkey ports]")
    for port in (6379, 6380):
        r = run(s2, "timeout 3 sh -c 'echo > /dev/tcp/192.168.1.222/%d' 2>/dev/null "
                    "&& echo REACHABLE || echo UNREACHABLE" % port)
        print("  :%d %s" % (port, r))

    valkey_checks(s2, "S2")

    print()
    print("[S2: clear laravel config/route caches]")
    print(run(s2, "cd ~/uni-activity && php artisan config:clear && php artisan route:clear"))

    print()
    print("[S2: Laravel functional test via tinker (against S1 Valkey)]")
    print(run(s2, "cd ~/uni-activity && php artisan tinker --execute=%s" % TINKER_S2))

    print()
    print("[S2: queue worker]")
    print("  " + run(s2, "pgrep -af 'artisan queue:work' | head -2 || echo NOT-RUNNING"))
    s2.close()

    print()
    print(LINE)
    print("FULL-VALKEY VERIFICATION COMPLETE")
    print(LINE)


if __name__ == "__main__":
    main()