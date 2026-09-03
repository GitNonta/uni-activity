#!/usr/bin/env python3
"""Switch the cluster to full Valkey.

S1 (master): purge legacy redis pkg, enable AOF, install valkey watchdog,
             wire watchdog into boot script.
S2 (worker): install valkey CLI tools, remove stale local-valkey scripts,
             replace with remote status checker pointed at S1.
Both:        verify Laravel cache/session over Valkey.
"""

import paramiko


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=300):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


def write_remote_file(run, path, content):
    body = (
        "cat > " + path + " << 'XEOF'\n" + content.rstrip("\n") + "\nXEOF\n"
        "chmod +x " + path + " && echo WRITTEN:" + path
    )
    print(run(body))


WATCHDOG = r"""#!/data/data/com.termux/files/usr/bin/bash
# watch_valkey.sh — keep both Valkey instances alive (full-Valkey mode)
LOG="$HOME/valkey-watchdog.log"
PROJ="$HOME/uni-activity"
RPW=$(awk -F= '/^REDIS_PASSWORD=/{print $2}' "$PROJ/.env" | tr -d '"\r')

start_6379() {
    valkey-server --daemonize yes --port 6379 --bind 0.0.0.0 \
        --requirepass "$RPW" --dir "$HOME/valkey-data" --dbfilename dump.rdb \
        --pidfile "$HOME/valkey-data/valkey6379.pid" --appendonly yes 2>/dev/null
}

start_6380() {
    mkdir -p "$HOME/valkey-queue-data"
    valkey-server --daemonize yes --port 6380 --bind 0.0.0.0 \
        --requirepass "$RPW" --dir "$HOME/valkey-queue-data" --dbfilename dump.rdb \
        --pidfile "$HOME/valkey-queue-data/valkey6380.pid" --appendonly yes 2>/dev/null
}

while true; do
    if ! valkey-cli -p 6379 -a "$RPW" --no-auth-warning ping 2>/dev/null | grep -q PONG; then
        start_6379 && echo "[$(date '+%F %T')] valkey :6379 restarted (sessions/cache)" >> "$LOG"
    fi
    if ! valkey-cli -p 6380 -a "$RPW" --no-auth-warning ping 2>/dev/null | grep -q PONG; then
        start_6380 && echo "[$(date '+%F %T')] valkey :6380 restarted (queue)" >> "$LOG"
    fi
    sleep 30
done
"""

S2_STATUS = r"""#!/data/data/com.termux/files/usr/bin/bash
# valkey-status.sh — check Valkey on S1 master (S2 has no local datastore)
S1=192.168.1.222
PW='UniActivityRedis2026!'
for p in 6379 6380; do
    if valkey-cli -h "$S1" -p "$p" -a "$PW" --no-auth-warning ping 2>/dev/null | grep -q PONG; then
        n=$(valkey-cli -h "$S1" -p "$p" -a "$PW" --no-auth-warning dbsize 2>/dev/null)
        v=$(valkey-cli -h "$S1" -p "$p" -a "$PW" --no-auth-warning info server 2>/dev/null | grep -m1 valkey_version | tr -d '\r')
        echo "valkey S1:${p} UP (${n} keys, ${v})"
    else
        echo "valkey S1:${p} DOWN"
    fi
done
"""


def setup_s1():
    print("#" * 62)
    print("# S1 (192.168.1.222) — full Valkey")
    print("#" * 62)
    ssh = connect("192.168.1.222", "u0_a175", "A2345678")
    run = make_runner(ssh)

    print("[1] Purge legacy redis package")
    print(run("dpkg -s redis >/dev/null 2>&1 && yes | pkg uninstall -y redis 2>&1 | tail -3 || echo NO-REDIS-PKG"))
    print()

    print("[2] Enable AOF persistence on both instances (runtime)")
    print(run("valkey-cli -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning CONFIG SET appendonly yes"))
    print(run("valkey-cli -p 6380 -a 'UniActivityRedis2026!' --no-auth-warning CONFIG SET appendonly yes"))
    print()

    print("[3] Write valkey watchdog ~/watch_valkey.sh")
    write_remote_file(run, "$HOME/watch_valkey.sh", WATCHDOG)
    print()

    print("[4] Start watchdog if not running")
    print(run(
        "pgrep -f 'watch_valkey[.]sh' >/dev/null && echo ALREADY-RUNNING "
        "|| (setsid nohup bash $HOME/watch_valkey.sh >/dev/null 2>&1 </dev/null & sleep 1; "
        "pgrep -f 'watch_valkey[.]sh' >/dev/null && echo STARTED || echo FAILED)"
    ))
    print()

    print("[5] Wire watchdog into boot script (idempotent)")
    boot_insert = r"""python3 - << 'PYEOF'
import re, pathlib
p = pathlib.Path.home() / ".termux/boot/start-cluster.sh"
s = p.read_text()
if "watch_valkey" in s:
    print("ALREADY-IN-BOOT-SCRIPT")
else:
    block = (
        "\n# Valkey watchdog (full-Valkey mode)\n"
        "if ! pgrep -f \"watch_valkey[.]sh\" > /dev/null 2>&1; then\n"
        "    setsid bash \"$HOME/watch_valkey.sh\" > /dev/null 2>&1 < /dev/null &\n"
        "    log \"valkey watchdog started\"\n"
        "fi\n"
    )
    marker = "# ── 5."
    idx = s.find(marker)
    if idx == -1:
        s = s.rstrip("\n") + "\n" + block
    else:
        s = s[:idx] + block.lstrip("\n") + "\n" + s[idx:]
    p.write_text(s)
    print("INSERTED-INTO-BOOT-SCRIPT")
PYEOF"""
    print(run(boot_insert))
    print()

    print("[6] Verify: version, ping, appendonly, dbsize")
    print(run("valkey-cli -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning INFO server | grep -E 'valkey_version'"))
    print(run("for p in 6379 6380; do echo \"port $p: $(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning ping) ao=$(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning CONFIG GET appendonly | tail -1) keys=$(valkey-cli -p $p -a 'UniActivityRedis2026!' --no-auth-warning dbsize)\"; done"))
    print()

    print("[7] Laravel cache round-trip over Valkey")
    print(run("cd ~/uni-activity && php artisan tinker --execute=\"Cache::store('redis')->put('valkey_switch_test','ok',60); echo 'cache='.Cache::store('redis')->get('valkey_switch_test'); echo ' session_driver='.config('session.driver');\" 2>&1 | tail -2"))
    print()

    print("[8] Confirm no redis binaries remain")
    print(run("which redis-server redis-cli 2>/dev/null || echo CLEAN; pgrep -a redis-server || echo NO-REDIS-PROCESS"))
    ssh.close()


def setup_s2():
    print()
    print("#" * 62)
    print("# S2 (192.168.1.140) — full Valkey client tooling")
    print("#" * 62)
    ssh = connect("192.168.1.140", "u0_a135", "A23457")
    run = make_runner(ssh)

    print("[1] Install valkey package (CLI tools)")
    print(run("command -v valkey-cli >/dev/null && echo ALREADY-INSTALLED || yes | pkg install -y valkey 2>&1 | tail -3"))
    print()

    print("[2] Remove stale local-valkey scripts (S2 has no local datastore)")
    print(run("rm -f $HOME/start-valkey.sh && echo REMOVED-start-valkey.sh"))
    print()

    print("[3] Rewrite valkey-status.sh -> remote check against S1")
    write_remote_file(run, "$HOME/valkey-status.sh", S2_STATUS)
    print()

    print("[4] Run status check")
    print(run("bash $HOME/valkey-status.sh"))
    print()

    print("[5] Laravel cache round-trip over Valkey (via S1)")
    print(run("cd ~/uni-activity && php artisan tinker --execute=\"Cache::store('redis')->put('valkey_switch_test_s2','ok',60); echo 'cache='.Cache::store('redis')->get('valkey_switch_test_s2');\" 2>&1 | tail -2"))
    print()

    print("[6] Queue worker alive?")
    print(run("pgrep -f 'artisan queue:work' >/dev/null && echo WORKER-ALIVE || echo NO-WORKER"))
    ssh.close()


setup_s1()
setup_s2()
print()
print("DONE — cluster switched to full Valkey.")