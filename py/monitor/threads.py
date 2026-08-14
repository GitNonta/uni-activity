"""
monitor/threads.py — Background worker threads (UDP, auto-sync, AI manager, stats collector, WS).
"""
import time, threading, json, os, socket, base64, hashlib, struct, subprocess
import monitor.config as cfg
from monitor.telegram import tg_send, tg_daily_report
from monitor.alerts import collect_stats


threading.Thread(target=fetch_public_ip_loop, daemon=True).start()

# ------- UDP Inspector Receiver -------
def udp_receiver_thread():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", UDP_cfg.PORT))
    while True:
        try:
            data, addr = sock.recvfrom(65535)
            if data:
                payload = json.loads(data.decode("utf-8"))
                payload['id'] = str(time.time()) + "-" + str(hash(data))
                payload['server_time'] = time.time()
                cfg.inspector_logs.appendleft(payload)
        except Exception:
            import time
            time.sleep(1)

# ------- UDP AI Logs Receiver -------
def udp_ai_receiver_thread():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", UDP_cfg.PORT_AI))
    while True:
        try:
            data, addr = sock.recvfrom(4096)
            msg = data.decode("utf-8", "ignore")
            cfg.remote_ai_logs.append(msg + "\n")
        except Exception:
            time.sleep(1)

# ------- Auto-Sync Thread -------
def auto_sync_thread():
    import subprocess, time, os
    from pathlib import Path
    app_dir = "/data/data/com.termux/files/home/uni-activity"
    if not os.path.exists(app_dir):
        app_dir = str(Path(__file__).parent.parent)
    
    while True:
        time.sleep(60)  # Poll GitHub every 60 seconds
        try:
            subprocess.run(["git", "fetch", "origin", "main"], cwd=app_dir, capture_output=True)
            local_head = subprocess.run(["git", "rev-parse", "HEAD"], cwd=app_dir, capture_output=True, text=True).stdout.strip()
            remote_head = subprocess.run(["git", "rev-parse", "origin/main"], cwd=app_dir, capture_output=True, text=True).stdout.strip()
            
            if local_head and remote_head and local_head != remote_head:
                sync_log = os.path.join(app_dir, "storage/logs/git-sync.log")
                os.makedirs(os.path.dirname(sync_log), exist_ok=True)
                with open(sync_log, "a", encoding="utf-8") as f:
                    f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Auto-Deploy: New commit ({remote_head[:7]}) detected! Updating & restarting...\n")
                
                subprocess.run(["git", "reset", "--hard", "origin/main"], cwd=app_dir)
                subprocess.run(["php", "artisan", "config:clear"], cwd=app_dir)
                subprocess.run(["php", "artisan", "route:clear"], cwd=app_dir)
                subprocess.run(["pkill", "-9", "-f", "php-fpm"])
                subprocess.Popen(["nohup", "php-fpm"], cwd=app_dir)
                
                pid = os.getpid()
                subprocess.Popen(
                    f"sleep 2 && kill -9 {pid} ; nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &",
                    shell=True, cwd=app_dir
                )
                break
        except Exception:
            pass

# ------- Start Background Threads -------

def ws_handshake(conn, request_data):
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

def ws_encode(message):
    """Encode a WebSocket text frame."""
    data = message.encode("utf-8")
    length = len(data)
    if length < 126:
        header = bytes([0x81, length])
    elif length < 65536:
        header = bytes([0x81, 126]) + struct.pack(">H", length)
    else:
        header = bytes([0x81, 127]) + struct.pack(">Q", length)
    return header + data

# ── Shared stats cache (collected once, served to all WS clients) ──────────
_stats_cache: dict = {}
_stats_lock  = threading.Lock()

def stats_collector_thread():
    """Collect stats in background every 5 s (was: every 2 s per client)."""
    
    while True:
        try:
            data = collect_stats()
            with cfg._stats_lock:
                cfg._stats_cache = data
            # Daily report ทุก 24 ชั่วโมง
            tg_daily_report(data)
        except Exception:
            pass
        time.sleep(5)   # ← collect ทุก 5 วินาที แทนทุก 2 วินาที

def ws_client_thread(conn):
    """Push cached stats to one WS client every 5 s — zero extra subprocess calls."""
    try:
        while True:
            with cfg._stats_lock:
                snapshot = cfg._stats_cache.copy() if cfg._stats_cache else {}
            if snapshot:
                conn.sendall(ws_encode(json.dumps(snapshot)))
            time.sleep(5)   # ← push ทุก 5 วินาที
    except Exception:
        pass
    finally:
        try:
            conn.close()
        except Exception:
            pass