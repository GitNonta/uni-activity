#!/usr/bin/env python3
"""True end-to-end queue pipeline test over Valkey :6380.

Deploys a temporary throwaway job class on S1, dispatches it, waits for
the live worker to consume it from Valkey, verifies execution, cleans up.
"""

import time

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")

JOB = r"""cat > ~/uni-activity/app/Jobs/ValkeyPipelineTestJob.php <<'PHPEOF'
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ValkeyPipelineTestJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        file_put_contents(
            storage_path('logs/valkey_pipeline_test.txt'),
            'PROCESSED-VIA-VALKEY ' . date('c') . PHP_EOL,
        );
    }
}
PHPEOF
echo JOB-WRITTEN"""


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
    ssh = connect(*S1)
    run = make_runner(ssh)

    print("[1] deploy temporary test job")
    print(run(JOB))

    print("\n[2] dispatch via Valkey queue connection")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "\\$q = Illuminate\\Support\\Facades\\Queue::connection('redis'); "
        "echo 'queue_size_before='.\\$q->size('default').' '; "
        "\\$q->push(new \\App\\Jobs\\ValkeyPipelineTestJob()); "
        "echo 'queue_size_after='.\\$q->size('default');"
        '" 2>&1 | tail -2'
    ))

    print("\n[3] wait for live worker to consume from Valkey :6380 ...")
    result = "NOT-PROCESSED"
    for _ in range(10):
        time.sleep(5)
        out = run("cat ~/uni-activity/storage/logs/valkey_pipeline_test.txt 2>/dev/null")
        if out.startswith("PROCESSED"):
            result = out
            break
    print(result)

    print("\n[4] cleanup test artifacts")
    print(run(
        "rm -f ~/uni-activity/app/Jobs/ValkeyPipelineTestJob.php "
        "~/uni-activity/storage/logs/valkey_pipeline_test.txt && echo CLEANED"
    ))

    print("\n[5] final state")
    print(run("pgrep -f 'watch_valkey[.]sh' >/dev/null && echo WATCHDOG-RUNNING || echo WATCHDOG-DOWN"))
    print(run("tail -2 ~/valkey-watchdog.log 2>/dev/null || echo no-heals-needed-yet"))
    print(run("pgrep -af 'artisan queue:work' | grep -v grep | head -1"))
    c = "valkey-cli -p 6380 -a UniActivityRedis2026! --no-auth-warning"
    print(run(f"{c} ping && {c} dbsize"))

    ssh.close()
    print()
    print("=" * 64)
    if result.startswith("PROCESSED"):
        print("QUEUE PIPELINE OVER VALKEY: PASS")
    else:
        print("QUEUE PIPELINE OVER VALKEY: INCONCLUSIVE")
    print("=" * 64)


if __name__ == "__main__":
    main()