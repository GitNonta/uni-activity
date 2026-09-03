#!/usr/bin/env python3
"""Deploy current Vite build to S2 (stale) and the CSP-fixed inbox view
to both nodes; clear view caches; verify."""

import pathlib
import stat
import paramiko

LOCAL_BUILD = pathlib.Path("public/build")
LOCAL_VIEW = pathlib.Path("resources/views/admin/inbox/index.blade.php")

NODES = [
    ("192.168.1.222", "u0_a175", "A2345678", "S1"),
    ("192.168.1.140", "u0_a135", "A23457", "S2"),
]


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


def make_runner(ssh):
    def run(cmd, timeout=120):
        _, o, e = ssh.exec_command(cmd, timeout=timeout)
        out = o.read().decode("utf-8", errors="ignore").strip()
        err = e.read().decode("utf-8", errors="ignore").strip()
        return out or err or "(no output)"
    return run


def remote_exists(sftp, path):
    try:
        sftp.stat(path)
        return True
    except FileNotFoundError:
        return False


for host, user, pw, label in NODES:
    print("#" * 55)
    print("#", label)
    print("#" * 55)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)
    sftp = ssh.open_sftp()

    base = "/data/data/com.termux/files/home/uni-activity"

    # ensure dirs exist
    for d in ["public/build", "public/build/assets"]:
        p = f"{base}/{d}"
        if not remote_exists(sftp, p):
            sftp.mkdir(p)
            print(f"mkdir {p}")

    print("[upload build manifest]")
    sftp.put(str(LOCAL_BUILD / "manifest.json"), f"{base}/public/build/manifest.json")
    print("uploaded manifest.json")

    print("[upload assets]")
    for f in sorted((LOCAL_BUILD / "assets").iterdir()):
        if f.is_file():
            sftp.put(str(f), f"{base}/public/build/assets/{f.name}")
            print(f"uploaded {f.name} ({f.stat().st_size} bytes)")

    print("[upload CSP-fixed inbox view]")
    sftp.put(str(LOCAL_VIEW), f"{base}/resources/views/admin/inbox/index.blade.php")
    print("uploaded admin/inbox/index.blade.php")

    sftp.close()

    print("[clear caches]")
    print(run(f"cd {base} && php artisan view:clear && php artisan config:clear"))
    print()

    print("[verify: app js HTTP status]")
    print(run("curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/build/assets/app-CPCjDsVR.js"))
    print()

    print("[verify: manifest md5 matches local?]")
    print(run(f"md5sum {base}/public/build/manifest.json"))
    print()

    print("[verify: no javascript:void(0) left in compiled views]")
    print(run(
        f"grep -rl 'javascript:void(0)' {base}/resources/views/admin/inbox/ "
        f"{base}/storage/framework/views/ 2>/dev/null || echo CLEAN"
    ))
    print()
    ssh.close()

import hashlib
print("local manifest md5:", hashlib.md5((LOCAL_BUILD / "manifest.json").read_bytes()).hexdigest())
print("DEPLOY DONE")