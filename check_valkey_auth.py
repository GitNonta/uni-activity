#!/usr/bin/env python3
"""Find real valkey auth/port config so monitor can query correctly."""

import paramiko


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


ssh = connect("192.168.1.222", "u0_a175", "A2345678")
run = make_runner(ssh)

print("[valkey server processes + configs]")
print(run("pgrep -af valkey-server"))
print()

print("[requirepass lines in valkey confs (masked)]")
q = chr(34)
print(run(
    "for f in ~/valkey-data/*.conf ~/valkey-queue-data/*.conf; do "
    "[ -f " + q + "$f" + q + " ] && echo == $f && grep -E '^(requirepass|port|dir)' " + q + "$f" + q + " | "
    "sed -E 's/(requirepass ).+/\\1***set***/'; done"
))
print()

print("[how does Laravel know queue port? database.php redis section]")
print(run(
    "grep -n -A4 'QUEUE' ~/uniactivity/config/database.php 2>/dev/null; "
    "grep -n '6380\\|queue' ~/uni-activity/config/database.php | head -10"
))
print()

print("[.env queue-related vars (masked)]")
print(run("grep -iE 'queue|6380' ~/uni-activity/.env | sed -E 's/(PASSWORD=).+/[REDACTED]/I'"))
print()

print("[test auth using requirepass from conf]")
print(run(
    "CONF=$(pgrep -af 'valkey-server.*6379' | head -1 | grep -oE '\\S+\\.conf$'); "
    "echo CONF=$CONF; "
    "RP=$(grep '^requirepass' $CONF | awk '{print $2}'); "
    "[ -n " + q + "$RP" + q + " ] && valkey-cli -p 6379 -a " + q + "$RP" + q
    + " --no-auth-warning ping || echo no-conf-pass"
))
print()

ssh.close()