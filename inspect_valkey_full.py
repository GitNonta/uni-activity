#!/usr/bin/env python3
"""Deep inspection before switching to full Valkey."""

import paramiko


def inspect(host, user, pw, label):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)

    def run(cmd, timeout=30):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"

    print("=" * 60)
    print(label)
    print("=" * 60)

    print("[binaries]")
    print(run("which valkey-server redis-server valkey-cli redis-cli 2>/dev/null; echo ---"))
    print()

    print("[listening ports 6379/6380]")
    print(run("netstat -tlnp 2>/dev/null | grep -E ':6379|:6380' || ss -tlnp | grep -E ':6379|:6380'"))
    print()

    print("[boot scripts / watchdogs referencing redis or valkey]")
    print(run(
        "grep -rIlE 'valkey|redis' ~/boot* ~/scripts/*.sh ~/.shortcuts 2>/dev/null | head -20; "
        "ls ~/boot_setup.sh ~/start_dual_node.sh 2>/dev/null"
    ))
    print()

    print("[crontab entries]")
    print(run("crontab -l 2>/dev/null | grep -iE 'valkey|redis' || echo NONE"))
    print()

    print("[runit/supervisord services]")
    print(run(
        "for d in ~/sv ~/.sv /data/data/com.termux/files/usr/var/service; do "
        'echo "--($d)"; ls $d 2>/dev/null; done'
    ))
    print()

    pw_line = run("grep '^REDIS_PASSWORD=' ~/uni-activity/.env | head -1")
    pw_env = pw_line.split("=", 1)[1].strip().strip('"') if "=" in pw_line else ""
    auth = f"-a '{pw_env}' --no-auth-warning" if pw_env else ""

    for port in ("6379", "6380"):
        print(f"[valkey :{port} info]")
        print(run(
            f"valkey-cli -h 127.0.0.1 -p {port} {auth} INFO server 2>/dev/null "
            f"| grep -E 'redis_version|process_id' ; "
            f"valkey-cli -h 127.0.0.1 -p {port} {auth} DBSIZE 2>/dev/null"
        ))
        print(f"[valkey :{port} key samples]")
        print(run(
            f"valkey-cli -h 127.0.0.1 -p {port} {auth} --scan 2>/dev/null | head -15"
        ))
        print()

    print("[php modules (full grep)]")
    print(run("php -m 2>/dev/null | head -40"))
    print()

    print("[config cached?]")
    print(run("ls -la ~/uni-activity/bootstrap/cache/*.php 2>/dev/null"))
    print()

    ssh.close()


inspect("192.168.1.222", "u0_a175", "A2345678", "S1 (192.168.1.222)")
print()
inspect("192.168.1.140", "u0_a135", "A23457", "S2 (192.168.1.140)")