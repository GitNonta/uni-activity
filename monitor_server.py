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
STATIC_DIR = Path(__file__).parent / "monitor-ui" / "dist"
PORT = 9999
UDP_PORT = 9998

inspector_logs = collections.deque(maxlen=100)
url_status = {"online": False, "ping_ms": 0}
alerts_history = collections.deque(maxlen=100)
active_alert_ids = set()

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
    if os.path.exists(ENV_PATH):
        with open(ENV_PATH) as f:
            for line in f:
                if line.startswith("APP_URL="):
                    return line.split("=", 1)[1].strip()
    return "Not Found"

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

def get_services():
    import subprocess
    services = {
        "Nginx": "nginx",
        "PHP-FPM": "php-fpm",
        "PostgreSQL": "postgres",
        "Redis": "redis-server",
        "Cloudflared": "cloudflared",
        "Reverb": "reverb:start",
        "Queue Worker": "artisan queue:work",
        "SSH": "sshd",
        "SFTP": "sshd"
    }
    status = {}
    for name, proc in services.items():
        try:
            res = subprocess.run(["pgrep", "-f", proc], capture_output=True, text=True)
            status[name] = "Running" if res.stdout.strip() else "Stopped"
        except Exception:
            status[name] = "Unknown"
    return status

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

# ------- WebSocket Handshake Helper -------

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

    def do_POST(self):
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
            
            import threading
            threading.Thread(target=restart).start()
            return

        self.send_response(404)
        self.end_headers()
        self.wfile.write(b"Not Found")

    def do_GET(self):
        # WebSocket upgrade
        if self.headers.get("Upgrade", "").lower() == "websocket":
            self._handle_websocket()
            return

        if self.path == "/api/stats":
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

if __name__ == "__main__":
    t_udp = threading.Thread(target=udp_receiver_thread, daemon=True)
    t_udp.start()
    
    t_ping = threading.Thread(target=ping_url_thread, daemon=True)
    t_ping.start()
    
    server = ThreadingHTTPServer(("", PORT), MonitorHandler)
    server.allow_reuse_address = True
    print(f"[Monitor] Serving at http://0.0.0.0:{PORT}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("Shutting down.")
