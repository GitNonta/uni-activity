#!/usr/bin/env python3
"""E2E queue test over Valkey :6380 — restart BOTH workers (S1 + S2 share the queue)."""

import time

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")
S2 = ("192.168.1.140", "u0_a135", "A23457")

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
    s1 = connect(*S1)
    r1 = make_runner(s1)
    s2 = connect(*S2)
    r2 = make_runner(s2)

    print("[0] exception of most recent failed job")
    print(r1(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "\\$f = DB::table('failed_jobs')->latest('id')->first(); "
        "echo \\$f ? substr(\\$f->exception, 0, 200) : 'none';"
        '" 2>&1 | tail -3'
    ))

    print("\n[1] deploy test job + regenerate autoload on S1")
    print(r1(JOB))

    print("\n[2] restart BOTH workers (shared queue consumers)")
    print(r1("pkill -f 'artisan queue:work' ; echo killed-s1"))
    print(r2("pkill -f 'artisan queue:work' ; echo killed-s2"))
    time.sleep(15)
    print("S1 worker:", r1("pgrep -f 'artisan queue:work' >/dev/null && echo UP || echo NOT-YET"))
    print("S2 worker:", r2("pgrep -f 'artisan queue:work' >/dev/null && echo UP || echo NOT-YET"))

    print("\n[3] dispatch via Valkey queue connection (:6380)")
    print(r1(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "\\$q = Illuminate\\Support\\Facades\\Queue::connection('redis'); "
        "\\$q->push(new \\App\\Jobs\\ValkeyPipelineTestJob()); "
        "echo 'size_after='.\\$q->size('default');"
        '" 2>&1 | tail -1'
    ))

    print("\n[4] wait for either worker to consume ...")
    result = "NOT-PROCESSED"
    for _ in range(12):
        time.sleep(5)
        out = r1("cat ~/uni-activity/storage/logs/valkey_pipeline_test.txt 2>/dev/null")
        if out.startswith("PROCESSED"):
            result = out
            break
    print(result)

    print("\n[5] cleanup test artifacts + restore autoload")
    print(r1(
        "rm -f ~/uni-activity/app/Jobs/ValkeyPipelineTestJob.php "
        "~/uni-activity/storage/logs/valkey_pipeline_test.txt "
        "&& cd ~/uni-activity && composer dump-autoload -o 2>&1 | tail -1 && echo CLEANED"
    ))

    print("\n[6] purge failed rows caused by this test")
    print(r1(
        'cd ~/uni-activity && php artisan tinker --execute="'
        "\\$n = DB::table('failed_jobs')->where('exception', 'like', '%ValkeyPipelineTestJob%')->delete(); "
        "echo 'purged=' . \\$n . ' failed_now=' . DB::table('failed_jobs')->count();"
        '" 2>&1 | tail -1'
    ))

    print("\n[7] final cluster state")
    print(r1("pgrep -a valkey-server"))
    print(r1("pgrep -f 'watch_valkey[.]sh' >/dev/null && echo WATCHDOG-RUNNING || echo WATCHDOG-DOWN"))
    print("S1 worker:", r1("pgrep -f 'artisan queue:work' >/dev/null && echo UP || echo DOWN"))
    print("S2 worker:", r2("pgrep -f 'artisan queue:work' >/dev/null && echo UP || echo DOWN"))

    s1.close()
    s2.close()
    print()
    print("=" * 64)
    if result.startswith("PROCESSED"):
        print("QUEUE PIPELINE OVER VALKEY :6380 — PASS (" + result.splitlines()[0] + ")")
    else:
        print("QUEUE PIPELINE OVER VALKEY :6380 — STILL INCONCLUSIVE")
    print("=" * 64)


if __name__ == "__main__":
    main()