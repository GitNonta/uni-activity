"""
monitor/collectors.py — All get_*() data collection functions.
"""
import os, time, re, subprocess, socket, json
import monitor.config as cfg

# ------- Data Collection -------

def get_cf_url():
    # 1. Check docs/active_url.json first
    json_path = os.path.join(os.path.dirname(cfg.ENV_PATH), "docs", "active_url.json")
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
    if os.path.exists(cfg.ENV_PATH):
        try:
            with open(cfg.ENV_PATH) as f:
                for line in f:
                    if line.startswith("APP_URL="):
                        val = line.split("=", 1)[1].strip()
                        if (val.startswith('"') and val.endswith('"')) or (val.startswith("'") and val.endswith("'")):
                            val = val[1:-1]
                        return val
        except Exception:
            pass
    return "Not Found"



def get_line_status():
    # line_status_cache lives in cfg (no global needed)
    now = time.time()
    if now - cfg.line_status_cache.get("last_check", 0) < 60:
        return cfg.line_status_cache

    token = None
    try:
        if os.path.exists(cfg.ENV_PATH):
            with open(cfg.ENV_PATH, "r") as f:
                for line in f:
                    if line.startswith("LINE_CHANNEL_ACCESS_TOKEN="):
                        token = line.split("=", 1)[1].strip()
                        if (token.startswith('"') and token.endswith('"')) or (token.startswith("'") and token.endswith("'")):
                            token = token[1:-1]
                        break
    except Exception as e:
        cfg.line_status_cache = {"status": "Error", "error": f"Failed to read .env: {str(e)}", "last_check": now}
        return cfg.line_status_cache

    if not token:
        cfg.line_status_cache = {"status": "Not Configured", "error": "LINE_CHANNEL_ACCESS_TOKEN missing from .env", "last_check": now}
        return cfg.line_status_cache

    import urllib.request, json
    try:
        req = urllib.request.Request("https://api.line.me/v2/bot/info")
        req.add_header("Authorization", f"Bearer {token}")
        proxy_support = urllib.request.ProxyHandler({})
        opener = urllib.request.build_opener(proxy_support)
        with opener.open(req, timeout=3) as response:
            res_data = json.loads(response.read().decode("utf-8"))
            cfg.line_status_cache = {
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
        cfg.line_status_cache = {"status": "Offline", "error": err_msg, "last_check": now}

    return cfg.line_status_cache


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
                    if cfg.last_rx > 0 and cfg.last_tx > 0:
                        diff = now - cfg.last_net_time
                        if diff > 0:
                            rx_rate = ((rx - cfg.last_rx) / 1024) / diff
                            tx_rate = ((tx - cfg.last_tx) / 1024) / diff
                            
                    cfg.last_rx = rx
                    cfg.last_tx = tx
                    cfg.last_net_time = now
                    
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
        lines = os.popen(f"tail -n 15 {cfg.NGINX_LOG}").read().strip().split("\n")
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

def get_github_sync_logs_dict():
    from pathlib import Path
    import os, glob
    app_dir = "/data/data/com.termux/files/home/uni-activity"
    if not os.path.exists(app_dir):
        app_dir = str(Path(__file__).parent.parent)
        
    logs = {}
    
    # Read global sync log
    sync_log_path = os.path.join(app_dir, "storage/logs/git-sync.log")
    if os.path.exists(sync_log_path):
        try:
            with open(sync_log_path, "r", encoding="utf-8", errors="replace") as f:
                lines = f.readlines()
                logs["latest"] = "".join(lines[-200:])
        except Exception:
            pass

    # Read per-commit logs
    log_pattern = os.path.join(app_dir, "storage/logs/git-sync-*.log")
    for filepath in glob.glob(log_pattern):
        filename = os.path.basename(filepath)
        commit_hash = filename.replace("git-sync-", "").replace(".log", "")
        try:
            with open(filepath, "r", encoding="utf-8", errors="replace") as f:
                lines = f.readlines()
                logs[commit_hash] = "".join(lines[-200:])
        except Exception:
            pass
            
    return logs

def get_github_events():
    """Fetch real-time commit & local deployment events."""
    events = []
    try:
        import subprocess, datetime, os
        from pathlib import Path
        
        app_dir = "/data/data/com.termux/files/home/uni-activity"
        if not os.path.exists(app_dir):
            app_dir = str(Path(__file__).parent.parent)

        # Get current active HEAD
        try:
            head_res = subprocess.run(["git", "rev-parse", "--short", "HEAD"], cwd=app_dir, capture_output=True, text=True)
            current_head = head_res.stdout.strip()
        except Exception:
            current_head = ""

        # Get local git log
        log_res = subprocess.run(
            ["git", "log", "-n", "20", "--pretty=format:%h|%ad|%s", "--date=iso"],
            cwd=app_dir, capture_output=True, text=True
        )
        
        if log_res.returncode == 0:
            lines = log_res.stdout.strip().split("\n")
            for line in lines:
                if not line: continue
                parts = line.split("|", 2)
                if len(parts) < 3: continue
                sha = parts[0]
                date_iso = parts[1]
                msg = parts[2]
                
                dt_str = date_iso
                try:
                    dt_obj = datetime.datetime.fromisoformat(date_iso.replace("Z", "+00:00"))
                    dt_str = dt_obj.strftime("%B %e, %Y at %I:%M %p")
                except Exception:
                    pass

                # If this is the current active commit
                if sha == current_head:
                    events.append({
                        "id": f"local-{sha}-status",
                        "type": "success",
                        "hash": sha,
                        "message": msg,
                        "detail": "Live - Deployed successfully on local server",
                        "timestamp": dt_str
                    })
                
                events.append({
                    "id": f"local-{sha}-start",
                    "type": "started",
                    "hash": sha,
                    "message": msg,
                    "detail": "Commit deployed" if sha == current_head else "Historical commit",
                    "timestamp": dt_str
                })
                
    except Exception as e:
        events.append({
            "id": "error",
            "type": "failed",
            "hash": "error",
            "message": "Error fetching local git logs",
            "detail": str(e),
            "timestamp": ""
        })

    return events

def get_ai_logs():
    if len(cfg.remote_ai_logs) > 0:
        return "".join(cfg.remote_ai_logs)
        
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

_services_cache: dict = {}
_services_cache_time: float = 0.0
_ports_cache: list = []
_ports_cache_time: float = 0.0

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
    global _services_cache, _services_cache_time
    import subprocess, time as _time
    # Cache 15 วินาที — ไม่ต้อง pgrep ทุกรอบ
    if _services_cache and (_time.time() - _services_cache_time) < 15:
        return _services_cache

    # Check active web worker
    is_octane = bool(subprocess.run(["pgrep", "-f", "octane:start"], capture_output=True, text=True).stdout.strip())
    is_fpm = bool(subprocess.run(["pgrep", "-f", "php-fpm"], capture_output=True, text=True).stdout.strip())
    is_dragonfly = bool(subprocess.run(["pgrep", "-f", "dragonfly|redis-server"], capture_output=True, text=True).stdout.strip())

    services = {
        "Nginx (Edge Proxy)": ("nginx", 8080),
        "Laravel Application Server": ("octane:start|php-fpm", 8080 if is_fpm else 8000),
        "Swoole / OpenSwoole": ("swoole|dragonfly|redis-server", 6379),
        "Laravel Reverb (WebSocket)": ("reverb:start", 8082),
        "Datastore (Dragonfly / Redis)": ("dragonfly|redis-server", 6379),
        "PostgreSQL 16 Database": ("postgres", 5432),
        "Redis Queue Worker": ("artisan queue:work", None),
        "AI Biometrics Face Service": ("server.py", 8000),
        "Cloudflared Tunnel": ("cloudflared", None),
        "SSH / SFTP Server": ("sshd", 8022)
    }

    listening = get_listening_ports()

    status = {}
    for name, (proc_pattern, default_port) in services.items():
        try:
            patterns = proc_pattern.split('|')
            is_running = False
            for pat in patterns:
                res = subprocess.run(["pgrep", "-f", pat], capture_output=True, text=True)
                if bool(res.stdout.strip()):
                    is_running = True
                    break

            if is_running:
                if name == "Laravel Application Server":
                    engine_desc = "Octane (In-Memory)" if is_octane else "Active Engine"
                    status[name] = f"Running ({engine_desc})"
                elif name == "Swoole / OpenSwoole":
                    status[name] = "Running (In-Memory State & Tables)"
                elif default_port and default_port in listening:
                    status[name] = f"Running (Port {default_port})"
                else:
                    status[name] = "Running"
            else:
                status[name] = "Stopped"
        except Exception:
            status[name] = "Unknown"

    _services_cache = status
    _services_cache_time = _time.time()
    return status


# --- Advanced Metrics Helpers ---

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
    dt = now - cfg.prev_net_bytes["time"]
    rx_speed = 0
    tx_speed = 0
    if cfg.prev_net_bytes["time"] > 0 and dt > 0:
        rx_speed = max(0.0, (rx - cfg.prev_net_bytes["rx"]) / dt)
        tx_speed = max(0.0, (tx - cfg.prev_net_bytes["tx"]) / dt)
        
    cfg.prev_net_bytes = {"rx": rx, "tx": tx, "time": now}
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
    stats = {
        "latency_ms"  : 0,
        "http_url"    : "",   # tunnel :8080 → Laravel/HTTP
        "ssh_url"     : "",   # tunnel :80   → SSH
        "http_online" : False,
        "ssh_online"  : False,
    }

    def _fetch_metrics(port: int) -> str:
        """ดึง metrics จาก cloudflared local port"""
        for method in [
            lambda: urllib.request.build_opener(
                urllib.request.ProxyHandler({})
            ).open(f"http://127.0.0.1:{port}/metrics", timeout=2).read().decode("utf-8"),
            lambda: subprocess.run(
                ["curl", "-s", "-m", "2", f"http://127.0.0.1:{port}/metrics"],
                capture_output=True, text=True, timeout=3
            ).stdout,
        ]:
            try:
                content = method()
                if content and len(content) > 10:
                    return content
            except Exception:
                pass
        return ""

    # port 20241 → cloudflared ตัวแรก (--url :8080)
    content_1 = _fetch_metrics(20241)
    if content_1:
        m_rtt = re.search(r'quic_client_smoothed_rtt\{[^}]*\}\s+([0-9.]+)', content_1)
        if m_rtt:
            stats["latency_ms"] = round(float(m_rtt.group(1)), 1)
        m_url = re.search(r'userHostname="(https?://[^"]+)"', content_1)
        if m_url:
            stats["http_url"] = m_url.group(1)

    # port 20242 → cloudflared ตัวที่สอง (--url :80 / SSH tunnel)
    content_2 = _fetch_metrics(20242)
    if content_2:
        m_url2 = re.search(r'userHostname="(https?://[^"]+)"', content_2)
        if m_url2:
            stats["ssh_url"] = m_url2.group(1)

    # fallback: สแกน log หาทุก trycloudflare URLs
    if not stats["http_url"] or not stats["ssh_url"]:
        try:
            log_path = "/data/data/com.termux/files/home/cloudflared.log"
            with open(log_path, "r") as f:
                log_content = f.read()
            all_urls = list(dict.fromkeys(
                re.findall(r'https://[a-zA-Z0-9-]+\.trycloudflare\.com', log_content)
            ))
            # URL แรก = HTTP, URL ที่สอง = SSH (ถ้ามี)
            if all_urls and not stats["http_url"]:
                stats["http_url"] = all_urls[0]
            if len(all_urls) > 1 and not stats["ssh_url"]:
                stats["ssh_url"] = all_urls[1]
        except Exception:
            pass

    # เช็คสถานะ online ของแต่ละ tunnel
    import ssl as _ssl, http.client as _http
    for key, url in [("http_online", stats["http_url"]), ("ssh_online", stats["ssh_url"])]:
        if not url:
            continue
        try:
            parsed = urllib.parse.urlparse(url) if hasattr(urllib, 'parse') else \
                     __import__('urllib.parse', fromlist=['parse']).parse.urlparse(url)
            ctx  = _ssl._create_unverified_context()
            conn = _http.HTTPSConnection(parsed.netloc, timeout=4, context=ctx)
            conn.request("HEAD", "/", headers={"Host": parsed.netloc})
            r = conn.getresponse()
            stats[key] = r.status < 530
        except Exception:
            stats[key] = False

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



def get_server_info():
    if cfg.server_info_cache is not None:
        return cfg.server_info_cache
    
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
        
    cfg.server_info_cache = info
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
