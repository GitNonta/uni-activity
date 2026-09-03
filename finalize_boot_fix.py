#!/usr/bin/env python3
"""Finalize boot-blocking fix: avoid duplicate log lines and confirm
the detached startup runs to completion."""

import paramiko

NL = chr(10)


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


def finalize(host, user, pw, label, done_marker):
    print("#" * 62)
    print("#", label)
    print("#" * 62)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)

    print("[1] Wrapper: stop duplicating log lines (stdout -> /dev/null)")
    q = chr(34)
    sed_cmd = (
        "sed -i 's|>> " + q + "$LOG" + q + " 2>&1 < /dev/null &|"
        ">> /dev/null 2>&1 < /dev/null \\&|' "
        "~/start-cluster.sh && grep -n 'setsid nohup' ~/start-cluster.sh"
    )
    print(run(sed_cmd))
    print()

    print("[2] Wait for detached startup to finish")
    print(run(
        "for i in $(seq 1 30); do "
        "tail -5 ~/boot-cluster.log | grep -q '" + done_marker + "' && break; sleep 2; done; "
        "tail -6 ~/boot-cluster.log"
    ))
    print()

    print("[3] Confirm patch markers present in boot script")
    print(run(
        "grep -c 'safe to close this terminal' ~/.termux/boot/start-cluster.sh; "
        "grep -n 'local m=' ~/.termux/boot/start-cluster.sh | head -1"
    ))
    print()

    print("[4] Services alive")
    print(run(
        "pgrep -f 'artisan serve' >/dev/null && echo WEB-WORKERS-UP || echo NO-WEB-WORKERS; "
        "pgrep -f 'artisan queue:work' >/dev/null && echo QUEUE-WORKER-UP || echo NO-QUEUE-WORKER"
    ))
    print()
    ssh.close()


finalize("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)", "Phone 1 boot complete")
print()
finalize("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)", "Phone 2 boot complete")
print()
print("FINALIZE DONE")