#!/usr/bin/env python3
"""Fix watchdog respawn + gather info for proper queue test."""

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

    print("[watch_valkey.sh exists?]")
    print(run("ls -la ~/watch_valkey.sh && head -5 ~/watch_valkey.sh"))

    print("\n[any stale watch_valkey processes?]")
    print(run("pgrep -af watch_valkey || echo NONE"))

    print("\n[start watchdog detached]")
    print(run(
        "setsid nohup bash $HOME/watch_valkey.sh >/dev/null 2>&1 </dev/null & "
        "sleep 2; pgrep -f 'watch_valkey[.]sh' >/dev/null && echo STARTED || echo FAILED"
    ))

    print("\n[add respawn guard to watchdog.sh (cron every 3 min)]")
    patch = r"""python3 - << 'PYEOF'
import pathlib
p = pathlib.Path.home() / "watchdog.sh"
s = p.read_text()
if "watch_valkey" in s and "respawn" in s:
    print("ALREADY-PATCHED")
elif "watch_valkey" in s:
    marker = "# Valkey"
    idx = s.find(marker)
    block = (
        "# Respawn Valkey supervisor if it died\n"
        "if ! pgrep -f \"watch_valkey[.]sh\" >/dev/null 2>&1; then\n"
        "    log \"HEAL\" \"watch_valkey GONE - respawning\"\n"
        "    setsid nohup bash \"$HOME/watch_valkey.sh\" >/dev/null 2>&1 </dev/null &\n"
        "fi\n\n"
    )
    s = s[:idx] + block + s[idx:]
    p.write_text(s)
    print("RESPAWN-GUARD-ADDED")
else:
    print("NO-VALKEY-SECTION-FOUND")
PYEOF"""
    print(run(patch))

    print("\n[verify watchdog.sh content]")
    print(run("head -30 ~/watchdog.sh"))

    print("\n[clean health check per instance]")
    for port in ("6379", "6380"):
        c = f"valkey-cli -p {port} -a UniActivityRedis2026! --no-auth-warning"
        ping = run(f"{c} ping")
        ao = run(f"{c} CONFIG GET appendonly | tail -1")
        fsync = run(f"{c} CONFIG GET appendfsync | tail -1")
        maxmem = run(f"{c} CONFIG GET maxmemory | tail -1")
        policy = run(f"{c} CONFIG GET maxmemory-policy | tail -1")
        keys = run(f"{c} dbsize")
        print(f"port {port}: {ping} ao={ao} fsync={fsync} maxmem={maxmem} policy={policy} keys={keys}")

    print("\n[available job classes]")
    print(run("ls ~/uni-activity/app/Jobs/"))

    ssh.close()


if __name__ == "__main__":
    main()