#!/usr/bin/env python3
"""Check runtime CONFIG of running valkey instances (dir/save/aof/maxmemory)."""

import paramiko


def connect(host, port, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, port, user, pw, timeout=20)
    return ssh


def run(ssh, cmd, timeout=30):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


def main():
    ssh = connect("192.168.1.222", 8022, "u0_a175", "A2345678")

    for port in (6379, 6380):
        print("=" * 60)
        print("PORT %d" % port)
        print("=" * 60)
        for key in ("dir", "dbfilename", "save", "appendonly", "appendfsync",
                    "appenddirname", "maxmemory", "maxmemory-policy",
                    "aof-use-rdb-preamble"):
            print(run(ssh,
                      "valkey-cli -h 127.0.0.1 -p %d -a 'UniActivityRedis2026!' "
                      "--no-auth-warning CONFIG GET %s 2>&1 | tail -1" % (port, key)))
        print()
        print(run(ssh,
                  "valkey-cli -h 127.0.0.1 -p %d -a 'UniActivityRedis2026!' "
                  "--no-auth-warning INFO persistence 2>&1 | grep -E 'aof_|rdb_last_bgsave|rdb_last_save'"
                  % port))
        print()

    print("[data dirs]")
    print(run(ssh, "ls -la ~/valkey-data/ ~/valkey-queue-data/ 2>/dev/null"))

    ssh.close()


if __name__ == "__main__":
    main()