#!/usr/bin/env python3
"""Finalize full-Valkey switch.

S1 (192.168.1.222):
  - Purge residual redis package config files
  - Enable AOF persistence (everysec) on both Valkey instances at runtime
  - Set memory limits: :6379 -> 512mb/allkeys-lru (cache+sessions),
                      :6380 -> 256mb/noeviction (queues must not evict jobs)
  - Patch boot scripts (~/.termux/boot/start-cluster.sh,
    ~/uni-activity/scripts/boot-node1.sh) so flags survive reboot

S2 (192.168.1.140):
  - Cross-node PING to both Valkey instances on S1 (via predis PHP file)
"""

import paramiko

VALKEY_PASSWORD = "UniActivityRedis2026!"
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


def cli(ssh, port, cmd):
    return run(
        ssh,
        f"valkey-cli -h 127.0.0.1 -p {port} -a '{VALKEY_PASSWORD}' "
        f"--no-auth-warning {cmd} 2>&1",
    )


def s1_finalize():
    print("=" * 64)
    print("S1 (192.168.1.222) — finalize")
    print("=" * 64)
    ssh = connect(*S1)

    print("\n[1] purge residual redis package configs")
    print(run(ssh, "yes | apt purge -y redis 2>&1 | tail -3"))
    print(run(ssh, "dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $1, $2, $3}'"))

    print("\n[2] enable AOF + memory limits at runtime")
    print(":6379 appendonly ->", cli(ssh, "6379", "CONFIG SET appendonly yes"))
    print(":6379 appendfsync ->", cli(ssh, "6379", "CONFIG SET appendfsync everysec"))
    print(":6379 maxmemory ->", cli(ssh, "6379", "CONFIG SET maxmemory 512mb"))
    print(":6379 policy ->", cli(ssh, "6379", "CONFIG SET maxmemory-policy allkeys-lru"))
    print(":6380 appendonly ->", cli(ssh, "6380", "CONFIG SET appendonly yes"))
    print(":6380 appendfsync ->", cli(ssh, "6380", "CONFIG SET appendfsync everysec"))
    print(":6380 maxmemory ->", cli(ssh, "6380", "CONFIG SET maxmemory 256mb"))
    print(":6380 policy stays noeviction (queue safety)")

    print("\n[3] verify runtime config")
    for port in ("6379", "6380"):
        print(f"--- port {port} ---")
        print(cli(ssh, port, "CONFIG GET appendonly appendfsync maxmemory maxmemory-policy"))

    print("\n[4] patch boot scripts so settings survive reboot")
    patch = r"""
set -e
for f in "$HOME/.termux/boot/start-cluster.sh" "$HOME/uni-activity/scripts/boot-node1.sh"; do
    [ -f "$f" ] || continue
    cp "$f" "$f.pre-aof.bak"
    # :6379 — datastore/sessions/cache: AOF + LRU
    sed -i 's|valkey-server --daemonize yes --port 6379 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-data"|valkey-server --daemonize yes --port 6379 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-data" --appendonly yes --appendfsync everysec --maxmemory 512mb --maxmemory-policy allkeys-lru|' "$f"
    # :6380 — queue: AOF, noeviction (default)
    sed -i 's|valkey-server --daemonize yes --port 6380 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-queue-data"|valkey-server --daemonize yes --port 6380 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-queue-data" --appendonly yes --appendfsync everysec --maxmemory 256mb|' "$f"
    echo "patched: $f"
done
grep -n 'valkey-server' "$HOME/.termux/boot/start-cluster.sh" "$HOME/uni-activity/scripts/boot-node1.sh"
"""
    print(run(ssh, patch))

    print("\n[5] confirm both instances still healthy")
    for port in ("6379", "6380"):
        print(f"port {port}: {cli(ssh, port, 'PING')}")

    ssh.close()


def s2_verify():
    print()
    print("=" * 64)
    print("S2 (192.168.1.140) — cross-node verification")
    print("=" * 64)
    ssh = connect(*S2)

    ping_php = r"""cat > /tmp/valkey_ping.php <<'PHPEOF'
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
php /tmp/valkey_ping.php && rm -f /tmp/valkey_ping.php"""
    print("\n[cross-node PING to S1 Valkey]")
    print(run(ssh, ping_php))

    print("\n[queue worker]")
    print(run(ssh, "ps aux | grep '[q]ueue:work' | head -2 || echo NOT-RUNNING"))

    ssh.close()


if __name__ == "__main__":
    s1_finalize()
    s2_verify()
    print()
    print("=" * 64)
    print("FULL VALKEY SWITCH COMPLETE")
    print("=" * 64)