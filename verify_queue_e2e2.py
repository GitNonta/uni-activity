#!/usr/bin/env python3
"""E2E queue test over Valkey :6380 — with proper autoload regeneration."""

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
cd ~/uni-activity && composer dump-autoload -o 2>&1 | tail -1 && echo JOB-WRITTEN"""


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


def main() -> None:
    ssh = connect(*S1)
    run = make_runner(ssh)

    print("[failed jobs before]")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "echo 'failed_before=' . DB::table('failed_jobs')->count();"
        '" 2>&1 | tail -1'
    ))

    print("\n[1] deploy test job + regenerate autoload")
    print(run(JOB))

    print("\n[2] dispatch via Valkey queue connection (:6380)")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "\\$q = Illuminate\\Support\\Facades\\Queue::connection('redis'); "
        "echo 'size_before='.\\$q->size('default').' '; "
        "\\$q->push(new \\App\\Jobs\\ValkeyPipelineTestJob()); "
        "echo 'size_after='.\\$q->size('default');"
        '" 2>&1 | tail -1'
    ))

    print("\n[3] wait for live worker to consume ...")
    result = "NOT-PROCESSED"
    for _ in range(12):
        time.sleep(5)
        out = run("cat ~/uni-activity/storage/logs/valkey_pipeline_test.txt 2>/dev/null")
        if out.startswith("PROCESSED"):
            result = out
            break
    print(result)

    print("\n[4] cleanup test artifacts + restore autoload")
    print(run(
        "rm -f ~/uni-activity/app/Jobs/ValkeyPipelineTestJob.php "
        "~/uni-activity/storage/logs/valkey_pipeline_test.txt "
        "&& cd ~/uni-activity && composer dump-autoload -o 2>&1 | tail -1 && echo CLEANED"
    ))

    print("\n[5] purge failed rows caused by this test")
    print(run(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "\\$n = DB::table('failed_jobs')->where('exception', 'like', '%ValkeyPipelineTestJob%')->delete(); "
        "echo 'purged=' . \\$n . ' failed_now=' . DB::table('failed_jobs')->count();"
        '" 2>&1 | tail -1'
    ))

    ssh.close()
    print()
    print("=" * 64)
    if result.startswith("PROCESSED"):
        print("QUEUE PIPELINE OVER VALKEY :6380 — PASS (" + result.splitlines()[0] + ")")
    else:
        print("QUEUE PIPELINE OVER VALKEY :6380 — STILL INCONCLUSIVE")
    print("=" * 64)


if __name__ == "__main__":
    main()