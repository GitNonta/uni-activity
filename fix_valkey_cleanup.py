#!/usr/bin/env python3
"""Cleanup after full-Valkey switch.

S1: dedupe valkey flags in boot scripts, remove leftover redis.conf,
    strip dead Redis block from legacy watchdog.sh
S2: cross-node PING via predis using $HOME temp path (Termux has no /tmp)
"""

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")
S2 = ("192.168.1.140", "u0_a135", "A23457")


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def run(ssh, cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


CLEANUP_S1 = r"""
set -e
# 1. Deduplicate repeated valkey flags in boot scripts (idempotent rewrite)
python3 - << 'PYEOF'
import pathlib

targets = [
    pathlib.Path.home() / ".termux/boot/start-cluster.sh",
    pathlib.Path.home() / "uni-activity/scripts/boot-node1.sh",
]

CANON_6379 = (
    'valkey-server --daemonize yes --port 6379 --bind 0.0.0.0 '
    '--requirepass "$RPW" --dir "$HOME/valkey-data" '
    '--appendonly yes --appendfsync everysec --maxmemory 512mb '
    '--maxmemory-policy allkeys-lru --dbfilename dump.rdb '
    '--pidfile "$HOME/valkey-data/valkey6379.pid" 2>/dev/null'
)
CANON_6380 = (
    'valkey-server --daemonize yes --port 6380 --bind 0.0.0.0 '
    '--requirepass "$RPW" --dir "$HOME/valkey-queue-data" '
    '--appendonly yes --appendfsync everysec --maxmemory 256mb '
    '--dbfilename dump.rdb --pidfile "$HOME/valkey-queue-data/valkey6380.pid" 2>/dev/null'
)

for p in targets:
    if not p.exists():
        print("skip (missing):", p)
        continue
    lines = p.read_text().splitlines()
    changed = False
    for i, line in enumerate(lines):
        stripped = line.strip()
        if stripped.startswith("valkey-server") and "--port 6379" in stripped:
            indent = line[: len(line) - len(line.lstrip())]
            lines[i] = indent + CANON_6379
            changed = True
        elif stripped.startswith("valkey-server") and "--port 6380" in stripped:
            indent = line[: len(line) - len(line.lstrip())]
            lines[i] = indent + CANON_6380
            changed = True
    if changed:
        p.write_text("\n".join(lines) + "\n")
        print("deduped:", p)
    else:
        print("no-change:", p)
PYEOF

# 2. Remove leftover redis package config
rm -f "$PREFIX/etc/redis.conf" && echo "removed \$PREFIX/etc/redis.conf"

# 3. Strip dead Redis block from legacy watchdog.sh (watch_valkey.sh owns Valkey now)
python3 - << 'PYEOF'
import pathlib
p = pathlib.Path.home() / "watchdog.sh"
s = p.read_text()
start = s.find("# Redis")
if start == -1:
    print("watchdog.sh: no Redis block found")
else:
    end = s.find("# PostgreSQL", start)
    if end == -1:
        end = len(s)
    s = s[:start] + (
        "# Valkey — healed by ~/watch_valkey.sh (dedicated supervisor)\n"
        "if ! valkey-cli -p 6379 -a 'UniActivityRedis2026!' --no-auth-warning ping >/dev/null 2>&1; then\n"
        "    log \"HEAL\" \"Valkey :6379 DOWN — triggering watch_valkey recovery\"\n"
        "fi\n\n"
    ) + s[end:]
    p.write_text(s)
    print("watchdog.sh: Redis block replaced with Valkey check")
PYEOF

echo "--- final boot script valkey lines ---"
grep -n 'valkey-server' "$HOME/.termux/boot/start-cluster.sh" "$HOME/uni-activity/scripts/boot-node1.sh"
echo "--- watchdog.sh head ---"
head -25 "$HOME/watchdog.sh"
echo "--- leftover redis files ---"
ls "$PREFIX/etc/redis.conf" 2>/dev/null || echo "redis.conf GONE"
dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $1, $2, $3}'
"""

PING_S2 = r"""
cat > "$HOME/valkey_ping.php" <<'PHPEOF'
<?php
require getenv('HOME') . '/uni-activity/vendor/autoload.php';
foreach ([6379, 6380] as $p) {
    try {
        $c = new Predis\Client([
            'scheme' => 'tcp',
            'host' => '192.168.1.222',
            'port' => $p,
            'password' => 'UniActivityRedis2026!',
            'timeout' => 5,
        ]);
        echo $p . ': ' . $c->ping() . PHP_EOL;
    } catch (Throwable $e) {
        echo $p . ': FAIL - ' . $e->getMessage() . PHP_EOL;
    }
}
PHPEOF
php "$HOME/valkey_ping.php" && rm -f "$HOME/valkey_ping.php"
"""


def main() -> None:
    print("=" * 64)
    print("S1 cleanup")
    print("=" * 64)
    ssh = connect(*S1)
    print(run(ssh, CLEANUP_S1))
    ssh.close()

    print()
    print("=" * 64)
    print("S2 cross-node PING")
    print("=" * 64)
    ssh = connect(*S2)
    print(run(ssh, PING_S2))
    ssh.close()


if __name__ == "__main__":
    main()