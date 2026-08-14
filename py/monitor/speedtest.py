"""
monitor/speedtest.py — Speedtest threads (internal + external via Cloudflare).
"""
import time, threading, concurrent.futures
import monitor.config as cfg

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
    with cfg._ext_lock:
        if cfg._ext_job.get("status") == "running":
            return False

        with cfg._ext_lock:
            cfg._ext_job.update({
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
        with cfg._ext_lock:
            cfg._ext_job.update(kw)

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

    cfg.speedtest_data["status"] = "running"
    cfg.speedtest_data["stage"] = "Finding Best Server"

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

    cfg.speedtest_data["server"] = {
        "name": best_node["name"],
        "code": best_node["code"],
        "latency_ms": round(min_lat, 1)
    }

    # 2. Testing Latency & Jitter (8 ping samples)
    cfg.speedtest_data["stage"] = "Testing Latency"
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
    cfg.speedtest_data["ping_ms"] = ping
    cfg.speedtest_data["jitter_ms"] = jitter
    cfg.speedtest_data["server"]["latency_ms"] = ping

    # 3. Testing Download (Parallel Chunking)
    cfg.speedtest_data["stage"] = "Testing Download"
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
    cfg.speedtest_data["download_mbps"] = dl_mbps

    # 4. Testing Upload (3 Iteration Average)
    cfg.speedtest_data["stage"] = "Testing Upload"
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
    cfg.speedtest_data["upload_mbps"] = avg_up

    # 5. Complete
    cfg.speedtest_data["stage"] = "Complete"
    cfg.speedtest_data["status"] = "idle"
    cfg.speedtest_data["last_test"] = int(time.time())
