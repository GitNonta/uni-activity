"""
Uni-Activity Monitor Backend
Pure Python — No external dependencies required.
Serves React build + WebSocket for real-time stats.
Port: 9999
"""
import asyncio
import json
import os
import re
import time
import threading
import socket
import struct
import hashlib
import base64
import collections
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ENV_PATH = "/data/data/com.termux/files/home/uni-activity/.env"
NGINX_LOG = "/data/data/com.termux/files/usr/var/log/nginx/access.log"
STATIC_DIR = Path(__file__).parent.parent / "monitor-ui" / "dist"
PORT = 9999
UDP_PORT = 9998
UDP_PORT_AI = 9997

inspector_logs = collections.deque(maxlen=100)
remote_ai_logs = collections.deque(maxlen=200)
url_status = {"online": False, "ping_ms": 0}
alerts_history = collections.deque(maxlen=100)
active_alert_ids = set()

speedtest_data = {
    "status": "idle",
    "stage": "idle",
    "ping_ms": 0,
    "jitter_ms": 0,
    "download_mbps": 0,
    "upload_mbps": 0,
    "server": {"name": "Auto-Select Server", "code": "AUTO", "latency_ms": 0},
    "last_test": None
}

# ─── External Speedtest Job (server-side, no CORS) ────────────────────────
_ext_job = {
    "status": "idle",   # idle | running | done | error
    "stage":  "idle",   # ping | upload | download | done
    "ping":     0.0,
    "jitter":   0.0,
    "ping_min": 0.0,
    "ping_max": 0.0,
    "download": 0.0,
    "upload":   0.0,
    "method":   "TCP:443",
    "server":   "Cloudflare (1.1.1.1)",
    "error":    None,
}
_ext_lock = threading.Lock()


def start_ext_speedtest() -> bool:
    """Reset the external test state before its worker is started."""
    with _ext_lock:
        if _ext_job.get("status") == "running":
            return False

        _ext_job.update({
            "status": "running",
            "stage": "ping",
            "ping": 0.0,
            "jitter": 0.0,
            "ping_min": 0.0,
            "ping_max": 0.0,
            "download": 0.0,
            "upload": 0.0,
            "error": None,
        })

    return True


def run_ext_speedtest_thread():
    """Server-side external speedtest: TCP ping → upload → download via Cloudflare."""
    import urllib.request as _ureq, socket as _sock, time as _time

    def _upd(**kw):
        with _ext_lock:
            _ext_job.update(kw)

    # ── 1. TCP Ping to 1.1.1.1:443 (10 samples, discard first 2) ─────────
    _upd(stage="ping", ping=0.0, jitter=0.0)
    rtts = []

    def collect_ping_samples() -> None:
        for _ in range(12):   # take 12, discard first 2
            t0 = _time.perf_counter()
            try:
                with _sock.create_connection(("1.1.1.1", 443), timeout=2):
                    rtts.append((_time.perf_counter() - t0) * 1000)
            except Exception:
                pass
            _time.sleep(0.02)

    ping_worker = threading.Thread(target=collect_ping_samples, daemon=True)
    ping_worker.start()
    ping_worker.join(timeout=12)

    if ping_worker.is_alive():
        _upd(error="Ping timed out after 12 seconds")
    else:
        rtts = rtts[2:]  # discard first 2 (connection warmup)
        if rtts:
            ping_avg = round(sum(rtts) / len(rtts), 1)
            jitter   = 0.0
            for i in range(1, len(rtts)):
                jitter += (abs(rtts[i] - rtts[i-1]) - jitter) / 16
            _upd(ping=ping_avg, jitter=round(jitter, 1),
                 ping_min=round(min(rtts), 1), ping_max=round(max(rtts), 1))

    # ── 2. Upload — 4 concurrent POSTs to Cloudflare ─────────────────────
    _upd(stage="upload")
    try:
        BLOB     = os.urandom(2 * 1024 * 1024)   # 2 MB random blob
        UL_CONNS = 4
        DURATION = 6.0
        ul_bytes = [0] * UL_CONNS
        stop_ev  = threading.Event()

        def _ul_worker(idx):
            while not stop_ev.is_set():
                try:
                    req = _ureq.Request(
                        "https://speed.cloudflare.com/__up",
                        data=BLOB, method="POST",
                        headers={"Content-Type": "application/octet-stream",
                                 "User-Agent": "SpeedTest/2.0"}
                    )
                    _ureq.urlopen(req, timeout=DURATION + 2)
                    ul_bytes[idx] += len(BLOB)
                except Exception:
                    _time.sleep(0.1)

        t_start  = _time.perf_counter()
        workers  = [threading.Thread(target=_ul_worker, args=(i,), daemon=True) for i in range(UL_CONNS)]
        for w in workers: w.start()
        _time.sleep(DURATION)
        stop_ev.set()
        for w in workers: w.join(timeout=3)
        elapsed  = max(_time.perf_counter() - t_start, 0.5)
        ul_total = sum(ul_bytes)
        _upd(upload=round((ul_total * 8) / (elapsed * 1_000_000), 2))
    except Exception as e:
        _upd(error=f"Upload: {e}")

    # ── 3. Download — 4 concurrent from Cloudflare, 8 s, warmup 1.5 s ───
    _upd(stage="download")
    try:
        DL_CONNS = 4
        DURATION = 8.0
        WARMUP   = 1.5
        chunks   = []   # (bytes, timestamp)
        dl_errors = []
        c_lock   = threading.Lock()
        stop_ev  = threading.Event()

        def _dl_worker():
            url = "https://speed.cloudflare.com/__down?bytes=134217728"
            try:
                with _ureq.urlopen(_ureq.Request(url, headers={"User-Agent": "SpeedTest/2.0"}),
                                   timeout=DURATION + 3) as r:
                    while not stop_ev.is_set():
                        chunk = r.read(65536)
                        if not chunk:
                            break
                        with c_lock:
                            chunks.append((len(chunk), _time.perf_counter()))
            except Exception as e:
                with c_lock:
                    dl_errors.append(str(e))

        t_start = _time.perf_counter()
        workers = [threading.Thread(target=_dl_worker, daemon=True) for _ in range(DL_CONNS)]
        for w in workers: w.start()
        _time.sleep(DURATION)
        stop_ev.set()
        for w in workers: w.join(timeout=3)
        t_end   = _time.perf_counter()

        # Discard warmup, use per-second median
        warmup_end  = t_start + WARMUP
        effective   = [(b, ts) for b, ts in chunks if ts >= warmup_end]
        if effective:
            eff_bytes   = sum(b for b, _ in effective)
            eff_elapsed = max(t_end - warmup_end, 0.5)
            # Per-second snapshots for median
            snaps, bucket_bytes, bucket_t0 = [], 0, warmup_end
            for b, ts in effective:
                bucket_bytes += b
                if ts - bucket_t0 >= 1.0:
                    snaps.append((bucket_bytes * 8) / ((ts - bucket_t0) * 1_000_000))
                    bucket_bytes, bucket_t0 = 0, ts
            if len(snaps) >= 2:
                snaps_sorted = sorted(snaps)
                m = len(snaps_sorted) // 2
                dl_mbps = round(snaps_sorted[m] if len(snaps_sorted) % 2 else (snaps_sorted[m-1]+snaps_sorted[m])/2, 2)
            else:
                dl_mbps = round((eff_bytes * 8) / (eff_elapsed * 1_000_000), 2)
        else:
            total = sum(b for b, _ in chunks)
            dl_mbps = round((total * 8) / (max(t_end - t_start, 1) * 1_000_000), 2)

        # Some mobile networks throttle or delay parallel streams long enough
        # that none produces a chunk before the timed test stops. Retry once
        # with a bounded single stream so the result is still measurable.
        if dl_mbps == 0:
            try:
                fallback_start = _time.perf_counter()
                fallback_bytes = 0
                fallback_url = "https://speed.cloudflare.com/__down?bytes=10485760"
                with _ureq.urlopen(
                    _ureq.Request(fallback_url, headers={"User-Agent": "SpeedTest/2.0"}),
                    timeout=20,
                ) as response:
                    while True:
                        chunk = response.read(65536)
                        if not chunk:
                            break
                        fallback_bytes += len(chunk)

                fallback_elapsed = max(_time.perf_counter() - fallback_start, 0.001)
                dl_mbps = round((fallback_bytes * 8) / (fallback_elapsed * 1_000_000), 2)
            except Exception as e:
                detail = dl_errors[0] if dl_errors else "no data received"
                _upd(error=f"Download: {detail}; fallback: {e}")

        _upd(download=dl_mbps, status="done", stage="done")
    except Exception as e:
        _upd(error=f"Download: {e}", status="error", stage="done")


def run_speedtest_thread():
    global speedtest_data
    if speedtest_data.get("status") == "running":
        return

    import time, urllib.request, urllib.parse, concurrent.futures

    test_nodes = [
        {
            "name": "Bangkok, Thailand",
            "code": "BKK",
            "ping_url": "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js",
            "dl_urls": [
                "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js",
                "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js",
                "https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css",
                "https://code.jquery.com/jquery-3.7.0.min.js"
            ]
        },
        {
            "name": "Singapore",
            "code": "SIN",
            "ping_url": "https://sin.download.datapacket.com/10mb.bin",
            "dl_urls": ["https://sin.download.datapacket.com/10mb.bin"]
        },
        {
            "name": "Hong Kong",
            "code": "HKG",
            "ping_url": "https://hkg.download.datapacket.com/10mb.bin",
            "dl_urls": ["https://hkg.download.datapacket.com/10mb.bin"]
        },
        {
            "name": "Tokyo, Japan",
            "code": "NRT",
            "ping_url": "https://tyo.download.datapacket.com/10mb.bin",
            "dl_urls": ["https://tyo.download.datapacket.com/10mb.bin"]
        },
        {
            "name": "Cloudflare Global",
            "code": "GLOBAL",
            "ping_url": "https://1.1.1.1",
            "dl_urls": ["https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"]
        }
    ]

    speedtest_data["status"] = "running"
    speedtest_data["stage"] = "Finding Best Server"

    # 1. Multi-region Server Latency Discovery
    best_node = test_nodes[0]
    min_lat = 99999.0

    for node in test_nodes:
        node_pings = []
        for _ in range(2):
            t0 = time.time()
            try:
                req = urllib.request.Request(node["ping_url"], headers={"User-Agent": "Mozilla/5.0"})
                with urllib.request.urlopen(req, timeout=2.5) as r:
                    r.read(512)
                node_pings.append((time.time() - t0) * 1000)
            except Exception:
                pass
            time.sleep(0.02)
        
        if node_pings:
            avg_p = sum(node_pings) / len(node_pings)
            if avg_p < min_lat:
                min_lat = avg_p
                best_node = node

    speedtest_data["server"] = {
        "name": best_node["name"],
        "code": best_node["code"],
        "latency_ms": round(min_lat, 1)
    }

    # 2. Testing Latency & Jitter (8 ping samples)
    speedtest_data["stage"] = "Testing Latency"
    pings = []
    for _ in range(8):
        t0 = time.time()
        try:
            req = urllib.request.Request(best_node["ping_url"], headers={"User-Agent": "Mozilla/5.0"})
            with urllib.request.urlopen(req, timeout=3) as r:
                r.read(512)
            pings.append((time.time() - t0) * 1000)
        except Exception:
            pass
        time.sleep(0.04)

    ping = round(sum(pings) / len(pings), 1) if pings else round(min_lat, 1)
    jitter = round(sum(abs(pings[i] - pings[i-1]) for i in range(1, len(pings))) / (len(pings) - 1), 1) if len(pings) > 1 else 0.0
    speedtest_data["ping_ms"] = ping
    speedtest_data["jitter_ms"] = jitter
    speedtest_data["server"]["latency_ms"] = ping

    # 3. Testing Download (Parallel Chunking)
    speedtest_data["stage"] = "Testing Download"
    dl_targets = best_node["dl_urls"] * 3

    def fetch_dl(u):
        try:
            req = urllib.request.Request(u, headers={"User-Agent": "Mozilla/5.0"})
            with urllib.request.urlopen(req, timeout=5) as r:
                return len(r.read())
        except Exception:
            return 0

    t0 = time.time()
    total_bytes = 0
    with concurrent.futures.ThreadPoolExecutor(max_workers=6) as ex:
        futures = [ex.submit(fetch_dl, u) for u in dl_targets]
        for f in concurrent.futures.as_completed(futures):
            total_bytes += f.result()
            if time.time() - t0 >= 4.5:
                break

    dur = time.time() - t0
    dl_mbps = round((total_bytes * 8 / dur) / 1_000_000, 2) if dur > 0 else 0.0
    speedtest_data["download_mbps"] = dl_mbps

    # 4. Testing Upload (3 Iteration Average)
    speedtest_data["stage"] = "Testing Upload"
    up_results = []
    dummy_data = b"0" * (2 * 1024 * 1024)

    for _ in range(3):
        t0 = time.time()
        try:
            req = urllib.request.Request(
                "https://speed.cloudflare.com/__up",
                data=dummy_data,
                method="POST",
                headers={"User-Agent": "SpeedTest/1.0", "Content-Type": "application/octet-stream"}
            )
            with urllib.request.urlopen(req, timeout=6) as r:
                r.read()
            dur = time.time() - t0
            if dur > 0:
                up_results.append((len(dummy_data) * 8 / dur) / 1_000_000)
        except Exception:
            pass
        time.sleep(0.05)

    avg_up = round(sum(up_results) / len(up_results), 2) if up_results else 0.0
    speedtest_data["upload_mbps"] = avg_up

    # 5. Complete
    speedtest_data["stage"] = "Complete"
    speedtest_data["status"] = "idle"
    speedtest_data["last_test"] = int(time.time())

def ping_url_thread():
    import urllib.parse, http.client, socket, ssl, time

    def resolve_dns_udp(domain, dns_server="8.8.8.8"):
        try:
            packet = bytearray([0x12, 0x34, 0x01, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00])
            for part in domain.split('.'):
                packet.append(len(part))
                packet.extend(part.encode('ascii'))
            packet.append(0)
            packet.extend([0x00, 0x01, 0x00, 0x01])
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            sock.settimeout(2)
            sock.sendto(packet, (dns_server, 53))
            data, addr = sock.recvfrom(512)
            answers = (data[6] << 8) + data[7]
            if answers == 0:
                return None
            idx = 12
            while data[idx] != 0:
                idx += data[idx] + 1
            idx += 5
            for _ in range(answers):
                if (data[idx] & 0xC0) == 0xC0:
                    idx += 2
                else:
                    while data[idx] != 0:
                        idx += data[idx] + 1
                    idx += 1
                atype = (data[idx] << 8) + data[idx+1]
                rdlen = (data[idx+8] << 8) + data[idx+9]
                idx += 10
                if atype == 1 and rdlen == 4:
                    return ".".join(str(b) for b in data[idx:idx+4])
                idx += rdlen
        except Exception:
            pass
        return None

    while True:
        url = get_cf_url()
        if url and url != "Not Found" and not any(loc in url for loc in ["localhost", "127.0.0.1", "192.168."]):
            try:
                parsed = urllib.parse.urlparse(url)
                domain = parsed.netloc
                start_time = time.time()
                
                # Resolve DNS directly via UDP to bypass Android system DNS negative cache
                ip = resolve_dns_udp(domain)
                if not ip:
                    ip = domain
                
                if parsed.scheme == "https":
                    ctx = ssl._create_unverified_context()
                    conn = http.client.HTTPSConnection(ip, timeout=3, context=ctx)
                else:
                    conn = http.client.HTTPConnection(ip, timeout=3)
                
                conn.request("HEAD", "/", headers={"Host": domain})
                conn.getresponse()
                
                url_status["ping_ms"] = int((time.time() - start_time) * 1000)
                url_status["online"] = True
            except Exception:
                url_status["online"] = False
                url_status["ping_ms"] = 0
        else:
            url_status["online"] = False
            url_status["ping_ms"] = 0
            
        time.sleep(5)

# ------- Data Collection -------

def get_cf_url():
    # 1. Check docs/active_url.json first
    json_path = os.path.join(os.path.dirname(ENV_PATH), "docs", "active_url.json")
    if os.path.exists(json_path):
        try:
            with open(json_path, "r") as f:
                data = json.load(f)
                url = data.get("url", "").strip()
                if url and "trycloudflare" in url:
                    return url
        except Exception:
            pass

    # 2. Read APP_URL from .env
    if os.path.exists(ENV_PATH):
        try:
            with open(ENV_PATH) as f:
                for line in f:
                    if line.startswith("APP_URL="):
                        val = line.split("=", 1)[1].strip()
                        if (val.startswith('"') and val.endswith('"')) or (val.startswith("'") and val.endswith("'")):
                            val = val[1:-1]
                        return val
        except Exception:
            pass
    return "Not Found"

line_status_cache = {"status": "Checking...", "error": None, "last_check": 0}

def get_line_status():
    global line_status_cache
    now = time.time()
    if now - line_status_cache.get("last_check", 0) < 60:
        return line_status_cache

    token = None
    try:
        if os.path.exists(ENV_PATH):
            with open(ENV_PATH, "r") as f:
                for line in f:
                    if line.startswith("LINE_CHANNEL_ACCESS_TOKEN="):
                        token = line.split("=", 1)[1].strip()
                        if (token.startswith('"') and token.endswith('"')) or (token.startswith("'") and token.endswith("'")):
                            token = token[1:-1]
                        break
    except Exception as e:
        line_status_cache = {"status": "Error", "error": f"Failed to read .env: {str(e)}", "last_check": now}
        return line_status_cache

    if not token:
        line_status_cache = {"status": "Not Configured", "error": "LINE_CHANNEL_ACCESS_TOKEN missing from .env", "last_check": now}
        return line_status_cache

    import urllib.request, json
    try:
        req = urllib.request.Request("https://api.line.me/v2/bot/info")
        req.add_header("Authorization", f"Bearer {token}")
        proxy_support = urllib.request.ProxyHandler({})
        opener = urllib.request.build_opener(proxy_support)
        with opener.open(req, timeout=3) as response:
            res_data = json.loads(response.read().decode("utf-8"))
            line_status_cache = {
                "status": "Online",
                "error": None,
                "bot_name": res_data.get("displayName", "LINE OA"),
                "basic_id": res_data.get("basicId", ""),
                "last_check": now
            }
    except Exception as e:
        err_msg = str(e)
        if hasattr(e, 'code'):
            if e.code == 401:
                err_msg = "401 Unauthorized (Invalid Access Token)"
            else:
                err_msg = f"HTTP Error {e.code}"
        line_status_cache = {"status": "Offline", "error": err_msg, "last_check": now}

    return line_status_cache


def get_memory():
    try:
        info = {}
        with open("/proc/meminfo") as f:
            for line in f:
                parts = line.split()
                if len(parts) >= 2:
                    info[parts[0].rstrip(":")] = int(parts[1])
        total = info.get("MemTotal", 0)
        avail = info.get("MemAvailable", 0)
        used = total - avail
        return {
            "total_mb": round(total / 1024),
            "available_mb": round(avail / 1024),
            "used_mb": round(used / 1024),
            "percent": round((used / total) * 100, 1) if total else 0,
        }
    except Exception:
        return {}

def get_load():
    try:
        with open("/proc/loadavg") as f:
            parts = f.read().split()
            return [float(parts[0]), float(parts[1]), float(parts[2])]
    except Exception:
        return [0.0, 0.0, 0.0]

def get_temp():
    try:
        with open("/sys/class/thermal/thermal_zone0/temp", "r") as f:
            t = int(f.read().strip())
            return str(round(t / 1000, 1))
    except Exception:
        return "N/A"

def get_disk():
    try:
        import os
        st = os.statvfs("/data/data/com.termux/files/home")
        total_b = st.f_blocks * st.f_frsize
        free_b = st.f_bavail * st.f_frsize
        used_b = total_b - free_b
        return {
            "total_gb": round(total_b / (1024**3), 2),
            "used_gb": round(used_b / (1024**3), 2),
            "percent": round((used_b / total_b) * 100, 1) if total_b > 0 else 0
        }
    except Exception:
        return {"total_gb": 0, "used_gb": 0, "percent": 0}

last_rx = 0
last_tx = 0
last_net_time = 0

def get_network_info():
    import subprocess
    info = {
        "interface": "wlan0",
        "gateway": "192.168.1.1",
        "dns": "8.8.8.8, 10.8.2.1"
    }
    try:
        res = subprocess.run(["ip", "addr", "show", "wlan0"], capture_output=True, text=True)
        for line in res.stdout.split('\n'):
            if "inet " in line:
                info["local_ip"] = line.strip().split()[1]
            if "link/ether" in line:
                info["mac"] = line.strip().split()[1]
    except:
        pass
    return info

def get_network():
    global last_rx, last_tx, last_net_time
    import time
    try:
        with open("/proc/net/dev") as f:
            for line in f:
                parts = line.split(":")
                if len(parts) == 2 and parts[0].strip() == "wlan0":
                    stats = parts[1].split()
                    rx = int(stats[0])
                    tx = int(stats[8])
                    now = time.time()
                    
                    rx_rate = 0.0
                    tx_rate = 0.0
                    if last_rx > 0 and last_tx > 0:
                        diff = now - last_net_time
                        if diff > 0:
                            rx_rate = ((rx - last_rx) / 1024) / diff
                            tx_rate = ((tx - last_tx) / 1024) / diff
                            
                    last_rx = rx
                    last_tx = tx
                    last_net_time = now
                    
                    return {
                        "rx_rate": round(rx_rate, 2),
                        "tx_rate": round(tx_rate, 2),
                        "total_rx": round(rx / (1024**2), 2),
                        "total_tx": round(tx / (1024**2), 2),
                    }
    except Exception:
        pass
    return {"rx_rate": 0, "tx_rate": 0, "total_rx": 0, "total_tx": 0}

def get_logs():
    logs = []
    try:
        lines = os.popen(f"tail -n 15 {NGINX_LOG}").read().strip().split("\n")
        for line in reversed(lines):
            if not line:
                continue
            parts = line.split('"')
            if len(parts) < 3:
                continue
            meta = parts[0].split()
            ip = meta[0] if meta else "?"
            time_str = meta[3][1:] if len(meta) > 3 else "?"
            req = parts[1]
            sp = parts[2].split()
            status = sp[0] if sp else "?"
            size = sp[1] if len(sp) > 1 else "0"
            logs.append({"ip": ip, "time": time_str, "req": req, "status": status, "size": size})
    except Exception:
        pass
    return logs

def get_deploy_logs():
    deploy_log_path = "/data/data/com.termux/files/home/uni-activity/storage/logs/deploy.log"
    if os.path.exists(deploy_log_path):
        try:
            with open(deploy_log_path, "r", encoding="utf-8", errors="replace") as f:
                lines = f.readlines()
                return "".join(lines[-200:])
        except Exception as e:
            return f"Error reading deploy log: {str(e)}"
    return "No deployment log found."

github_events_cache = {"data": [], "last_fetch": 0}

def get_github_events():
    """Fetch real-time commit & deployment events directly from GitHub REST API."""
    global github_events_cache
    now = time.time()

    # Cache for 5 seconds to comply with GitHub REST API rate limits
    if github_events_cache["data"] and (now - github_events_cache["last_fetch"] < 5):
        return github_events_cache["data"]

    events = []
    try:
        import urllib.request, json, datetime

        url = "https://api.github.com/repos/GitNonta/uni-activity/commits?per_page=15"
        req = urllib.request.Request(url, headers={
            "User-Agent": "Uni-Activity-Monitor/1.0",
            "Accept": "application/vnd.github.v3+json"
        })

        with urllib.request.urlopen(req, timeout=4) as response:
            if response.status == 200:
                raw_json = json.loads(response.read().decode("utf-8"))
                for idx, item in enumerate(raw_json):
                    sha_full = item.get("sha", "")
                    sha = sha_full[:7]
                    commit_obj = item.get("commit", {})
                    msg = commit_obj.get("message", "").split("\n")[0]
                    author_obj = commit_obj.get("author", {})
                    author_name = author_obj.get("name", "GitNonta")
                    date_iso = author_obj.get("date", "")

                    dt_str = date_iso
                    try:
                        dt_obj = datetime.datetime.fromisoformat(date_iso.replace("Z", "+00:00"))
                        dt_str = dt_obj.strftime("%B %e, %Y at %I:%M %p")
                    except Exception:
                        pass

                    status_type = "success"
                    detail = f"GitHub Live REST API • Synced with GitHub ({author_name})"
                    if idx == 0:
                        status_type = "success"
                        detail = f"🟢 GitHub Live REST API • Latest Active Commit on GitHub ({author_name})"

                    events.append({
                        "id": f"gh-{sha}-status",
                        "type": status_type,
                        "hash": sha,
                        "message": msg,
                        "detail": detail,
                        "timestamp": dt_str
                    })

                    events.append({
                        "id": f"gh-{sha}-start",
                        "type": "started",
                        "hash": sha,
                        "message": msg,
                        "detail": f"GitHub Push Event by {author_name}",
                        "timestamp": dt_str
                    })

                if events:
                    github_events_cache["data"] = events
                    github_events_cache["last_fetch"] = now
                    return events
    except Exception:
        pass

    # Fallback to local git log if offline
    try:
        import subprocess
        repo_dir = "/data/data/com.termux/files/home/uni-activity"
        if not os.path.exists(repo_dir):
            repo_dir = str(Path(__file__).parent.parent)

        res = subprocess.run(
            ["git", "log", "-n", "15", '--pretty=format:%h|%s|%cd|%an', '--date=format:%B %e, %Y at %I:%M %p'],
            cwd=repo_dir, capture_output=True, text=True, timeout=4
        )
        if res.returncode == 0 and res.stdout.strip():
            lines = res.stdout.strip().split("\n")
            for idx, line in enumerate(lines):
                parts = line.split("|", 3)
                if len(parts) == 4:
                    h, msg, dt, author = parts[0], parts[1], parts[2], parts[3]
                    events.append({
                        "id": f"event-{h}-status",
                        "type": "success",
                        "hash": h,
                        "message": msg,
                        "detail": f"Local Git Log • Active Deployment ({author})",
                        "timestamp": dt
                    })
    except Exception:
        pass

    return events

def get_ai_logs():
    if len(remote_ai_logs) > 0:
        return "".join(remote_ai_logs)
        
    ai_log_path = "/data/data/com.termux/files/home/uni-activity/ai_service/server.log"
    if os.path.exists(ai_log_path):
        try:
            with open(ai_log_path, "r", encoding="utf-8", errors="replace") as f:
                lines = f.readlines()
                return "".join(lines[-200:])
        except Exception as e:
            return f"Error reading AI log: {str(e)}"
    return "No AI Scan Service log found."

def get_active_sessions():
    sessions = []
    try:
        if os.path.exists("/proc/net/tcp"):
            with open("/proc/net/tcp", "r") as f:
                lines = f.readlines()
            
            # Port 8022 in hex is 1F56
            ssh_port_hex = "1F56"
            for line in lines[1:]:
                parts = line.split()
                if len(parts) >= 4:
                    local_addr = parts[1]
                    remote_addr = parts[2]
                    state = parts[3]
                    
                    # State "01" is ESTABLISHED
                    if state == "01" and local_addr.endswith(":" + ssh_port_hex):
                        r_ip_hex, r_port_hex = remote_addr.split(":")
                        r_ip = ".".join(str(int(r_ip_hex[i:i+2], 16)) for i in (6, 4, 2, 0))
                        r_port = int(r_port_hex, 16)
                        sessions.append(f"{r_ip}:{r_port}")
    except Exception as e:
        pass
    return sessions

def get_sftp_active():
    import subprocess
    try:
        res = subprocess.run(["pgrep", "-f", "sftp"], capture_output=True, text=True)
        return len(res.stdout.strip().split('\n')) if res.stdout.strip() else 0
    except:
        return 0



def get_battery():
    try:
        import subprocess, json
        res = subprocess.run(["termux-battery-status"], capture_output=True, text=True, timeout=1)
        if res.returncode == 0:
            data = json.loads(res.stdout)
            return {
                "percent": data.get("percentage", 0),
                "status": data.get("status", "UNKNOWN"),
                "current_ua": data.get("current", 0),
                "voltage_mv": data.get("voltage", 0),
                "charge_counter_uah": data.get("charge_counter", 0)
            }
    except Exception:
        pass
    return None

def get_listening_ports():
    import subprocess, re
    ports = set()
    
    # Method 1: ss -ltn
    try:
        res = subprocess.run(["ss", "-ltn"], capture_output=True, text=True, timeout=1)
        if res.returncode == 0:
            for line in res.stdout.split('\n'):
                parts = line.split()
                if len(parts) >= 4 and "LISTEN" in parts[0]:
                    match = re.search(r':(\d+)$', parts[3])
                    if match:
                        ports.add(int(match.group(1)))
    except:
        pass
        
    # Method 2: netstat -ltn
    if not ports:
        try:
            res = subprocess.run(["netstat", "-ltn"], capture_output=True, text=True, timeout=1)
            if res.returncode == 0:
                for line in res.stdout.split('\n'):
                    parts = line.split()
                    if len(parts) >= 4 and "LISTEN" in line:
                        match = re.search(r':(\d+)$', parts[3])
                        if match:
                            ports.add(int(match.group(1)))
        except:
            pass
            
    # Method 3: /proc/net/tcp
    if not ports:
        try:
            for path in ["/proc/net/tcp", "/proc/net/tcp6"]:
                if os.path.exists(path):
                    with open(path, "r") as f:
                        lines = f.readlines()
                    for line in lines[1:]:
                        parts = line.split()
                        if len(parts) >= 4:
                            state = parts[3]
                            if state == "0A":
                                local_address = parts[1]
                                port_hex = local_address.split(":")[1]
                                ports.add(int(port_hex, 16))
        except:
            pass
            
    return sorted(list(ports))

def get_services():
    import subprocess
    services = {
        "Nginx": ("nginx", 8080),
        "PHP-FPM": ("php-fpm", None),
        "PostgreSQL": ("postgres", 5432),
        "Redis": ("redis-server", 6379),
        "Cloudflared": ("cloudflared", None),
        "Reverb": ("reverb:start", 8082),
        "Queue Worker": ("artisan queue:work", None),
        "AI Scan Service": ("python server.py", 8001),
        "SSH": ("sshd", 8022),
        "SFTP": ("sshd", 8022)
    }
    
    listening = get_listening_ports()
    
    status = {}
    for name, (proc, default_port) in services.items():
        try:
            res = subprocess.run(["pgrep", "-f", proc], capture_output=True, text=True)
            is_running = bool(res.stdout.strip())
            
            if is_running:
                if default_port and default_port in listening:
                    status[name] = f"Running (Port {default_port})"
                else:
                    status[name] = "Running"
            else:
                status[name] = "Stopped"
        except Exception:
            status[name] = "Unknown"
    return status

# --- Advanced Metrics Helpers ---
prev_net_bytes = {"rx": 0, "tx": 0, "time": 0}

def get_cpu_freqs():
    freqs = []
    try:
        import glob
        files = sorted(glob.glob("/sys/devices/system/cpu/cpu[0-9]/cpufreq/scaling_cur_freq"))
        for f in files:
            with open(f, "r") as file:
                freqs.append(int(file.read().strip()) // 1000) # Convert to MHz
    except:
        pass
    return freqs

def get_wifi_rssi():
    try:
        if os.path.exists("/proc/net/wireless"):
            with open("/proc/net/wireless", "r") as f:
                lines = f.readlines()
            for line in lines[2:]:
                parts = line.split()
                if len(parts) >= 4:
                    level = parts[3].replace(".", "")
                    return int(level)
    except:
        pass
    return None

def get_net_speeds():
    global prev_net_bytes
    import time
    rx = 0
    tx = 0
    try:
        with open("/proc/net/dev", "r") as f:
            lines = f.readlines()
        for line in lines[2:]:
            if "wlan0" in line or "rmnet" in line or "dummy" in line or "eth0" in line:
                parts = line.split()
                if len(parts) >= 10:
                    rx += int(parts[1])
                    tx += int(parts[9])
    except:
        pass
    
    now = time.time()
    dt = now - prev_net_bytes["time"]
    rx_speed = 0
    tx_speed = 0
    if prev_net_bytes["time"] > 0 and dt > 0:
        rx_speed = max(0.0, (rx - prev_net_bytes["rx"]) / dt)
        tx_speed = max(0.0, (tx - prev_net_bytes["tx"]) / dt)
        
    prev_net_bytes = {"rx": rx, "tx": tx, "time": now}
    return {
        "rx_kbps": round(rx_speed / 1024.0, 1),
        "tx_kbps": round(tx_speed / 1024.0, 1)
    }

def get_top_processes():
    import subprocess
    procs = []
    try:
        res = subprocess.run(["ps", "-A", "-o", "pid,comm,pcpu,pmem"], capture_output=True, text=True, timeout=1)
        lines = res.stdout.strip().split('\n')
        for line in lines[1:]:
            parts = line.split()
            if len(parts) >= 4:
                try:
                    pid = parts[0]
                    comm = parts[1]
                    cpu = float(parts[2])
                    mem = float(parts[3])
                    if comm in ["ps", "top", "grep", "ss", "netstat"]:
                        continue
                    procs.append({"pid": pid, "name": comm, "cpu": cpu, "mem": mem})
                except:
                    pass
        procs = sorted(procs, key=lambda x: x["cpu"], reverse=True)[:5]
    except:
        pass
    return procs

def get_postgres_stats():
    import subprocess
    stats = {"db_size": "—", "connections": 0}
    try:
        res1 = subprocess.run(["psql", "-d", "uni_activity", "-t", "-c", "SELECT count(*) FROM pg_stat_activity;"], capture_output=True, text=True, timeout=1)
        if res1.returncode == 0 and res1.stdout.strip():
            stats["connections"] = int(res1.stdout.strip())
        res2 = subprocess.run(["psql", "-d", "uni_activity", "-t", "-c", "SELECT pg_size_pretty(pg_database_size('uni_activity'));"], capture_output=True, text=True, timeout=1)
        if res2.returncode == 0 and res2.stdout.strip():
            stats["db_size"] = res2.stdout.strip()
    except:
        pass
    return stats

def get_redis_stats():
    import subprocess
    stats = {"used_memory": "—", "clients": 0}
    try:
        res = subprocess.run(["redis-cli", "info", "memory"], capture_output=True, text=True, timeout=1)
        for line in res.stdout.split('\n'):
            if "used_memory_human:" in line:
                stats["used_memory"] = line.split(":")[1].strip()
        res2 = subprocess.run(["redis-cli", "info", "clients"], capture_output=True, text=True, timeout=1)
        for line in res2.stdout.split('\n'):
            if "connected_clients:" in line:
                stats["clients"] = int(line.split(":")[1].strip())
    except:
        pass
    return stats

def get_queue_stats():
    import subprocess
    stats = {"pending": 0, "failed": 0}
    try:
        res1 = subprocess.run(["redis-cli", "llen", "queues:default"], capture_output=True, text=True, timeout=1)
        if res1.returncode == 0 and res1.stdout.strip():
            stats["pending"] = int(res1.stdout.strip())
            
        res2 = subprocess.run(["psql", "-d", "uni_activity", "-t", "-c", "SELECT count(*) FROM failed_jobs;"], capture_output=True, text=True, timeout=1)
        if res2.returncode == 0 and res2.stdout.strip():
            stats["failed"] = int(res2.stdout.strip())
    except:
        pass
    return stats

def get_cloudflared_stats():
    import urllib.request, re, subprocess
    stats = {"latency_ms": 0}
    try:
        # Try direct urllib with proxy bypass first
        proxy_support = urllib.request.ProxyHandler({})
        opener = urllib.request.build_opener(proxy_support)
        opener.addheaders = [('User-agent', 'Mozilla/5.0')]
        with opener.open("http://127.0.0.1:20241/metrics", timeout=1) as response:
            content = response.read().decode('utf-8')
        match = re.search(r'quic_client_smoothed_rtt\{[^}]*\}\s+([0-9.]+)', content)
        if match:
            stats["latency_ms"] = round(float(match.group(1)), 1)
    except Exception:
        # Fallback to local curl binary which is highly reliable on Termux
        try:
            res = subprocess.run(["curl", "-s", "-m", "1", "http://127.0.0.1:20241/metrics"], capture_output=True, text=True, timeout=1)
            if res.returncode == 0:
                match = re.search(r'quic_client_smoothed_rtt\{[^}]*\}\s+([0-9.]+)', res.stdout)
                if match:
                    stats["latency_ms"] = round(float(match.group(1)), 1)
        except:
            pass
    return stats

def get_gpu_stats():
    import os
    stats = {"freq_mhz": 0, "load_percent": 0, "status": "Not Supported"}
    try:
        freq_path = "/sys/class/kgsl/kgsl-3d0/gpuclk"
        if os.path.exists(freq_path):
            try:
                with open(freq_path, "r") as f:
                    stats["freq_mhz"] = int(f.read().strip()) // 1000000
                stats["status"] = "Active"
            except PermissionError:
                stats["status"] = "SELinux Protected"
                stats["freq_mhz"] = "Permission Denied"
                stats["load_percent"] = "Permission Denied"
                return stats
        
        busy_path = "/sys/class/kgsl/kgsl-3d0/gpubusy"
        if os.path.exists(busy_path):
            with open(busy_path, "r") as f:
                parts = f.read().strip().split()
                if len(parts) == 2:
                    active = int(parts[0])
                    total = int(parts[1])
                    if total > 0:
                        stats["load_percent"] = round((active / total) * 100, 1)
    except:
        pass
    return stats

server_info_cache = None

def get_server_info():
    global server_info_cache
    if server_info_cache is not None:
        return server_info_cache
    
    import platform, subprocess
    info = {
        "Hostname": platform.node(),
        "OS / Kernel": platform.system() + " " + platform.release(),
        "Architecture": platform.machine(),
        "Python Version": platform.python_version()
    }
    
    try:
        model = subprocess.run(["getprop", "ro.product.model"], capture_output=True, text=True).stdout.strip()
        android_ver = subprocess.run(["getprop", "ro.build.version.release"], capture_output=True, text=True).stdout.strip()
        if model: info["Device Model"] = model
        if android_ver: info["Android Version"] = android_ver
    except:
        pass
        
    try:
        php_ver = subprocess.run(["php", "-r", "echo PHP_VERSION;"], capture_output=True, text=True).stdout.strip()
        if php_ver: info["PHP Version"] = php_ver
    except:
        pass
        
    server_info_cache = info
    return info

def get_uptime():
    try:
        import subprocess
        res = subprocess.run(["uptime", "-p"], capture_output=True, text=True)
        if res.stdout.strip():
            return res.stdout.strip().replace("up ", "")
            
        with open('/proc/uptime', 'r') as f:
            uptime_seconds = float(f.readline().split()[0])
            hours = int(uptime_seconds // 3600)
            minutes = int((uptime_seconds % 3600) // 60)
            return f"{hours}h {minutes}m"
    except:
        return "N/A"

def get_alerts(stats):
    global active_alert_ids
    alerts = []
    
    # 1. Cloudflare Connection Offline
    if not stats.get("cf_status", {}).get("online", False):
        alerts.append({"id": "cf_offline", "type": "critical", "message": "Cloudflare Tunnel is Offline!"})
        
    # 2. Services Crash
    offline_services = []
    for svc, status in stats.get("services", {}).items():
        if status == "Stopped":
            offline_services.append(svc)
    if offline_services:
        alerts.append({"id": "service_crash", "type": "critical", "message": f"Service(s) Offline: {', '.join(offline_services)}"})
        
    # 3. High CPU Load
    load = stats.get("load", [0,0,0])[0]
    if load > 6.0:
        alerts.append({"id": "high_load", "type": "warning", "message": f"High CPU Load: {load}"})
        
    # 4. Overheating
    try:
        temp = float(stats.get("temp", 0))
        if temp > 75.0:
            alerts.append({"id": "high_temp", "type": "warning", "message": f"Server Overheating: {temp}°C"})
    except:
        pass
        
    # 5. High Memory Usage
    mem_percent = stats.get("memory", {}).get("percent", 0)
    if mem_percent > 90:
        alerts.append({"id": "high_mem", "type": "warning", "message": f"High Memory Usage: {mem_percent}%"})
        
    # 6. High Storage Usage
    disk_percent = stats.get("disk", {}).get("percent", 0)
    if disk_percent > 90:
        alerts.append({"id": "high_disk", "type": "warning", "message": f"Disk Space Low: {disk_percent}% used"})
        
    # 7. Abnormal Traffic Spike (Per IP)
    import time
    current_time = time.time()
    ip_counts = {}
    for log in inspector_logs:
        server_time = log.get("server_time", 0)
        if current_time - server_time <= 10:
            ip = log.get("ip", "unknown")
            ip_counts[ip] = ip_counts.get(ip, 0) + 1
            
    for ip, count in ip_counts.items():
        if count >= 40: # 40 requests in 10s from a single IP
            alerts.append({"id": f"traffic_spike_{ip}", "type": "warning", "message": f"Abnormal Traffic: {count} reqs in 10s from {ip}"})
            
    # Track history
    current_ids = set()
    from datetime import datetime
    for a in alerts:
        current_ids.add(a["id"])
        if a["id"] not in active_alert_ids:
            history_item = a.copy()
            history_item["time"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            alerts_history.appendleft(history_item)
            
    active_alert_ids = current_ids
    return alerts

def collect_stats():
    stats = {
        "timestamp": int(time.time()),
        "uptime": get_uptime(),
        "server_info": get_server_info(),
        "cf_url": get_cf_url(),
        "cf_status": url_status,
        "speedtest": speedtest_data,
        "line_status": get_line_status(),
        "memory": get_memory(),
        "load": get_load(),
        "temp": get_temp(),
        "battery": get_battery(),
        "disk": get_disk(),
        "services": get_services(),
        "network": get_network(),
        "network_info": get_network_info(),
        "logs": get_logs(),
        "inspector": list(inspector_logs),
        "deploy_log": get_deploy_logs(),
        "events": get_github_events(),
        "ai_log": get_ai_logs(),
        "ssh_sessions": get_active_sessions(),
        "sftp_sessions": get_sftp_active(),
        "listening_ports": get_listening_ports(),
        "advanced_metrics": {
            "cpu_freqs": get_cpu_freqs(),
            "wifi_rssi": get_wifi_rssi(),
            "net_speeds": get_net_speeds(),
            "top_procs": get_top_processes(),
            "postgres": get_postgres_stats(),
            "redis": get_redis_stats(),
            "queue": get_queue_stats(),
            "cloudflared": get_cloudflared_stats(),
            "gpu": get_gpu_stats()
        }
    }
    stats["alerts"] = get_alerts(stats)
    stats["alerts_history"] = list(alerts_history)
    return stats

# ------- UDP Inspector Receiver -------
def udp_receiver_thread():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", UDP_PORT))
    while True:
        try:
            data, addr = sock.recvfrom(65535)
            if data:
                payload = json.loads(data.decode("utf-8"))
                payload['id'] = str(time.time()) + "-" + str(hash(data))
                payload['server_time'] = time.time()
                inspector_logs.appendleft(payload)
        except Exception:
            pass

# ------- UDP AI Logs Receiver -------
def udp_ai_receiver_thread():
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    sock.bind(("0.0.0.0", UDP_PORT_AI))
    while True:
        try:
            data, addr = sock.recvfrom(4096)
            msg = data.decode("utf-8", "ignore")
            remote_ai_logs.append(msg + "\n")
        except Exception:
            time.sleep(1)

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

def ws_client_thread(conn):
    """Handle a single WebSocket client — push stats every 2 seconds."""
    try:
        while True:
            payload = json.dumps(collect_stats())
            conn.sendall(ws_encode(payload))
            time.sleep(2)
    except Exception:
        pass
    finally:
        try:
            conn.close()
        except Exception:
            pass

# ------- HTTP Handler -------

class MonitorHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass  # Suppress access logs

    def _cors_headers(self):
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type, Cache-Control")
        self.send_header("Cache-Control", "no-store, no-cache, must-revalidate")
        self.send_header("Pragma", "no-cache")

    def do_OPTIONS(self):
        self.send_response(204)
        self._cors_headers()
        self.end_headers()

    def do_POST(self):
        # Upload endpoint — read & discard body, no disk write
        if self.path.startswith("/api/st/upload"):
            content_length = int(self.headers.get("Content-Length", 0))
            received = 0
            chunk_size = 65536
            while received < content_length:
                to_read = min(chunk_size, content_length - received)
                chunk = self.rfile.read(to_read)
                if not chunk:
                    break
                received += len(chunk)
            resp = json.dumps({"received_bytes": received}).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(resp)))
            self._cors_headers()
            self.end_headers()
            self.wfile.write(resp)
            return

        if self.path == "/api/speedtest":
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(b'{"status":"started"}')
            threading.Thread(target=run_speedtest_thread, daemon=True).start()
            return

        # External speedtest start (server-side, no CORS) — POST triggers background job
        if self.path == "/api/st/ext-start":
            started = start_ext_speedtest()
            resp_body = json.dumps({"status": "started" if started else "running"}).encode()
            self.send_response(202 if started else 409)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(resp_body)))
            self._cors_headers()
            self.end_headers()
            self.wfile.write(resp_body)
            if started:
                threading.Thread(target=run_ext_speedtest_thread, daemon=True).start()
            return

        if self.path == "/api/restart-tunnel":
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.end_headers()
            self.wfile.write(b'{"status":"ok"}')
            
            # Restart cloudflared in a background thread to not block the response
            def restart():
                import subprocess, time, re, os
                subprocess.run(["pkill", "cloudflared"])
                time.sleep(2)
                log_path = "/data/data/com.termux/files/home/cloudflared.log"
                # Clear old log
                with open(log_path, "w") as f:
                    f.write("")
                subprocess.Popen(f"nohup cloudflared tunnel --url http://localhost:8080 > {log_path} 2>&1 &", shell=True)
                
                # Wait for URL and update .env
                new_url = None
                for _ in range(15):
                    time.sleep(1)
                    if os.path.exists(log_path):
                        with open(log_path, "r") as f:
                            content = f.read()
                            match = re.search(r'https://[a-zA-Z0-9-]+\.trycloudflare\.com', content)
                            if match:
                                new_url = match.group(0)
                                break
                
                if new_url:
                    env_path = "/data/data/com.termux/files/home/uni-activity/.env"
                    if os.path.exists(env_path):
                        with open(env_path, "r") as f:
                            lines = f.readlines()
                        with open(env_path, "w") as f:
                            for line in lines:
                                if line.startswith("APP_URL="):
                                    f.write(f"APP_URL={new_url}\n")
                                else:
                                    f.write(line)
            
        if self.path == "/api/deploy/manual":
            def trigger_manual_deploy():
                import subprocess, time
                app_dir = "/data/data/com.termux/files/home/uni-activity"
                if not os.path.exists(app_dir):
                    app_dir = str(Path(__file__).parent.parent)
                sync_log = os.path.join(app_dir, "storage/logs/git-sync.log")
                try:
                    os.makedirs(os.path.dirname(sync_log), exist_ok=True)
                    with open(sync_log, "a", encoding="utf-8") as f:
                        f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Manual deploy triggered via Monitor UI Events.\n")
                except Exception:
                    pass
                subprocess.run(["git", "fetch", "origin", "main"], cwd=app_dir)
                subprocess.run(["git", "reset", "--hard", "origin/main"], cwd=app_dir)
                subprocess.run(["php", "artisan", "config:clear"], cwd=app_dir)
                subprocess.run(["php", "artisan", "route:clear"], cwd=app_dir)
                subprocess.run(["pkill", "-9", "-f", "php-fpm"])
                subprocess.Popen(["nohup", "php-fpm"], cwd=app_dir)

            threading.Thread(target=trigger_manual_deploy, daemon=True).start()
            resp = json.dumps({"status": "ok", "message": "Manual deployment triggered successfully"}).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self._cors_headers()
            self.end_headers()
            self.wfile.write(resp)
            return

        if self.path == "/api/deploy/rollback":
            content_length = int(self.headers.get("Content-Length", 0))
            body = self.rfile.read(content_length).decode('utf-8') if content_length > 0 else "{}"
            try:
                payload = json.loads(body)
            except Exception:
                payload = {}
            commit_hash = payload.get("commit_hash", "")

            if commit_hash:
                def trigger_rollback():
                    import subprocess, time
                    app_dir = "/data/data/com.termux/files/home/uni-activity"
                    if not os.path.exists(app_dir):
                        app_dir = str(Path(__file__).parent.parent)
                    sync_log = os.path.join(app_dir, "storage/logs/git-sync.log")
                    try:
                        os.makedirs(os.path.dirname(sync_log), exist_ok=True)
                        with open(sync_log, "a", encoding="utf-8") as f:
                            f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Rollback executed to commit {commit_hash} via Monitor UI Events.\n")
                    except Exception:
                        pass
                    subprocess.run(["git", "reset", "--hard", commit_hash], cwd=app_dir)
                    subprocess.run(["php", "artisan", "config:clear"], cwd=app_dir)
                    subprocess.run(["php", "artisan", "route:clear"], cwd=app_dir)
                    subprocess.run(["pkill", "-9", "-f", "php-fpm"])
                    subprocess.Popen(["nohup", "php-fpm"], cwd=app_dir)

                threading.Thread(target=trigger_rollback, daemon=True).start()
                resp = json.dumps({"status": "ok", "message": f"Rollback to commit {commit_hash} initiated"}).encode()
            else:
                resp = json.dumps({"status": "error", "message": "Missing commit_hash"}).encode()

            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self._cors_headers()
            self.end_headers()
            self.wfile.write(resp)
            return

        self.send_response(404)
        self.end_headers()
        self.wfile.write(b"Not Found")

    def do_GET(self):
        # WebSocket upgrade
        if self.headers.get("Upgrade", "").lower() == "websocket":
            self._handle_websocket()
            return

        # LAN Ping endpoint — multi-method: ICMP → TCP socket → HTTP timing fallback
        if self.path.startswith("/api/st/lan-ping"):
            from urllib.parse import urlparse, parse_qs
            import subprocess, re as _re, socket as _sock, time as _time, urllib.request as _ureq
            qs     = parse_qs(urlparse(self.path).query)
            target = qs.get("target", ["192.168.1.45"])[0]
            count  = min(int(qs.get("count", ["10"])[0]), 20)

            def _calc_stats(rtts, method):
                jitter = 0.0
                for i in range(1, len(rtts)):
                    jitter += (abs(rtts[i] - rtts[i-1]) - jitter) / 16
                return {
                    "ok": True, "target": target, "method": method,
                    "ping_ms":   round(sum(rtts) / len(rtts), 1),
                    "jitter_ms": round(jitter, 1),
                    "min_ms":    round(min(rtts), 1),
                    "max_ms":    round(max(rtts), 1),
                    "samples":   len(rtts),
                    "rtt_values": rtts,
                }

            resp = None

            # ── Layer 1: ICMP ping (may fail on Android/if target blocks ICMP) ──
            try:
                result = subprocess.run(
                    ["ping", "-c", str(count), "-W", "1", target],
                    capture_output=True, text=True, timeout=15
                )
                rtts = [float(m.group(1)) for m in
                    _re.finditer(r"time[=<]([\d.]+)\s*ms", result.stdout)]
                if rtts:
                    resp = _calc_stats(rtts, "ICMP")
            except Exception:
                pass

            # ── Layer 2: TCP socket connect (works if any port is open) ──
            if resp is None:
                TCP_PORTS = [9999, 80, 443, 22, 8080]
                for port in TCP_PORTS:
                    rtts = []
                    try:
                        for _ in range(count):
                            t0 = _time.perf_counter()
                            s  = _sock.create_connection((target, port), timeout=1)
                            rtt = (_time.perf_counter() - t0) * 1000
                            s.close()
                            rtts.append(round(rtt, 2))
                        if rtts:
                            resp = _calc_stats(rtts, f"TCP:{port}")
                            break
                    except Exception:
                        continue

            # ── Layer 3: HTTP GET timing to monitor port on target ──
            if resp is None:
                HTTP_URLS = [
                    f"http://{target}:9999/api/stats",
                    f"http://{target}/",
                    f"http://{target}:8080/",
                ]
                for url in HTTP_URLS:
                    rtts = []
                    try:
                        for _ in range(min(count, 5)):
                            t0 = _time.perf_counter()
                            _ureq.urlopen(url, timeout=1).read(64)
                            rtt = (_time.perf_counter() - t0) * 1000
                            rtts.append(round(rtt, 2))
                        if rtts:
                            resp = _calc_stats(rtts, f"HTTP")
                            break
                    except Exception:
                        continue

            if resp is None:
                resp = {
                    "ok": False, "target": target,
                    "ping_ms": 0, "jitter_ms": 0,
                    "error": "All methods failed (ICMP blocked, no open TCP port, no HTTP service)",
                    "method": "none",
                }

            data = json.dumps(resp).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(data)))
            self._cors_headers()
            self.end_headers()
            self.wfile.write(data)
            return

        # External speedtest status (server-side, no CORS issues)
        if self.path.startswith("/api/st/ext-status"):
            global _ext_job, _ext_lock
            with _ext_lock:
                data = json.dumps(_ext_job).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Content-Length", str(len(data)))
            self._cors_headers()
            self.end_headers()
            self.wfile.write(data)
            return

        # Download endpoint — generate random binary in-memory, no disk write
        if self.path.startswith("/api/st/download"):
            from urllib.parse import urlparse, parse_qs
            qs = parse_qs(urlparse(self.path).query)
            size = min(int(qs.get("size", ["104857600"])[0]), 256 * 1024 * 1024)  # max 256 MB
            self.send_response(200)
            self.send_header("Content-Type", "application/octet-stream")
            self.send_header("Content-Length", str(size))
            self._cors_headers()
            self.end_headers()
            chunk = os.urandom(65536)  # 64 KB random chunk, reused
            sent = 0
            try:
                while sent < size:
                    to_send = min(len(chunk), size - sent)
                    self.wfile.write(chunk[:to_send])
                    sent += to_send
            except Exception:
                pass
            return

        if self.path == "/api/stats" or self.path.startswith("/api/stats?"):
            data = json.dumps(collect_stats()).encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)
            return

        # Serve static React files
        path = self.path.split("?")[0]
        if path == "/" or path == "":
            path = "/index.html"
        
        file_path = STATIC_DIR / path.lstrip("/")
        
        if not file_path.exists() or not str(file_path).startswith(str(STATIC_DIR)):
            # SPA fallback
            file_path = STATIC_DIR / "index.html"
        
        if file_path.exists() and file_path.is_file():
            ext = file_path.suffix
            content_types = {
                ".html": "text/html; charset=utf-8",
                ".js": "application/javascript",
                ".css": "text/css",
                ".svg": "image/svg+xml",
                ".ico": "image/x-icon",
                ".png": "image/png",
                ".json": "application/json",
            }
            content_type = content_types.get(ext, "application/octet-stream")
            data = file_path.read_bytes()
            self.send_response(200)
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(len(data)))
            if ext in (".js", ".css"):
                self.send_header("Cache-Control", "public, max-age=3600")
            self.end_headers()
            self.wfile.write(data)
        else:
            self.send_response(404)
            self.end_headers()
            self.wfile.write(b"Not Found")

    def _handle_websocket(self):
        """Upgrade connection to WebSocket and spawn thread."""
        # Read the full HTTP request headers (already done by BaseHTTPRequestHandler)
        raw_request = f"GET {self.path} HTTP/1.1\r\n"
        for key, val in self.headers.items():
            raw_request += f"{key}: {val}\r\n"
        raw_request += "\r\n"
        
        conn = self.connection
        if ws_handshake(conn, raw_request):
            t = threading.Thread(target=ws_client_thread, args=(conn,), daemon=True)
            t.start()
            t.join()  # Block this handler thread until WS disconnects

# ------- Main -------

def manage_ai_service_thread():
    import subprocess
    import time
    import socket
    
    while True:
        try:
            # Check if port 8001 is open locally
            sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            sock.settimeout(1.5)
            result = sock.connect_ex(('127.0.0.1', 8001))
            sock.close()
            
            if result != 0:
                # Port is not open, meaning the server is stopped or starting.
                # Check if the process is already running to avoid duplicates.
                res = subprocess.run(["pgrep", "-f", "python server.py"], capture_output=True, text=True)
                if not res.stdout.strip():
                    # Kill any orphaned processes just in case
                    subprocess.run(["pkill", "-f", "python server.py"])
                    # Start the process in the background as a child of the monitor server
                    subprocess.Popen(
                        ['proot-distro', 'login', 'ubuntu', '--', 'bash', '-c', 
                         'cd /data/data/com.termux/files/home/uni-activity/ai_service && /root/ai_project/venv/bin/python server.py > server.log 2>&1'],
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.DEVNULL
                    )
        except Exception:
            pass
        time.sleep(10)

if __name__ == "__main__":
    t_udp = threading.Thread(target=udp_receiver_thread, daemon=True)
    t_udp.start()
    
    t_udp_ai = threading.Thread(target=udp_ai_receiver_thread, daemon=True)
    t_udp_ai.start()
    
    t_ping = threading.Thread(target=ping_url_thread, daemon=True)
    t_ping.start()
    
    t_ai = threading.Thread(target=manage_ai_service_thread, daemon=True)
    t_ai.start()
    
    server = ThreadingHTTPServer(("", PORT), MonitorHandler)
    server.allow_reuse_address = True
    print(f"[Monitor] Serving at http://0.0.0.0:{PORT}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("Shutting down.")
