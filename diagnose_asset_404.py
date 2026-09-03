#!/usr/bin/env python3
"""Diagnose 404 on app-CPCjDsVR.js across both nodes."""

import hashlib
import pathlib
import paramiko

LOCAL_BUILD = pathlib.Path("public/build")


def local_manifest_hash():
    for cand in [LOCAL_BUILD / "manifest.json", LOCAL_BUILD / ".vite" / "manifest.json"]:
        if cand.exists():
            return str(cand), hashlib.md5(cand.read_bytes()).hexdigest()
    return None, None


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


lm_path, lm_hash = local_manifest_hash()
print(f"local manifest: {lm_path} md5={lm_hash}")
print()

for host, user, pw, label in [
    ("192.168.1.222", "u0_a175", "A2345678", "S1"),
    ("192.168.1.140", "u0_a135", "A23457", "S2"),
]:
    print("#" * 50)
    print("#", label)
    print("#" * 50)
    ssh = connect(host, user, pw)
    run = make_runner(ssh)

    print("[build dir exists?]")
    print(run("ls ~/uni-activity/public/build/ 2>/dev/null | head -5 || echo NO-BUILD-DIR"))
    print()

    print("[app-CPCjDsVR.js present?]")
    print(run("ls -la ~/uni-activity/public/build/assets/app-CPCjDsVR.js 2>/dev/null || echo MISSING"))
    print()

    print("[remote manifest md5]")
    print(run(
        "md5sum ~/uni-activity/public/build/manifest.json "
        "~/uni-activity/public/build/.vite/manifest.json 2>/dev/null || echo NO-MANIFEST"
    ))
    print()

    print("[asset count]")
    print(run("ls ~/uni-activity/public/build/assets 2>/dev/null | wc -l"))
    print()

    print("[what does served HTML reference?]")
    q = chr(34)
    print(run(
        "curl -s http://127.0.0.1:8000/ 2>/dev/null | grep -oE '/build/[^" + q + "]+\\.js' | head -3"
    ))
    print()

    print("[HTTP status of referenced app js]")
    print(run(
        "curl -s -o /dev/null -w '%{http_code}' "
        "http://127.0.0.1:8000/build/assets/app-CPCjDsVR.js"
    ))
    print()
    ssh.close()