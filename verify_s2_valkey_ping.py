#!/usr/bin/env python3
"""Verify cross-node Valkey connectivity from S2 (Termux has no /tmp — use $HOME)."""

import paramiko

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


ssh = connect(*S2)

ping_php = r"""cat > "$HOME/valkey_ping.php" <<'PHPEOF'
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
php "$HOME/valkey_ping.php"
rm -f "$HOME/valkey_ping.php"
"""
print("[cross-node PING from S2 to S1 Valkey]")
print(run(ssh, ping_php))

print("")
print("[queue worker]")
print(run(ssh, "ps aux | grep '[q]ueue:work' | head -2 || echo NOT-RUNNING"))

ssh.close()