#!/usr/bin/env python3
"""Remove temp test files from both servers."""

import paramiko


def main():
    for host, port, user, pw, label in (
        ("192.168.1.222", 8022, "u0_a175", "A2345678", "S1"),
        ("192.168.1.140", 8022, "u0_a135", "A23457", "S2"),
    ):
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        ssh.connect(host, port, user, pw, timeout=15)
        _, o, _ = ssh.exec_command("rm -f $HOME/vk_test.php && echo done")
        print(label, o.read().decode().strip())
        ssh.close()


if __name__ == "__main__":
    main()