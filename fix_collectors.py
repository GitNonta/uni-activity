#!/usr/bin/env python3
"""Patch py/monitor/collectors.py on S1 so the dashboard reflects the
actual stack: artisan serve workers, dual Valkey (6379 datastore /
6380 queue), authenticated valkey-cli stats."""

import base64
import paramiko

NL = chr(10)

NEW_HELPERS = (
    'def _valkey_password():' + NL
    + '    pw = ""' + NL
    + '    try:' + NL
    + '        import os' + NL
    + '        env_path = os.path.expanduser("~/uni-activity/.env")' + NL
    + '        with open(env_path, "r", encoding="utf-8") as f:' + NL
    + '            for line in f:' + NL
    + '                if line.startswith("REDIS_PASSWORD="):' + NL
    + '                    pw = line.split("=", 1)[1].strip().strip(chr(34)).strip()' + NL
    + '                    break' + NL
    + '    except Exception:' + NL
    + '        pass' + NL
    + '    return pw' + NL
    + NL
    + NL
    + 'def _valkey_cli(port, *args):' + NL
    + '    import subprocess' + NL
    + '    cmd = ["valkey-cli", "-p", str(port), "-a", _valkey_password(),' + NL
    + '           "--no-auth-warning"] + list(args)' + NL
    + '    try:' + NL
    + '        return subprocess.run(cmd, capture_output=True, text=True, timeout=2)' + NL
    + '    except Exception:' + NL
    + '        return None'
)

NEW_SERVICES = (
    'def get_services():' + NL
    + '    global _services_cache, _services_cache_time' + NL
    + '    import subprocess, time as _time' + NL
    + '    # Cache 15s - no need to pgrep every cycle' + NL
    + '    if _services_cache and (_time.time() - _services_cache_time) < 15:' + NL
    + '        return _services_cache' + NL
    + NL
    + '    def pgrep(pattern):' + NL
    + '        try:' + NL
    + '            res = subprocess.run(["pgrep", "-f", pattern], capture_output=True, text=True)' + NL
    + '            return bool(res.stdout.strip())' + NL
    + '        except Exception:' + NL
    + '            return False' + NL
    + NL
    + '    services = {' + NL
    + '        "Nginx (Edge Proxy)":          ("nginx", 8080),' + NL
    + '        "Web Workers (artisan serve)": ("artisan serve", None),' + NL
    + '        "Laravel Reverb (WebSocket)":  ("reverb:start", 8082),' + NL
    + '        "Datastore (Valkey)":          ("valkey-server", 6379),' + NL
    + '        "Queue Store (Valkey)":        ("valkey-server", 6380),' + NL
    + '        "PostgreSQL Database":         ("postgres", 5432),' + NL
    + '        "Queue Worker":                ("artisan queue:work", None),' + NL
    + '        "Cloudflared Tunnel":          ("cloudflared", None),' + NL
    + '        "SSH / SFTP Server":           ("sshd", 8022),' + NL
    + '    }' + NL
    + NL
    + '    listening = get_listening_ports()' + NL
    + NL
    + '    def count_workers():' + NL
    + '        try:' + NL
    + '            res = subprocess.run(["pgrep", "-f", "artisan serve"], capture_output=True, text=True)' + NL
    + '            return len([x for x in res.stdout.split() if x.strip()])' + NL
    + '        except Exception:' + NL
    + '            return 0' + NL
    + NL
    + '    status = {}' + NL
    + '    for name, (proc_pattern, default_port) in services.items():' + NL
    + '        try:' + NL
    + '            patterns = proc_pattern.split("|")' + NL
    + '            is_running = any(pgrep(pat) for pat in patterns)' + NL
    + '            if not is_running:' + NL
    + '                status[name] = "Stopped"' + NL
    + '            elif name == "Datastore (Valkey)":' + NL
    + '                status[name] = "Running (Port 6379)" if 6379 in listening else "Running"' + NL
    + '            elif name == "Queue Store (Valkey)":' + NL
    + '                status[name] = "Running (Port 6380)" if 6380 in listening else "Running"' + NL
    + '            elif name == "Web Workers (artisan serve)":' + NL
    + '                n = count_workers()' + NL
    + '                status[name] = "Running (" + str(n) + " workers)" if n else "Running"' + NL
    + '            elif default_port and default_port in listening:' + NL
    + '                status[name] = "Running (Port " + str(default_port) + ")"' + NL
    + '            else:' + NL
    + '                status[name] = "Running"' + NL
    + '        except Exception:' + NL
    + '            status[name] = "Unknown"' + NL
    + NL
    + '    _services_cache = status' + NL
    + '    _services_cache_time = _time.time()' + NL
    + '    return status'
)

NEW_STATS = (
    'def get_redis_stats():' + NL
    + '    stats = {"used_memory": "-", "clients": 0}' + NL
    + '    r = _valkey_cli(6379, "info", "memory")' + NL
    + '    if r is not None and r.returncode == 0:' + NL
    + '        for line in r.stdout.split(chr(10)):' + NL
    + '            if "used_memory_human:" in line:' + NL
    + '                stats["used_memory"] = line.split(":", 1)[1].strip()' + NL
    + '    r2 = _valkey_cli(6379, "info", "clients")' + NL
    + '    if r2 is not None and r2.returncode == 0:' + NL
    + '        for line in r2.stdout.split(chr(10)):' + NL
    + '            if "connected_clients:" in line:' + NL
    + '                try:' + NL
    + '                    stats["clients"] = int(line.split(":", 1)[1].strip())' + NL
    + '                except ValueError:' + NL
    + '                    pass' + NL
    + '    return stats' + NL
    + NL
    + NL
    + 'def get_queue_stats():' + NL
    + '    import subprocess' + NL
    + '    stats = {"pending": 0, "failed": 0}' + NL
    + '    r = _valkey_cli(6380, "llen", "queues:default")' + NL
    + '    if r is not None and r.returncode == 0 and r.stdout.strip():' + NL
    + '        try:' + NL
    + '            stats["pending"] = int(r.stdout.strip())' + NL
    + '        except ValueError:' + NL
    + '            pass' + NL
    + '    try:' + NL
    + '        res2 = subprocess.run(["psql", "-d", "uni_activity", "-t", "-c",' + NL
    + '                              "SELECT count(*) FROM failed_jobs;"],' + NL
    + '                              capture_output=True, text=True, timeout=2)' + NL
    + '        if res2.returncode == 0 and res2.stdout.strip():' + NL
    + '            stats["failed"] = int(res2.stdout.strip())' + NL
    + '    except Exception:' + NL
    + '        pass' + NL
    + '    return stats'
)


def connect(host, user, pw):
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, 8022, user, pw, timeout=20)
    return ssh


b64_services = base64.b64encode(NEW_SERVICES.encode()).decode()
b64_helpers = base64.b64encode(NEW_HELPERS.encode()).decode()
b64_stats = base64.b64encode(NEW_STATS.encode()).decode()

WRAPPER_BODY = (
    'import pathlib, py_compile, shutil, time, base64' + NL
    + 'NEW_SERVICES_SRC = base64.b64decode("' + b64_services + '").decode()' + NL
    + 'NEW_HELPERS_SRC = base64.b64decode("' + b64_helpers + '").decode()' + NL
    + 'NEW_STATS_SRC = base64.b64decode("' + b64_stats + '").decode()' + NL
    + 'p = pathlib.Path.home() / "uni-activity/py/monitor/collectors.py"' + NL
    + 'backup = str(p) + ".bak-" + time.strftime("%Y%m%d%H%M%S")' + NL
    + 'shutil.copy(p, backup)' + NL
    + 'print("backup:", backup)' + NL
    + 'lines = p.read_text().split(chr(10))' + NL
    + NL
    + 'def find_block(start_prefix, end_prefix):' + NL
    + '    start = end = None' + NL
    + '    for i, ln in enumerate(lines):' + NL
    + '        if ln.startswith(start_prefix):' + NL
    + '            start = i' + NL
    + '        elif start is not None and ln.startswith(end_prefix):' + NL
    + '            end = i' + NL
    + '            break' + NL
    + '    if start is None or end is None:' + NL
    + '        raise SystemExit("block not found: %s .. %s" % (start_prefix, end_prefix))' + NL
    + '    return start, end' + NL
    + NL
    + 's1, e1 = find_block("def get_services():", "# --- Advanced Metrics Helpers ---")' + NL
    + 'lines[s1:e1] = NEW_SERVICES_SRC.split(chr(10)) + ["", ""]' + NL
    + 'print("get_services replaced: lines %d-%d" % (s1 + 1, e1))' + NL
    + NL
    + 's2, e2 = find_block("def get_redis_stats():", "def get_cloudflared_stats():")' + NL
    + 'lines[s2:e2] = (NEW_HELPERS_SRC + chr(10) * 3 + NEW_STATS_SRC).split(chr(10)) + [""]' + NL
    + 'print("stats replaced: lines %d-%d" % (s2 + 1, e2))' + NL
    + NL
    + 'p.write_text(chr(10).join(lines))' + NL
    + 'py_compile.compile(str(p), doraise=True)' + NL
    + 'print("COMPILE-OK")'
)

ssh = connect("192.168.1.222", "u0_a175", "A2345678")
cmd = "python3 - << 'PYEOF'" + NL + WRAPPER_BODY + NL + "PYEOF"
_, o, e = ssh.exec_command(cmd, timeout=60)
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)
ssh.close()