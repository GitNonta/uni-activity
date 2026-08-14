"""
monitor/http_handler.py — MonitorHandler: do_GET, do_POST, do_OPTIONS, WebSocket upgrade.
"""
import time, json, os, re, socket, subprocess, threading
from http.server import BaseHTTPRequestHandler
import monitor.config as cfg
from monitor.telegram import tg_send
from monitor.alerts import collect_stats
from monitor.speedtest import start_ext_speedtest, run_ext_speedtest_thread, run_speedtest_thread
from monitor.threads import ws_handshake, ws_client_thread
from monitor.collectors import get_cf_url
from pathlib import Path


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
            threading.Thread(target=restart, daemon=True).start()
            return

        if self.path.startswith("/api/deploy/manual"):
            import urllib.parse
            qs = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
            clear_cache = qs.get("clear_cache", ["false"])[0].lower() == "true"
            
            def trigger_manual_deploy():
                import subprocess, time, os
                app_dir = "/data/data/com.termux/files/home/uni-activity"
                if not os.path.exists(app_dir):
                    app_dir = str(Path(__file__).parent.parent)
                sync_log = os.path.join(app_dir, "storage/logs/git-sync.log")
                try:
                    os.makedirs(os.path.dirname(sync_log), exist_ok=True)
                    with open(sync_log, "w", encoding="utf-8") as f:
                        f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Manual deploy triggered via Monitor UI Events.\n")
                        f.flush()
                        
                        def run_and_log(cmd):
                            import subprocess, time
                            f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] > {' '.join(cmd)}\n")
                            f.flush()
                            proc = subprocess.Popen(cmd, cwd=app_dir, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
                            for line in iter(proc.stdout.readline, ''):
                                f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] {line}")
                                f.flush()
                            proc.stdout.close()
                            proc.wait()

                        run_and_log(["git", "fetch", "origin", "main"])
                        run_and_log(["git", "reset", "--hard", "origin/main"])
                        
                        if clear_cache:
                            f.write("Clearing build cache...\n")
                            f.flush()
                            run_and_log(["php", "artisan", "cache:clear"])
                            run_and_log(["php", "artisan", "view:clear"])
                            run_and_log(["npm", "cache", "clean", "--force"])
                            
                        run_and_log(["php", "artisan", "config:clear"])
                        run_and_log(["php", "artisan", "route:clear"])
                        run_and_log(["npm", "run", "build"]) # Generate build logs
                        
                        f.write("Restarting php-fpm...\n")
                        subprocess.run(["pkill", "-9", "-f", "php-fpm"])
                        subprocess.Popen(["nohup", "php-fpm"], cwd=app_dir)
                        f.write("Deploy finished.\n")
                        
                except Exception:
                    pass
                
                # Try to copy to per-commit log file
                try:
                    hash_res = subprocess.run(["git", "rev-parse", "--short", "origin/main"], cwd=app_dir, capture_output=True, text=True)
                    commit_hash = hash_res.stdout.strip()
                    if commit_hash:
                        import shutil
                        shutil.copy2(sync_log, os.path.join(app_dir, f"storage/logs/git-sync-{commit_hash}.log"))
                except Exception:
                    pass
                
                # Auto-restart monitor_server.py so new python code takes effect immediately
                pid = os.getpid()
                subprocess.Popen(
                    f"sleep 2 && kill -9 {pid} ; nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &",
                    shell=True, cwd=app_dir
                )

            threading.Thread(target=trigger_manual_deploy, daemon=True).start()
            resp = json.dumps({"status": "ok", "message": "Manual deployment triggered! Server will reboot in 2 seconds."}).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self._cors_headers()
            self.end_headers()
            self.wfile.write(resp)
            return
            
        if self.path == "/api/deploy/restart":
            def trigger_restart():
                import subprocess, time, os
                app_dir = "/data/data/com.termux/files/home/uni-activity"
                if not os.path.exists(app_dir):
                    app_dir = str(Path(__file__).parent.parent)
                
                # Restart php-fpm
                subprocess.run(["pkill", "-9", "-f", "php-fpm"])
                subprocess.Popen(["nohup", "php-fpm"], cwd=app_dir)
                
                # Auto-restart monitor_server.py
                pid = os.getpid()
                subprocess.Popen(
                    f"sleep 2 && kill -9 {pid} ; nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &",
                    shell=True, cwd=app_dir
                )
            
            threading.Thread(target=trigger_restart, daemon=True).start()
            resp = json.dumps({"status": "ok", "message": "Restart triggered! Server will reboot in 2 seconds."}).encode()
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
                    import subprocess, time, os
                    app_dir = "/data/data/com.termux/files/home/uni-activity"
                    if not os.path.exists(app_dir):
                        app_dir = str(Path(__file__).parent.parent)
                    sync_log = os.path.join(app_dir, "storage/logs/git-sync.log")
                    try:
                        os.makedirs(os.path.dirname(sync_log), exist_ok=True)
                        with open(sync_log, "a", encoding="utf-8") as f:
                            f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] Rollback executed to commit {commit_hash} via Monitor UI Events.\n")
                            f.flush()

                            def run_and_log(cmd):
                                import subprocess, time
                                f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] > {' '.join(cmd)}\n")
                                f.flush()
                                proc = subprocess.Popen(cmd, cwd=app_dir, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
                                for line in iter(proc.stdout.readline, ''):
                                    f.write(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] {line}")
                                    f.flush()
                                proc.stdout.close()
                                proc.wait()

                            run_and_log(["git", "reset", "--hard", commit_hash])
                            run_and_log(["php", "artisan", "config:clear"])
                            run_and_log(["php", "artisan", "route:clear"])
                            run_and_log(["npm", "run", "build"]) # Generate build logs
                            
                            f.write("Restarting php-fpm...\n")
                            subprocess.run(["pkill", "-9", "-f", "php-fpm"])
                            subprocess.Popen(["nohup", "php-fpm"], cwd=app_dir)
                            f.write("Rollback finished.\n")
                    except Exception:
                        pass
                        
                    # Try to copy to per-commit log file
                    try:
                        if commit_hash:
                            import shutil
                            shutil.copy2(sync_log, os.path.join(app_dir, f"storage/logs/git-sync-{commit_hash}.log"))
                    except Exception:
                        pass
                    
                    # Auto-restart monitor_server.py so rolled-back python code takes effect immediately
                    pid = os.getpid()
                    subprocess.Popen(
                        f"sleep 2 && kill -9 {pid} ; nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &",
                        shell=True, cwd=app_dir
                    )

                threading.Thread(target=trigger_rollback, daemon=True).start()
                resp = json.dumps({"status": "ok", "message": f"Rollback to commit {commit_hash} initiated! Server will reboot in 2 seconds."}).encode()
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
            with cfg._ext_lock:
                data = json.dumps(cfg._ext_job).encode()
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

        # ── /api/tunnel-urls — ให้ script ดึง URLs ทั้งสองได้ ─────────────
        if self.path == "/api/tunnel-urls":
            import re as _re, subprocess as _sp, urllib.request as _ur

            http_url = get_cf_url()
            ssh_url  = ""

            # อ่านจาก active_url.json ก่อน
            jp = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
            try:
                with open(jp, "r") as _f:
                    _d = json.load(_f)
                    http_url = _d.get("url", http_url) or http_url
                    ssh_url  = _d.get("ssh_url", "")
            except Exception:
                pass

            # fallback: metrics port
            if not ssh_url:
                try:
                    resp = _ur.urlopen("http://127.0.0.1:20242/metrics", timeout=2)
                    m = _re.search(r'userHostname="(https://[^"]+)"', resp.read().decode())
                    if m:
                        ssh_url = m.group(1)
                except Exception:
                    pass

            payload = json.dumps({
                "http_url"   : http_url or "",
                "ssh_url"    : ssh_url  or "",
                "server_lan" : "192.168.1.222",
                "ssh_port"   : 8022,
                "updated_at" : time.strftime("%Y-%m-%d %H:%M:%S"),
            }).encode("utf-8")

            self.send_response(200)
            self.send_header("Content-Type", "application/json; charset=utf-8")
            self.send_header("Access-Control-Allow-Origin", "*")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)
            return

        # ── /ssh-to-server.sh — serve script ให้ Termux เครื่องอื่น dl ──
        if self.path in ("/ssh-to-server.sh", "/ssh-to-server.sh?raw=1"):
            script_path = "/data/data/com.termux/files/home/ssh-to-server.sh"
            try:
                with open(script_path, "rb") as _f:
                    data = _f.read()
                self.send_response(200)
                self.send_header("Content-Type", "text/plain; charset=utf-8")
                self.send_header("Content-Disposition", "inline; filename=ssh-to-server.sh")
                self.send_header("Content-Length", str(len(data)))
                self.send_header("Access-Control-Allow-Origin", "*")
                self.end_headers()
                self.wfile.write(data)
            except Exception:
                self.send_response(404)
                self.end_headers()
                self.wfile.write(b"ssh-to-server.sh not found")
            return

        # Serve static React files
        path = self.path.split("?")[0]
        if path == "/" or path == "":
            path = "/index.html"
        
        file_path = cfg.STATIC_DIR / path.lstrip("/")
        
        if not file_path.exists() or not str(file_path).startswith(str(cfg.STATIC_DIR)):
            # SPA fallback
            file_path = cfg.STATIC_DIR / "index.html"
        
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
