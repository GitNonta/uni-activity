#!/usr/bin/env python3
"""Fixed end-to-end Valkey verification (base64 payload + real socket tests)."""

import base64

import paramiko

LINE = "=" * 70

PHP_TEST = r"""
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
$fail = 0;
try { $p = Redis::connection('default')->ping(); echo 'redis_ping=' . ($p ? 'OK' : 'FAIL') . PHP_EOL; } catch (\Throwable $e) { $fail++; echo 'redis_ping=FAIL: ' . $e->getMessage() . PHP_EOL; }
try { Cache::store('redis')->put('valkey_switch_test', 'hello-from-laravel', 120); $v = Cache::store('redis')->get('valkey_switch_test'); echo 'cache_put_get=' . ($v === 'hello-from-laravel' ? 'OK' : 'FAIL') . PHP_EOL; Cache::store('redis')->forget('valkey_switch_test'); } catch (\Throwable $e) { $fail++; echo 'cache_put_get=FAIL: ' . $e->getMessage() . PHP_EOL; }
try { $l = Cache::lock('valkey_switch_lock', 15); $g = $l->get(); echo 'atomic_lock=' . ($g ? 'OK' : 'FAIL') . PHP_EOL; if ($g) { $l->release(); } } catch (\Throwable $e) { $fail++; echo 'atomic_lock=FAIL: ' . $e->getMessage() . PHP_EOL; }
try { echo 'queue_connection=' . config('queue.default') . ' queue_size=' . Queue::size('default') . PHP_EOL; } catch (\Throwable $e) { $fail++; echo 'queue_check=FAIL: ' . $e->getMessage() . PHP_EOL; }
echo 'cache_store=' . config('cache.default') . ' session_driver=' . config('session.driver') . ' client=' . config('database.redis.client') . PHP_EOL;
echo $fail === 0 ? 'TINKER_ALL_OK' : ('TINKER_FAILURES=' . $fail);
"""

B64 = base64.b64encode(PHP_TEST.encode()).decode()

SOCK_TEST = (
    "php -r "
    "'foreach ([6379, 6380] as $p) "
    "{ $s = @fsockopen(\"192.168.1.222\", $p, $e, $m, 3); "
    "echo \":$p \" . ($s ? \"REACHABLE\" : \"UNREACHABLE($m)\") . PHP_EOL; "
    "if ($s) fclose($s); }'"
)


def connect(host, port, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, port, user, pw, timeout=20)
    return ssh


def run(ssh, cmd, timeout=150):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


def tinker_cmd():
    return ("cd ~/uni-activity && printf %s " + B64 +
            " | base64 -d > $HOME/vk_test.php"
            " && timeout 120 php artisan tinker --execute=\"$(cat $HOME/vk_test.php)\"")


def main():
    # ── S1 ────────────────────────────────────────────────────────────────
    print(LINE)
    print("S1 (192.168.1.222) — MASTER")
    print(LINE)
    s1 = connect("192.168.1.222", 8022, "u0_a175", "A2345678")

    print("[S1: Laravel functional test]")
    print(run(s1, tinker_cmd()))
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

    print("[S2 -> S1 valkey reachability (real sockets)]")
    print(run(s2, SOCK_TEST))

    print()
    print("[S2: Laravel functional test (against S1 Valkey)]")
    print(run(s2, tinker_cmd()))
    s2.close()

    print()
    print(LINE)
    print("VERIFICATION COMPLETE")
    print(LINE)


if __name__ == "__main__":
    main()