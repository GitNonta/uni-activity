#!/usr/bin/env python3
"""Check S1 nginx load-balancing config (session sharing implications)."""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)


def run(cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


print("[nginx files]")
print(run("ls $PREFIX/etc/nginx/nginx.conf $PREFIX/etc/nginx/conf.d/ 2>&1"))
print()
print("[upstream / proxy_pass / hash lines]")
print(run(
    "grep -rnE 'upstream|proxy_pass|ip_hash|hash |server 192' "
    "$PREFIX/etc/nginx/nginx.conf $PREFIX/etc/nginx/conf.d/ 2>/dev/null"
))
print()
print("[full nginx.conf]")
print(run("cat $PREFIX/etc/nginx/nginx.conf 2>/dev/null"))
print()
print("[conf.d contents]")
print(run("for f in $PREFIX/etc/nginx/conf.d/*.conf; do echo \"--- $f\"; cat \"$f\"; done 2>/dev/null"))

ssh.close()