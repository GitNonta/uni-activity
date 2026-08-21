"""
monitor/threads.py — Background worker threads (UDP, auto-sync, AI manager, stats collector, WS).
"""
import time, threading, json, os, socket, base64, hashlib, struct, subprocess
import monitor.config as cfg
from monitor.telegram import tg_send, tg_daily_report
from monitor.alerts import collect_stats

# ── NOTE: fetch_public_ip_loop lives in alerts.py ───────────────────────────
# (do NOT call it here at module level — only start it from monitor_server.py)

# ------- UDP Inspector Receiver -------
def udp_receiver_thread():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", cfg.UDP_PORT))
    while True:
        try:
            data, addr = sock.recvfrom(65535)
            if data:
                payload = json.loads(data.decode("utf-8"))
                payload['id'] = str(time.time()) + "-" + str(hash(data))
                payload['server_time'] = time.time()
                cfg.inspector_logs.appendleft(payload)
        except Exception:
            time.sleep(1)

# ------- UDP AI Logs Receiver -------
def udp_ai_receiver_thread():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", cfg.UDP_PORT_AI))
    while True:
        try:
            data, addr = sock.recvfrom(4096)
            msg = data.decode("utf-8", "ignore")
            cfg.remote_ai_logs.append(msg + "\n")
        except Exception:
            time.sleep(1)

# ------- Auto-Sync Thread -------
def auto_sync_thread():
    from pathlib import Path
    app_dir = "/data/data/com.termux/files/home/uni-activity"
    if not os.path.exists(app_dir):
        app_dir = str(Path(__file__).parent.parent.parent)  # project root

    while True:
        time.sleep(60)  # Poll GitHub every 60 seconds
        try:
            subprocess.run(["git", "fetch", "origin", "main"], cwd=app_dir, capture_output=True)
            local_head  = subprocess.run(["git", "rev-parse", "HEAD"],         cwd=app_dir, capture_output=True, text=True).stdout.strip()
            remote_head = subprocess.run(["git", "rev-parse", "origin/main"],  cwd=app_dir, capture_output=True, text=True).stdout.strip()

            if local_head and remote_head and local_head != remote_head:
                sync_log = os.path.join(app_dir, "storage/logs/git-sync.log")
                os.makedirs(os.path.dirname(sync_log), exist_ok=True)
                with open(sync_log, "a", encoding="utf-8") as f:
                    f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Auto-Deploy: New commit ({remote_head[:7]}) detected! Updating & restarting...\n")

                subprocess.run(["git", "reset", "--hard", "origin/main"], cwd=app_dir)
                subprocess.run(["php", "artisan", "config:clear"], cwd=app_dir)
                subprocess.run(["php", "artisan", "route:clear"],  cwd=app_dir)
                octane_reload = subprocess.run(
                    ["php", "artisan", "octane:reload"],
                    cwd=app_dir,
                    capture_output=True,
                    text=True,
                )
                with open(sync_log, "a", encoding="utf-8") as f:
                    f.write(
                        f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] "
                        f"Octane reload exited with status {octane_reload.returncode}.\n"
                    )
                    if octane_reload.stdout:
                        f.write(octane_reload.stdout)
                    if octane_reload.stderr:
                        f.write(octane_reload.stderr)

                pid = os.getpid()
                subprocess.Popen(
                    f"sleep 2 && kill -9 {pid} ; nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &",
                    shell=True, cwd=app_dir
                )
                break
        except Exception:
            pass

# ------- AI Service Manager -------
def _is_local_ai_configured() -> bool:
    """True ถ้า AI_SERVERS/AI_SERVER_URL ใน .env ระบุ localhost (AI รันบนเครื่องนี้)"""
    from monitor.collectors import _get_ai_nodes
    try:
        for url in _get_ai_nodes():
            if "127.0.0.1" in url or "localhost" in url:
                return True
    except Exception:
        pass
    return False


def manage_ai_service_thread():
    """Monitor local AI service and auto-restart if down.

    ในโหมด dual-node (AI อยู่ที่ Phone 2) ถ้าเครื่องนี้ไม่ได้รัน AI ในเครื่อง
    (AI_SERVERS ไม่มี localhost) ให้ข้ามไป — ไม่งั้นจะ spawn restart ทุก 10 วิ
    บนเครื่อง master ที่ไม่มี AI จริง ๆ
    """
    if not _is_local_ai_configured():
        return
    while True:
        try:
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(1.5)
            result = sock.connect_ex(('127.0.0.1', 8001))
            sock.close()
            if result != 0:
                res = subprocess.run(["pgrep", "-f", "python server.py"], capture_output=True, text=True)
                if not res.stdout.strip():
                    subprocess.run(["pkill", "-f", "python server.py"])
                    subprocess.Popen(
                        ['proot-distro', 'login', 'ubuntu', '--', 'bash', '-c',
                         'cd /data/data/com.termux/files/home/uni-activity/ai_service && /root/ai_project/venv/bin/python server.py > server.log 2>&1'],
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.DEVNULL
                    )
        except Exception:
            pass
        time.sleep(10)

# ------- Remote AI Node Watcher -------
_remote_ai_node_state = {}  # url -> {"was_up": bool, "last_alert": float}

def watch_remote_ai_nodes_thread():
    """Monitor remote AI nodes via SSH and restart if down. Sends Telegram alerts.

    Config (env vars, ค่า default = เท่าที่เคย hardcode ไว้):
      AI_SSH_USER      — Termux user ของโหนดปลายทาง (default: u0_a175)
      AI_SSH_PORT      — SSH port (default: 8022)
      AI_SSH_RESTART_CMD — คำสั่ง restart บนเครื่องปลายทาง (default: tmux + proot-distro)
    """
    from monitor.collectors import _parse_node_url, _get_ai_nodes

    ssh_user = os.environ.get("AI_SSH_USER", "u0_a175")
    ssh_port = os.environ.get("AI_SSH_PORT", "8022")
    restart_cmd = os.environ.get(
        "AI_SSH_RESTART_CMD",
        "tmux kill-session -t ai_service 2>/dev/null; "
        "tmux new-session -d -s ai_service 'proot-distro login ubuntu -- bash -c \""
        "cd /data/data/com.termux/files/home/uni-activity/ai_service && "
        "/root/ai_project/venv/bin/python server.py\"'",
    )

    while True:
        try:
            nodes = _get_ai_nodes()
            for url in nodes:
                node = _parse_node_url(url)
                host = node["host"]
                port = node["port"]

                # Skip localhost — handled by manage_ai_service_thread
                if host in ("127.0.0.1", "localhost"):
                    continue

                # TCP check
                is_up = False
                try:
                    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                    s.settimeout(3)
                    is_up = s.connect_ex((host, port)) == 0
                    s.close()
                except Exception:
                    is_up = False

                prev = _remote_ai_node_state.get(url, {"was_up": True, "last_alert": 0})

                if not is_up and prev["was_up"]:
                    # Node just went down — try to restart via SSH
                    now = time.time()
                    if now - prev["last_alert"] > 300:  # alert max every 5 min
                        try:
                            from monitor.telegram import tg_alert
                            tg_alert(f"ai_node_down_{host}", "critical",
                                     f"🤖 Remote AI Node {url} is DOWN! Attempting restart via SSH...")
                        except Exception:
                            pass
                        _remote_ai_node_state[url] = {"was_up": False, "last_alert": now}

                    # Try SSH restart
                    try:
                        subprocess.run(
                            ["ssh", "-o", "ConnectTimeout=5", "-o", "StrictHostKeyChecking=no",
                             "-p", ssh_port, f"{ssh_user}@{host}", restart_cmd, "2>/dev/null"],
                            timeout=15, capture_output=True
                        )
                    except Exception:
                        pass

                elif is_up and not prev.get("was_up", True):
                    # Node recovered
                    try:
                        from monitor.telegram import tg_resolved
                        tg_resolved(f"ai_node_down_{host}", f"🤖 Remote AI Node {url} is back UP ✓")
                    except Exception:
                        pass
                    _remote_ai_node_state[url] = {"was_up": True, "last_alert": 0}

                else:
                    _remote_ai_node_state[url] = {"was_up": is_up, "last_alert": prev["last_alert"]}

        except Exception:
            pass
        time.sleep(30)  # Check every 30 seconds

# ------- WebSocket Helpers -------
def ws_handshake(conn, request_data: str) -> bool:
    """Perform WebSocket upgrade handshake."""
    key = ""
    for line in request_data.split("\r\n"):
        if "Sec-WebSocket-Key" in line:
            key = line.split(": ")[1].strip()
            break
    if not key:
        return False
    accept = base64.b64encode(
        hashlib.sha1((key + "258EAFA5-E914-47DA-95CA-C5AB0DC85B11").encode()).digest()
    ).decode()
    response = (
        "HTTP/1.1 101 Switching Protocols\r\n"
        "Upgrade: websocket\r\n"
        "Connection: Upgrade\r\n"
        f"Sec-WebSocket-Accept: {accept}\r\n\r\n"
    )
    conn.sendall(response.encode())
    return True


def ws_encode(message: str) -> bytes:
    """Encode a WebSocket text frame."""
    data   = message.encode("utf-8")
    length = len(data)
    if length < 126:
        header = bytes([0x81, length])
    elif length < 65536:
        header = bytes([0x81, 126]) + struct.pack(">H", length)
    else:
        header = bytes([0x81, 127]) + struct.pack(">Q", length)
    return header + data


# ------- Stats Collector Thread -------
def stats_collector_thread():
    """Collect stats in background every 5 s (shared cache for all WS clients)."""
    while True:
        try:
            data = collect_stats()
            with cfg._stats_lock:
                cfg._stats_cache = data
            tg_daily_report(data)
        except Exception:
            pass
        time.sleep(5)


def ws_client_thread(conn):
    """Push cached stats to one WS client every 5 s — zero extra subprocess calls."""
    try:
        while True:
            with cfg._stats_lock:
                snapshot = cfg._stats_cache.copy() if cfg._stats_cache else {}
            if snapshot:
                conn.sendall(ws_encode(json.dumps(snapshot)))
            time.sleep(5)
    except Exception:
        pass
    finally:
        try:
            conn.close()
        except Exception:
            pass
