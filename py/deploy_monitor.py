"""
deploy_monitor.py — Deploy py/monitor/ package to Termux server.

Usage:
    python py/deploy_monitor.py
    python py/deploy_monitor.py --host 192.168.1.222 --port 8022 --user u0_a175

Requires:
    - SSH key or password (will prompt if needed)
    - rsync available locally and on server (OR falls back to scp)
    - Server: Termux @ 192.168.1.222:8022

What it does:
    1. rsync py/ directory to server (excludes __pycache__, *.pyc, old files)
    2. Kill old monitor_server.py process
    3. Start monitor_server.py in background
    4. Verify server is responding on port 9999
"""

import subprocess
import sys
import time
import argparse
import urllib.request
import urllib.error

# ── Config ────────────────────────────────────────────────────────────────────
DEFAULT_HOST  = "192.168.1.222"
DEFAULT_PORT  = 8022
DEFAULT_USER  = "u0_a175"
REMOTE_DIR    = "/data/data/com.termux/files/home/uni-activity"
MONITOR_PORT  = 9999
LOCAL_PY_DIR  = "py/"   # relative to project root


def banner(msg: str, char: str = "─") -> None:
    print(f"\n{char * 50}")
    print(f"  {msg}")
    print(char * 50)


def run_ssh(host: str, port: int, user: str, cmd: str, check: bool = False) -> subprocess.CompletedProcess:
    """Run a command on remote via SSH"""
    ssh_cmd = [
        "ssh",
        "-p", str(port),
        "-o", "StrictHostKeyChecking=no",
        "-o", "ConnectTimeout=10",
        f"{user}@{host}",
        cmd,
    ]
    return subprocess.run(ssh_cmd, capture_output=True, text=True, check=check)


def rsync_files(host: str, port: int, user: str, local_src: str, remote_dst: str) -> bool:
    """Sync local py/ to remote server"""
    rsync_cmd = [
        "rsync", "-avz", "--delete",
        "--exclude=__pycache__/",
        "--exclude=*.pyc",
        "--exclude=*.pyo",
        "--exclude=monitor_server_original.py",
        "--exclude=monitor_server_new.py",
        "--exclude=patch_monitor.py",
        "--exclude=split_monitor.py",
        "-e", f"ssh -p {port} -o StrictHostKeyChecking=no",
        local_src,
        f"{user}@{host}:{remote_dst}/py/",
    ]
    print(f"[rsync] {local_src} -> {user}@{host}:{remote_dst}/py/")
    result = subprocess.run(rsync_cmd, capture_output=False)
    return result.returncode == 0


def scp_fallback(host: str, port: int, user: str, local_src: str, remote_dst: str) -> bool:
    """Fallback: scp if rsync not available"""
    print("[scp] Using scp as fallback...")
    scp_cmd = [
        "scp", "-r", "-P", str(port),
        "-o", "StrictHostKeyChecking=no",
        local_src,
        f"{user}@{host}:{remote_dst}/",
    ]
    result = subprocess.run(scp_cmd, capture_output=False)
    return result.returncode == 0


def git_push_deploy(host: str, port: int, user: str, remote_dst: str) -> bool:
    """Fallback: git add + commit + push, then git pull on server.
    ใช้เมื่อ rsync และ scp ไม่มีบน Windows."""
    import os
    print("[git] rsync/scp not found. Using git push + pull strategy...")

    # 1. Commit locally if dirty
    project_root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    status = subprocess.run(["git", "status", "--short", "py/"], capture_output=True, text=True, cwd=project_root)
    if status.stdout.strip():
        subprocess.run(["git", "add", "py/"], cwd=project_root)
        subprocess.run(["git", "commit", "-m", "chore: monitor bug fixes & folder cleanup [auto-deploy]"], cwd=project_root)

    # 2. Push to remote
    push = subprocess.run(["git", "push", "origin", "main"], capture_output=True, text=True, cwd=project_root)
    if push.returncode != 0:
        print(f"[git] push failed: {push.stderr}")
        return False
    print("[git] Pushed to origin/main")

    # 3. Pull on server
    pull_cmd = f"cd {remote_dst} && git pull origin main"
    result = run_ssh(host, port, user, pull_cmd)
    if result.returncode != 0:
        print(f"[git] pull on server failed: {result.stderr}")
        return False
    print("[git] Server pulled latest code")
    return True


def _tool_available(name: str) -> bool:
    """Check if a CLI tool exists without raising FileNotFoundError."""
    try:
        subprocess.run([name, "--version"], capture_output=True, timeout=5)
        return True
    except (FileNotFoundError, OSError):
        return False


def check_server_health(host: str, timeout: int = 15) -> bool:
    """Check if monitor server is responding on port 9999"""
    url = f"http://{host}:{MONITOR_PORT}/api/stats"
    print(f"[health] Checking {url} ...")
    for i in range(timeout):
        try:
            with urllib.request.urlopen(url, timeout=3) as r:
                if r.status == 200:
                    return True
        except Exception:
            pass
        time.sleep(1)
        print(f"[health] Waiting... ({i+1}/{timeout})")
    return False


def main() -> None:
    parser = argparse.ArgumentParser(description="Deploy monitor package to Termux server")
    parser.add_argument("--host", default=DEFAULT_HOST)
    parser.add_argument("--port", default=DEFAULT_PORT, type=int)
    parser.add_argument("--user", default=DEFAULT_USER)
    parser.add_argument("--no-restart", action="store_true")
    parser.add_argument("--health-only", action="store_true")
    args = parser.parse_args()

    host, port, user = args.host, args.port, args.user

    if args.health_only:
        banner(f"Health Check: {host}:{MONITOR_PORT}")
        ok = check_server_health(host, timeout=5)
        print(f"\n{'OK - Server UP' if ok else 'FAIL - Server NOT responding'}")
        sys.exit(0 if ok else 1)

    banner(f"Deploy Monitor to {user}@{host}:{port}", "=")

    print("\n[1/4] Testing SSH connectivity...")
    result = run_ssh(host, port, user, "echo OK")
    if result.returncode != 0:
        print(f"SSH failed: {result.stderr}")
        sys.exit(1)
    print("SSH connected OK")

    print("\n[2/4] Syncing py/ directory...")
    if _tool_available("rsync"):
        ok = rsync_files(host, port, user, LOCAL_PY_DIR, REMOTE_DIR)
    elif _tool_available("scp"):
        print("[!] rsync not found, trying scp...")
        ok = scp_fallback(host, port, user, LOCAL_PY_DIR, REMOTE_DIR)
    else:
        print("[!] rsync and scp not found, trying git push...")
        ok = git_push_deploy(host, port, user, REMOTE_DIR)

    if not ok:
        print("File sync failed!")
        sys.exit(1)
    print("Files synced OK")

    if args.no_restart:
        print("\n[--no-restart] Skipping server restart")
        sys.exit(0)

    print("\n[3/4] Restarting monitor_server.py...")
    restart_cmd = (
        "cd {dir} && "
        "pkill -f 'python.*monitor_server.py' ; "
        "sleep 1 ; "
        "nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &"
    ).format(dir=REMOTE_DIR)

    result = run_ssh(host, port, user, restart_cmd)
    if result.returncode not in (0, 1):
        print(f"Warning: restart returned {result.returncode}: {result.stderr}")
    else:
        print("Server restarted OK")

    print("\n[4/4] Waiting for server to come up...")
    ok = check_server_health(host, timeout=20)

    banner("Deploy Result", "=")
    if ok:
        print(f"Monitor server is UP at http://{host}:{MONITOR_PORT}")
        print(f"API: http://{host}:{MONITOR_PORT}/api/stats")
    else:
        print(f"Server did not respond within timeout")
        print(f"Check log: ssh -p {port} {user}@{host} 'tail -n 50 {REMOTE_DIR}/storage/logs/monitor.log'")
        sys.exit(1)


if __name__ == "__main__":
    main()
