#!/usr/bin/env python3
"""Check what optimize_cluster.py accomplished before it stalled."""

import paramiko

S1 = ("192.168.1.222", "u0_a175", "A2345678")
S2 = ("192.168.1.140", "u0_a135", "A23457")


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=15)
    return ssh


def run(ssh, cmd, timeout=60):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


s2 = connect(*S2)
print("[S2] bootstrap cache files")
print(run(s2, "ls ~/uni-activity/bootstrap/cache/"))
print()
print("[S2] workers")
print(run(s2, "pgrep -af 'artisan serve' | grep -v pgrep || echo none"))
print()
print("[S2] :8004 probe")
print(run(s2, "curl -s -o /dev/null -w '%{http_code}' -m 8 http://127.0.0.1:8004/; echo"))
print()
s2.close()

s1 = connect(*S1)
print("[S1] nginx upstream block")
print(run(s1, "sed -n '/upstream laravel_cluster/,/^}/p' "
              "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"))
print()
print("[S1] edge probe x3")
print(run(s1, "for i in 1 2 3; do curl -s -o /dev/null -w '%{http_code} ' "
              "-m 10 http://127.0.0.1:8080/; done; echo"))
s1.close()