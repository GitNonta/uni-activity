#!/usr/bin/env python3
"""Live availability probe: is S2 really working continuously?

Polls S2 web (:8000) + AI (:8001) via LAN every 30s for ~5 minutes,
and checks internal service state via SSH at the end.
"""

import time
from datetime import datetime

import paramiko
import urllib.request

S2_WEB = "http://192.168.1.140:8000/"
S2_AI = "http://192.168.1.140:8001/health"
ROUNDS = 10
INTERVAL = 30


def http_ok(url, timeout=8):
    try:
        with urllib.request.urlopen(url, timeout=timeout) as r:
            return r.status == 200 or r.status == 302
    except Exception:
        return False


results = []
for i in range(ROUNDS):
    ts = datetime.now().strftime("%H:%M:%S")
    web = http_ok(S2_WEB)
    ai = http_ok(S2_AI)
    results.append((web, ai))
    print("[%s] round %2d/%d  web:8000=%s  ai:8001=%s" % (
        ts, i + 1, ROUNDS, "OK" if web else "FAIL", "OK" if ai else "FAIL",
    ))
    if i < ROUNDS - 1:
        time.sleep(INTERVAL)

web_up = sum(1 for w, _ in results if w)
ai_up = sum(1 for _, a in results if a)
print()
print("RESULT: web %d/%d OK (%.0f%%), ai %d/%d OK (%.0f%%)" % (
    web_up, ROUNDS, 100 * web_up / ROUNDS, ai_up, ROUNDS, 100 * ai_up / ROUNDS,
))

print()
print("[internal check via SSH]")
try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=15)

    def run(cmd):
        _, o, _ = ssh.exec_command(cmd, timeout=20)
        return o.read().decode(errors="ignore").strip() or "(no output)"

    print(run(
        "ps aux 2>/dev/null | grep -cE 'artisan serve' ; "
        "pgrep -f watch_web_workers >/dev/null && echo WATCHDOG-WEB-RUNNING || echo WATCHDOG-WEB-DEAD; "
        "pgrep -f 'queue:work' >/dev/null && echo QUEUE-RUNNING || echo QUEUE-DEAD; "
        "uptime_since=$(stat -c %y /proc/$(pgrep -o sshd)/ 2>/dev/null); echo done"
    ))
    print(run("tail -3 ~/uni-activity/storage/logs/web-watchdog.log 2>/dev/null"))
    ssh.close()
except Exception as exc:
    print("SSH check failed: %s" % exc)