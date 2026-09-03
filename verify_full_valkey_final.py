#!/usr/bin/env python3
"""Final end-to-end verification of the full-Valkey cluster."""

import paramiko

VALKEY_PASSWORD = "UniActivityRedis2026!"
S1 = ("192.168.1.222", "u0_a175", "A2345678")
S2 = ("192.168.1.140", "u0_a135", "A23457")
NL = chr(10)


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


def flat(ssh, port, cmd):
    return cli(ssh, port, cmd).replace(NL, " | ")


print("=" * 64)
print("FINAL VERIFICATION - FULL VALKEY CLUSTER")
print("=" * 64)

ssh = connect(*S1)
print("")
print("--- S1 (192.168.1.222) master ---")
print("[packages] " + run(ssh, "dpkg -l 2>/dev/null | grep -iE 'valkey|redis' | awk '{print $1, $2, $3}'"))
print("[processes]")
print(run(ssh, "ps aux | grep -E '[v]alkey-server|[r]edis-server' || echo NONE"))
for port in ("6379", "6380"):
    print(f"[instance :{port}]")
    print("  PING: " + cli(ssh, port, "PING"))
    print("  version: " + flat(ssh, port, "INFO server 2>/dev/null | grep valkey_version"))
    print("  config: " + flat(ssh, port, "CONFIG GET appendonly maxmemory maxmemory-policy"))
    print("  dbsize: " + cli(ssh, port, "DBSIZE"))
print("[AOF files on disk]")
print(run(ssh, "ls -la ~/valkey-data/appendonly.aof ~/valkey-queue-data/appendonly.aof 2>&1"))
print("[boot script flags (appendonly yes count)]")
print(run(ssh, "grep -c 'appendonly yes' ~/.termux/boot/start-cluster.sh ~/uni-activity/scripts/boot-node1.sh"))
ssh.close()

ssh = connect(*S2)
print("")
print("--- S2 (192.168.1.140) worker ---")
print("[php-redis removed?] " + run(ssh, "dpkg -s php-redis 2>/dev/null | grep Status || echo NOT-INSTALLED"))
print("[predis present?] " + run(ssh, "test -f ~/uni-activity/vendor/predis/predis/src/Client.php && echo YES || echo NO"))
ping_php = r"""cat > "$HOME/valkey_ping.php" <<'PHPEOF'
<?php
require getenv('HOME') . '/uni-activity/vendor/autoload.php';
foreach ([6379, 6380] as $p) {
    try {
        $c = new Predis\Client([
            'scheme' => 'tcp', 'host' => '192.168.1.222',
            'port' => $p, 'password' => 'UniActivityRedis2026!', 'timeout' => 5,
        ]);
        echo $p . ': ' . $c->ping() . PHP_EOL;
    } catch (Throwable $e) {
        echo $p . ': FAIL - ' . $e->getMessage() . PHP_EOL;
    }
}
PHPEOF
php "$HOME/valkey_ping.php"
rm -f "$HOME/valkey_ping.php"
"""
print("[cross-node PING]")
print(run(ssh, ping_php))
print("[queue worker] " + run(ssh, "pgrep -fc 'artisan queue:work' || echo NOT-RUNNING") + " process(es)")
ssh.close()

print("")
print("=" * 64)
print("VERIFICATION COMPLETE")
print("=" * 64)