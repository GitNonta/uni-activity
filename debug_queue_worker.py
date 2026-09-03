#!/usr/bin/env python3
"""Debug why dispatched test job wasn't consumed."""

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

    print("[composer autoload config]")
    print(run("grep -A8 '\"config\"' ~/uni-activity/composer.json | head -12"))
    print(run("ls ~/uni-activity/vendor/composer/autoload_classmap.php >/dev/null && grep -c 'App\\\\Jobs' ~/uni-activity/vendor/composer/autoload_classmap.php"))

    print("\n[queue depth now]")
    print(run("valkey-cli -p 6380 -a UniActivityRedis2026! --no-auth-warning LLEN queues:default"))
    print(run("valkey-cli -p 6380 -a UniActivityRedis2026! --no-auth-warning KEYS 'queues:*' | head -10"))

    print("\n[worker alive + recent output]")
    print(run("pgrep -af 'artisan queue:work' | grep -v grep || echo NO-WORKER"))
    print(run("tail -15 ~/uni-activity/storage/logs/laravel.log 2>/dev/null | grep -iE 'error|exception|valkeypipelinetest' | tail -5 || echo no-recent-errors"))

    print("\n[failed jobs table]")
    print(run(
        "cd ~/uni-activity && php artisan tinker --execute=\""
        "echo 'failed=' . DB::table('failed_jobs')->count(); "
        "\\$f = DB::table('failed_jobs')->latest('id')->first(); "
        "if (\\$f) { echo ' last=' . substr(\\$f->exception, 0, 160); }"
        '" 2>&1 | tail -2'
    ))

    print("\n[worker tmux session output]")
    print(run("tmux capture-pane -t queue -p 2>/dev/null | tail -15 || echo no-tmux-queue-session"))

    ssh.close()


if __name__ == "__main__":
    main()