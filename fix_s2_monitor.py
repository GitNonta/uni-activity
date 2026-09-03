#!/usr/bin/env python3
"""Apply the same dashboard fixes to S2 (192.168.1.140):
patch collectors.py for S2's actual stack (remote Valkey @ S1, AI service,
web workers, queue worker), deploy new UI dist, start monitor server."""

import base64
import os

import paramiko

NL = chr(10)

# ── New source blocks for S2 ─────────────────────────────────────────────────
NEW_HELPERS = NL.join([
    'def _env_var(name, default=""):',
    '    val = default',
    '    try:',
    '        import os',
    '        env_path = os.path.expanduser("~/uni-activity/.env")',
    '        with open(env_path, "r", encoding="utf-8") as f:',
    '            for line in f:',
    '                if line.startswith(name + "="):',
    '                    val = line.split("=", 1)[1].strip().strip(chr(34)).strip()',
    '                    break',
    '    except Exception:',
    '        pass',
    '    return val',
    '',
    '',
    'def _valkey_password():',
    '    return _env_var("REDIS_PASSWORD")',
    '',
    '',
    'def _valkey_host():',
    '    return _env_var("REDIS_HOST", "127.0.0.1")',
    '',
    '',
    'def _valkey_cli(port, *args):',
    '    import subprocess',
    '    cmd = ["valkey-cli", "-h", _valkey_host(), "-p", str(port),',
    '           "-a", _valkey_password(), "--no-auth-warning"] + list(args)',
    '    try:',
    '        return subprocess.run(cmd, capture_output=True, text=True, timeout=3)',
    '    except Exception:',
    '        return None',
    '',
    '',
    'def _tcp_open(host, port):',
    '    import socket',
    '    try:',
    '        s = socket.create_connection((host, port), timeout=1)',
    '        s.close()',
    '        return True',
    '    except Exception:',
    '        return False',
])

NEW_SERVICES = NL.join([
    'def get_services():',
    '    global _services_cache, _services_cache_time',
    '    import subprocess, time as _time',
    '    # Cache 15s - no need to pgrep every cycle',
    '    if _services_cache and (_time.time() - _services_cache_time) < 15:',
    '        return _services_cache',
    '',
    '    def pgrep(pattern):',
    '        try:',
    '            res = subprocess.run(["pgrep", "-f", pattern], capture_output=True, text=True)',
    '            return bool(res.stdout.strip())',
    '        except Exception:',
    '            return False',
    '',
    '    vk_host = _valkey_host()',
    '    services = {',
    '        "Web Workers (artisan serve)": ("artisan serve", None),',
    '        "AI Biometrics Face Service":  ("server.py", None),',
    '        "Datastore (Valkey @ S1)":     ("valkey:" + vk_host + ":6379", 6379),',
    '        "Queue Store (Valkey @ S1)":   ("valkey:" + vk_host + ":6380", 6380),',
    '        "PostgreSQL Database":         ("postgres", 5432),',
    '        "Queue Worker":                ("artisan queue:work", None),',
    '        "SSH / SFTP Server":           ("sshd", 8022),',
    '    }',
    '',
    '    listening = get_listening_ports()',
    '',
    '    def count_workers():',
    '        try:',
    '            res = subprocess.run(["pgrep", "-f", "artisan serve"], capture_output=True, text=True)',
    '            return len([x for x in res.stdout.split() if x.strip()])',
    '        except Exception:',
    '            return 0',
    '',
    '    status = {}',
    '    for name, (proc_pattern, default_port) in services.items():',
    '        try:',
    '            if proc_pattern.startswith("valkey:"):',
    '                _, h, p = proc_pattern.split(":")',
    '                status[name] = ("Running (Port " + p + ")") if _tcp_open(h, int(p)) else "Stopped"',
    '                continue',
    '            patterns = proc_pattern.split("|")',
    '            is_running = any(pgrep(pat) for pat in patterns)',
    '            if not is_running:',
    '                status[name] = "Stopped"',
    '            elif name == "Web Workers (artisan serve)":',
    '                n = count_workers()',
    '                status[name] = "Running (" + str(n) + " workers)" if n else "Running"',
    '            elif default_port and default_port in listening:',
    '                status[name] = "Running (Port " + str(default_port) + ")"',
    '            else:',
    '                status[name] = "Running"',
    '        except Exception:',
    '            status[name] = "Unknown"',
    '',
    '    _services_cache = status',
    '    _services_cache_time = _time.time()',
    '    return status',
])

NEW_STATS = NL.join([
    'def get_redis_stats():',
    '    stats = {"used_memory": "-", "clients": 0}',
    '    r = _valkey_cli(6379, "info", "memory")',
    '    if r is not None and r.returncode == 0:',
    '        for line in r.stdout.split(chr(10)):',
    '            if "used_memory_human:" in line:',
    '                stats["used_memory"] = line.split(":", 1)[1].strip()',
    '    r2 = _valkey_cli(6379, "info", "clients")',
    '    if r2 is not None and r2.returncode == 0:',
    '        for line in r2.stdout.split(chr(10)):',
    '            if "connected_clients:" in line:',
    '                try:',
    '                    stats["clients"] = int(line.split(":", 1)[1].strip())',
    '                except ValueError:',
    '                    pass',
    '    return stats',
    '',
    '',
    'def get_queue_stats():',
    '    import subprocess',
    '    stats = {"pending": 0, "failed": 0}',
    '    r = _valkey_cli(6380, "llen", "queues:default")',
    '    if r is not None and r.returncode == 0 and r.stdout.strip():',
    '        try:',
    '            stats["pending"] = int(r.stdout.strip())',
    '        except ValueError:',
    '            pass',
    '    try:',
    '        res2 = subprocess.run(["psql", "-d", "uni_activity", "-t", "-c",',
    '                              "SELECT count(*) FROM failed_jobs;"],',
    '                              capture_output=True, text=True, timeout=2)',
    '        if res2.returncode == 0 and res2.stdout.strip():',
    '            stats["failed"] = int(res2.stdout.strip())',
    '    except Exception:',
    '        pass',
    '    return stats',
])


def b64(text):
    return base64.b64encode(text.encode()).decode()


WRAPPER_BODY = NL.join([
    'import pathlib, py_compile, shutil, time, base64',
    'NEW_SERVICES_SRC = base64.b64decode("' + b64(NEW_SERVICES) + '").decode()',
    'NEW_HELPERS_SRC = base64.b64decode("' + b64(NEW_HELPERS) + '").decode()',
    'NEW_STATS_SRC = base64.b64decode("' + b64(NEW_STATS) + '").decode()',
    'p = pathlib.Path.home() / "uni-activity/py/monitor/collectors.py"',
    'backup = str(p) + ".bak-" + time.strftime("%Y%m%d%H%M%S")',
    'shutil.copy(p, backup)',
    'print("backup:", backup)',
    'lines = p.read_text().split(chr(10))',
    '',
    'def find_block(start_prefix, end_prefix):',
    '    start = end = None',
    '    for i, ln in enumerate(lines):',
    '        if ln.startswith(start_prefix):',
    '            start = i',
    '        elif start is not None and ln.startswith(end_prefix):',
    '            end = i',
    '            break',
    '    if start is None or end is None:',
    '        raise SystemExit("block not found: %s .. %s" % (start_prefix, end_prefix))',
    '    return start, end',
    '',
    's1, e1 = find_block("def get_services():", "# --- Advanced Metrics Helpers ---")',
    'lines[s1:e1] = NEW_SERVICES_SRC.split(chr(10)) + ["", ""]',
    'print("get_services replaced: lines %d-%d" % (s1 + 1, e1))',
    '',
    's2, e2 = find_block("def get_redis_stats():", "def get_cloudflared_stats():")',
    'lines[s2:e2] = (NEW_HELPERS_SRC + chr(10) * 3 + NEW_STATS_SRC).split(chr(10)) + [""]',
    'print("stats replaced: lines %d-%d" % (s2 + 1, e2))',
    '',
    'p.write_text(chr(10).join(lines))',
    'py_compile.compile(str(p), doraise=True)',
    'print("COMPILE-OK")',
])

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect("192.168.1.140", 8022, "u0_a135", "A23457", timeout=20)


def run(cmd, timeout=90):
    _, o, e = ssh.exec_command(cmd, timeout=timeout)
    out = o.read().decode("utf-8", errors="ignore").strip()
    err = e.read().decode("utf-8", errors="ignore").strip()
    return out or err or "(no output)"


# ── 1. Patch collectors.py ───────────────────────────────────────────────────
print("[patch collectors.py]")
cmd = "python3 - << 'PYEOF'" + NL + WRAPPER_BODY + NL + "PYEOF"
_, o, e = ssh.exec_command(cmd, timeout=60)
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)

# ── 2. Deploy dist ───────────────────────────────────────────────────────────
print("[deploy dist]")
sftp = ssh.open_sftp()
LOCAL_DIST = r"D:\projects\uni-activity\monitor-ui\dist"
REMOTE_DIST = "/data/data/com.termux/files/home/uni-activity/monitor-ui/dist"


def upload_dir(local, remote):
    try:
        sftp.stat(remote)
    except FileNotFoundError:
        parts = remote.strip("/").split("/")
        cur = ""
        for part in parts:
            cur += "/" + part
            try:
                sftp.stat(cur)
            except FileNotFoundError:
                sftp.mkdir(cur)
    for entry in os.listdir(local):
        lpath = os.path.join(local, entry)
        rpath = remote + "/" + entry
        if os.path.isdir(lpath):
            upload_dir(lpath, rpath)
        else:
            sftp.put(lpath, rpath)
            print("uploaded:", rpath)


upload_dir(LOCAL_DIST, REMOTE_DIST)
sftp.close()

# ── 3. Start monitor server ──────────────────────────────────────────────────
print("[start monitor server]")
print(run(
    "pkill -f 'monitor_serve[r].py' 2>/dev/null; sleep 1; "
    "cd ~/uni-activity && setsid nohup python -u py/monitor_server.py "
    ">> ~/monitor_server.log 2>&1 < /dev/null & sleep 5; "
    "pgrep -f 'monitor_serve[r].py'"
))

# ── 4. Verify HTTP + WS broadcast ────────────────────────────────────────────
q = chr(34)
print("[index bundle]")
print(run("curl -s -m 3 http://127.0.0.1:9999/ | grep -o " + q + "assets/index-[A-Za-z0-9._-]*" + q))
print("[asset status]")
print(run("curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:9999/assets/index-D44OyvcX.js"))

WS_PROBE = NL.join([
    'import base64, json, os, socket, struct, time',
    'CRLF = chr(13) + chr(10)',
    'key = base64.b64encode(os.urandom(16)).decode()',
    'req = ("GET /ws HTTP/1.1" + CRLF + "Host: 127.0.0.1:9999" + CRLF',
    '       + "Upgrade: websocket" + CRLF + "Connection: Upgrade" + CRLF',
    '       + "Sec-WebSocket-Key: " + key + CRLF',
    '       + "Sec-WebSocket-Version: 13" + CRLF + CRLF)',
    's = socket.create_connection(("127.0.0.1", 9999), timeout=5)',
    's.sendall(req.encode())',
    'buf = b""',
    'marker = (chr(13) + chr(10) + chr(13) + chr(10)).encode()',
    'while marker not in buf:',
    '    c = s.recv(4096)',
    '    if not c:',
    '        break',
    '    buf += c',
    'head = buf.split(marker)[0].decode(errors="ignore")',
    'print("handshake:", head.splitlines()[0] if head else "EMPTY")',
    'rest = buf.split(marker, 1)[1]',
    '',
    'def recv_frame(sock, initial=b""):',
    '    data = initial',
    '    while len(data) < 2:',
    '        c = sock.recv(4096)',
    '        if not c:',
    '            return None',
    '        data += c',
    '    b1, b2 = data[0], data[1]',
    '    ln = b2 & 0x7F',
    '    off = 2',
    '    if ln == 126:',
    '        while len(data) < 4:',
    '            c = sock.recv(4096)',
    '            if not c:',
    '                return None',
    '            data += c',
    '        ln = struct.unpack(">H", data[2:4])[0]',
    '        off = 4',
    '    payload = data[off:]',
    '    while len(payload) < ln:',
    '        c = sock.recv(4096)',
    '        if not c:',
    '            break',
    '        payload += c',
    '    return b1 & 0x0F, payload[:ln]',
    '',
    'deadline = time.time() + 45',
    'got = None',
    'frame_rest = rest',
    'while time.time() < deadline:',
    '    fr = recv_frame(s, frame_rest)',
    '    frame_rest = b""',
    '    if fr is None:',
    '        break',
    '    opcode, payload = fr',
    '    if opcode == 1:',
    '        got = json.loads(payload.decode())',
    '        break',
    'if got:',
    '    st = got.get("stats", got)',
    '    print("services:", json.dumps(st.get("services", {}), indent=1)[:900])',
    '    am = st.get("advanced_metrics") or {}',
    '    print("redis:", am.get("redis"))',
    '    print("queue:", am.get("queue"))',
    'else:',
    '    print("NO BROADCAST")',
    's.close()',
])
wscmd = "python3 - << 'PYEOF'" + NL + WS_PROBE + NL + "PYEOF"
_, o, e = ssh.exec_command(wscmd, timeout=90)
print("[ws broadcast]")
print(o.read().decode(errors="ignore"))
err = e.read().decode(errors="ignore").strip()
if err:
    print("STDERR:", err)

ssh.close()