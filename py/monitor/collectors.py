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
    app_dir = "/data/data/com.termux/files/home/uni-activity"
    if not os.path.exists(app_dir):
        from pathlib import Path
        app_dir = str(Path(__file__).parent.parent)

    # 1. Check deploy.log
    deploy_log_path = os.path.join(app_dir, "storage/logs/deploy.log")
    if os.path.exists(deploy_log_path) and os.path.getsize(deploy_log_path) > 0:
        try:
            with open(deploy_log_path, "r", encoding="utf-8", errors="replace") as f:
                lines = f.readlines()
                return "".join(lines[-250:])
        except Exception as e:
            return f"Error reading deploy log: {str(e)}"

    # 2. Check git-sync.log as fallback
    sync_log_path = os.path.join(app_dir, "storage/logs/git-sync.log")
    if os.path.exists(sync_log_path) and os.path.getsize(sync_log_path) > 0:
        try:
            with open(sync_log_path, "r", encoding="utf-8", errors="replace") as f:
                lines = f.readlines()
                return "".join(lines[-250:])
        except Exception:
            pass

    return "No deployment log found. Deploy via SCP, SFTP, or Git Sync to generate logs."

def get_log_files_info():
    import os, glob, time
    from pathlib import Path
    app_dir = "/data/data/com.termux/files/home/uni-activity"
    if not os.path.exists(app_dir):
        app_dir = str(Path(__file__).parent.parent)

    logs_dir = os.path.join(app_dir, "storage/logs")
    log_files = []
    total_bytes = 0

    if os.path.exists(logs_dir):
        for file_path in glob.glob(os.path.join(logs_dir, "*.log")):
            try:
                name = os.path.basename(file_path)
                stat = os.stat(file_path)
                size_bytes = stat.st_size
                total_bytes += size_bytes
                mtime_str = time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(stat.st_mtime))
                
                # Read snippet
                snippet = ""
                if size_bytes > 0:
                    try:
                        with open(file_path, "r", encoding="utf-8", errors="replace") as f:
                            snippet = "".join(f.readlines()[-200:])
                    except Exception:
                        pass
                
                log_files.append({
                    "name": name,
                    "size_bytes": size_bytes,
                    "size_kb": round(size_bytes / 1024, 1),
                    "size_mb": round(size_bytes / (1024 * 1024), 2),
                    "modified": mtime_str,
                    "content": snippet
                })
            except Exception:
                pass

    log_files.sort(key=lambda x: x["name"])

    return {
        "count": len(log_files),
        "total_size_mb": round(total_bytes / (1024 * 1024), 2),
        "files": log_files
    }

def get_channel_logs():
    import os, subprocess, time
    from pathlib import Path
    app_dir = "/data/data/com.termux/files/home/uni-activity"
    if not os.path.exists(app_dir):
        app_dir = str(Path(__file__).parent.parent)

    now_str = time.strftime('%Y-%m-%d %H:%M:%S')

    result = {
        "deploy": "",
        "git": "",
        "scp": "",
        "sftp": "",
        "ssh": ""
    }

    # 1. deploy.log
    deploy_path = os.path.join(app_dir, "storage/logs/deploy.log")
    if os.path.exists(deploy_path) and os.path.getsize(deploy_path) > 0:
        try:
            with open(deploy_path, "r", encoding="utf-8", errors="replace") as f:
                result["deploy"] = "".join(f.readlines()[-250:])
        except Exception:
            pass

    # 2. git-sync.log
    git_path = os.path.join(app_dir, "storage/logs/git-sync.log")
    if os.path.exists(git_path) and os.path.getsize(git_path) > 0:
        try:
            with open(git_path, "r", encoding="utf-8", errors="replace") as f:
                result["git"] = "".join(f.readlines()[-250:])
        except Exception:
            pass

    # 3. SSH Status & Live Log
    ssh_lines = []
    ssh_sessions = get_active_sessions()
    ssh_lines.append(f"[{now_str}] === SSH Session & Daemon Monitor (Port 8022) ===")
    ssh_lines.append(f"Active Connected Clients: {len(ssh_sessions)}")
    if ssh_sessions:
        for idx, sess in enumerate(ssh_sessions, 1):
            ssh_lines.append(f"   [{idx}] Client Remote Socket: {sess} -> Termux Daemon (Port 8022)")
    else:
        ssh_lines.append("   [IDLE] No remote SSH client sessions currently connected.")

    ssh_lines.append("")
    ssh_lines.append("=== Active Shell & SSH Daemon Processes ===")
    try:
        ps_res = subprocess.run(["ps", "-ef"], capture_output=True, text=True)
        found_procs = 0
        for line in ps_res.stdout.split('\n'):
            if any(k in line.lower() for k in ["sshd", "ssh-", "dropbear", "zsh", "bash"]) and "grep" not in line and line.strip():
                ssh_lines.append(f"   * {line.strip()}")
                found_procs += 1
        if found_procs == 0:
            ssh_lines.append("   No active shell processes found.")
    except Exception as e:
        ssh_lines.append(f"   Error reading processes: {str(e)}")

    # Check for shell activity or auth log
    shell_log_path = os.path.join(app_dir, "storage/logs/shell_activity.log")
    if os.path.exists(shell_log_path) and os.path.getsize(shell_log_path) > 0:
        try:
            with open(shell_log_path, "r", encoding="utf-8", errors="replace") as f:
                ssh_lines.append("")
                ssh_lines.append("=== Recent Shell Command Activity ===")
                ssh_lines.extend(f.readlines()[-30:])
        except Exception:
            pass

    ssh_lines.append("")
    ssh_lines.append("[Config] SSH Direct Connect Command:")
    ssh_lines.append("   ssh -p 8022 u0_a175@192.168.1.222")

    result["ssh"] = "\n".join(ssh_lines)

    # 4. SCP Status & Log
    scp_lines = []
    scp_active = get_scp_active()
    scp_lines.append(f"[{now_str}] === SCP Transfer Monitor (Port 8022) ===")
    scp_lines.append(f"Active SCP Sessions: {scp_active}")
    if scp_active > 0:
        try:
            ps_res = subprocess.run(["ps", "-ef"], capture_output=True, text=True)
            for line in ps_res.stdout.split('\n'):
                if "scp" in line.lower() and "grep" not in line:
                    scp_lines.append(f"[ACTIVE PROCESS] {line.strip()}")
        except Exception:
            pass
    else:
        scp_lines.append("[IDLE] No active SCP file copy processes currently running.")
        scp_lines.append("")
        scp_lines.append("[Command] Quick SCP Transfer Example:")
        scp_lines.append("   scp -P 8022 -r ./app/ u0_a175@192.168.1.222:/data/data/com.termux/files/home/uni-activity/app/")

    # Check recently modified files in app/ and routes/
    try:
        recent_files = subprocess.run(
            ["find", "app", "routes", "-type", "f", "-mmin", "-120"],
            cwd=app_dir, capture_output=True, text=True
        )
        files = [f for f in recent_files.stdout.strip().split('\n') if f]
        if files:
            scp_lines.append("")
            scp_lines.append(f"[Files] Updated in last 2 hours ({len(files)} files):")
            for f in files[:20]:
                scp_lines.append(f"   * {f}")
    except Exception:
        pass

    result["scp"] = "\n".join(scp_lines)

    # 5. SFTP Status & Log
    sftp_lines = []
    sftp_active = get_sftp_active()
    sftp_lines.append(f"[{now_str}] === SFTP Subsystem Monitor (Port 8022) ===")
    sftp_lines.append(f"Active SFTP Sessions: {sftp_active}")
    if sftp_active > 0:
        try:
            ps_res = subprocess.run(["ps", "-ef"], capture_output=True, text=True)
            for line in ps_res.stdout.split('\n'):
                if "sftp" in line.lower() and "grep" not in line:
                    sftp_lines.append(f"[ACTIVE PROCESS] {line.strip()}")
        except Exception:
            pass
    else:
        sftp_lines.append("[IDLE] No active SFTP clients connected.")
        sftp_lines.append("")
        sftp_lines.append("[Config] SFTP Connection Settings:")
        sftp_lines.append("   Host: 192.168.1.222 | Port: 8022 | User: u0_a175")
        sftp_lines.append("   Root Path: /data/data/com.termux/files/home/uni-activity")

    result["sftp"] = "\n".join(sftp_lines)

    return result

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

def get_scp_active():
    import subprocess
    try:
        res = subprocess.run(["pgrep", "-f", "scp"], capture_output=True, text=True)
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

def _env_var(name, default=""):
    """Read a variable from the Laravel .env file."""
    val = default
    try:
        env_path = os.path.expanduser("~/uni-activity/.env")
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                if line.startswith(name + "="):
                    val = line.split("=", 1)[1].strip().strip('"').strip("'")
                    break
    except Exception:
        pass
    return val


def _valkey_password():
    return _env_var("REDIS_PASSWORD")


def _valkey_host():
    return _env_var("REDIS_HOST", "127.0.0.1")


def _valkey_cli(port, *args):
    """Run valkey-cli against the configured Valkey host (local or remote)."""
    cmd = ["valkey-cli", "-h", _valkey_host(), "-p", str(port),
           "-a", _valkey_password(), "--no-auth-warning"] + list(args)
    try:
        return subprocess.run(cmd, capture_output=True, text=True, timeout=3)
    except Exception:
        return None


def _tcp_open(host, port):
    try:
        s = socket.create_connection((host, port), timeout=1)
        s.close()
        return True
    except Exception:
        return False


def get_services():
    global _services_cache, _services_cache_time
    import subprocess, time as _time
    # Cache 15 วินาที — ไม่ต้อง pgrep ทุกรอบ
    if _services_cache and (_time.time() - _services_cache_time) < 15:
        return _services_cache

    def pgrep(pattern):
        try:
            res = subprocess.run(["pgrep", "-f", pattern], capture_output=True, text=True)
            return bool(res.stdout.strip())
        except Exception:
            return False

    def count_workers():
        try:
            res = subprocess.run(["pgrep", "-f", "artisan serve"], capture_output=True, text=True)
            return len([x for x in res.stdout.split() if x.strip()])
        except Exception:
            return 0

    vk_host = _valkey_host()
    listening = get_listening_ports()

    candidates = [
        ("Nginx (Edge Proxy)",          lambda: pgrep("nginx"),                     8080),
        ("Web Workers (artisan serve)", lambda: count_workers() > 0,                None),
        ("Laravel Reverb (WebSocket)",  lambda: pgrep("reverb:start"),              8082),
        ("Datastore (Valkey)",          lambda: _tcp_open(vk_host, 6379),           None),
        ("Queue Store (Valkey)",        lambda: _tcp_open(vk_host, 6380),           None),
        ("PostgreSQL Database",         lambda: pgrep("postgres"),                  5432),
        ("Queue Worker",                lambda: pgrep("artisan queue:work"),        None),
        ("AI Biometrics Face Service",  lambda: pgrep("venv/bin/python server.py"), None),
        ("Cloudflared Tunnel",          lambda: pgrep("cloudflared"),               None),
        ("SSH / SFTP Server",           lambda: pgrep("sshd"),                      8022),
    ]

    status = {}
    for name, check, default_port in candidates:
        try:
            running = bool(check())
        except Exception:
            running = False

        if not running:
            status[name] = "Stopped"
        elif name == "Web Workers (artisan serve)":
            n = count_workers()
            status[name] = f"Running ({n} workers)" if n else "Running"
        elif name == "Datastore (Valkey)":
            status[name] = ("Running (Port 6379)" if vk_host in ("127.0.0.1", "localhost")
                            else f"Running (Port 6379 @ {vk_host})")
        elif name == "Queue Store (Valkey)":
            status[name] = ("Running (Port 6380)" if vk_host in ("127.0.0.1", "localhost")
                            else f"Running (Port 6380 @ {vk_host})")
        elif default_port and default_port in listening:
            status[name] = f"Running (Port {default_port})"
        else:
            status[name] = "Running"

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
        res = subprocess.run(["valkey-cli", "-h", _valkey_host(), "-p", "6379",
                              "-a", _valkey_password(), "--no-auth-warning",
                              "info", "memory"], capture_output=True, text=True, timeout=3)
        for line in res.stdout.split('\n'):
            if "used_memory_human:" in line:
                stats["used_memory"] = line.split(":")[1].strip()
        res2 = subprocess.run(["valkey-cli", "-h", _valkey_host(), "-p", "6379",
                               "-a", _valkey_password(), "--no-auth-warning",
                               "info", "clients"], capture_output=True, text=True, timeout=3)
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
        res1 = subprocess.run(["valkey-cli", "-h", _valkey_host(), "-p", "6380",
                               "-a", _valkey_password(), "--no-auth-warning",
                               "llen", "queues:default"], capture_output=True, text=True, timeout=3)
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

# ------- AI Cluster Health -------
import socket as _socket

_ai_node_cache = {}

def get_ai_cluster_health():
    """Check health of all configured AI service nodes."""
    nodes = _get_ai_nodes()
    if not nodes:
        return {"nodes": [], "healthy_count": 0, "total_count": 0, "cluster_status": "no_nodes_configured"}

    results = []
    for url in nodes:
        results.append(_check_ai_node_health(url))

    healthy = sum(1 for r in results if r["available"])

    return {
        "nodes": results,
        "healthy_count": healthy,
        "total_count": len(results),
        "cluster_status": "healthy" if healthy == len(results) else ("degraded" if healthy > 0 else "critical"),
        "checked_at": time.time(),
    }

def _get_ai_nodes():
    """Read AI_SERVERS (or AI_SERVER_URL) from .env."""
    nodes = []
    try:
        with open(cfg.ENV_PATH) as f:
            for line in f:
                line = line.strip()
                if line.startswith("AI_SERVERS="):
                    val = line.split("=", 1)[1].strip().strip('"\'')
                    nodes = [u.strip() for u in val.split(",") if u.strip()]
                elif line.startswith("AI_SERVER_URL=") and not nodes:
                    val = line.split("=", 1)[1].strip().strip('"\'')
                    if val:
                        nodes = [val]
    except Exception:
        pass
    return nodes

def _parse_node_url(url):
    from urllib.parse import urlparse
    try:
        parsed = urlparse(url if "://" in url else f"http://{url}")
        return {"host": parsed.hostname or "127.0.0.1", "port": parsed.port or 8001}
    except Exception:
        return {"host": "127.0.0.1", "port": 8001}

def _check_ai_node_health(url, timeout=8):
    """TCP + HTTP health check for one AI node, with 10s cache.
    Timeout is generous: /health can stall while the node is busy
    running a face-verification inference."""
    cached = _ai_node_cache.get(url)
    if cached and (time.time() - cached["t"]) < 10:
        return cached["r"]

    node = _parse_node_url(url)
    t0 = time.time()
    try:
        sock = _socket.socket(_socket.AF_INET, _socket.SOCK_STREAM)
        sock.settimeout(timeout)
        if sock.connect_ex((node["host"], node["port"])) != 0:
            raise ConnectionRefusedError("TCP port closed")
        sock.close()
    except Exception as e:
        r = {"url": url, "host": node["host"], "port": node["port"], "available": False,
             "status": "down", "latency_ms": int((time.time() - t0) * 1000), "error": str(e)}
        _ai_node_cache[url] = {"t": time.time(), "r": r}
        return r

    try:
        import urllib.request as _req
        resp = _req.urlopen(_req.Request(f"{url}/health", headers={"User-Agent": "Monitor/1.0"}), timeout=timeout)
        data = json.loads(resp.read().decode())
        latency = int((time.time() - t0) * 1000)
        r = {"url": url, "host": node["host"], "port": node["port"], "available": True,
             "status": data.get("status", "unknown"), "latency_ms": latency,
             "models": data.get("models", {}), "version": data.get("version", "")}
    except Exception as e:
        r = {"url": url, "host": node["host"], "port": node["port"], "available": False,
             "status": "unhealthy", "latency_ms": int((time.time() - t0) * 1000), "error": str(e)}

    _ai_node_cache[url] = {"t": time.time(), "r": r}
    return r

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


# ------- Proxy Monitoring -------

def _run_cmd(cmd, timeout=5):
    """Run a shell command and return stdout, or empty string on error."""
    try:
        r = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=timeout)
        return r.stdout.strip()
    except:
        return ""


def get_proxy_status():
    """Collect status of all proxy services: Squid HTTP, SOCKS5 (Python), Nginx LB."""
    now = time.time()
    # Cache for 10 seconds
    if hasattr(cfg, '_proxy_cache') and now - cfg._proxy_cache.get('t', 0) < 10:
        return cfg._proxy_cache.get('data', {})

    result = {
        'squid': {'status': 'Stopped', 'port': 3128, 'connections': 0, 'cache_hits': 0, 'cache_misses': 0},
        'socks5': {'status': 'Stopped', 'port': 1080, 'connections': 0},
        'nginx_lb': {'status': 'Stopped', 'port': 8088, 'connections': 0},
        'cloudflared': {'status': 'Stopped', 'port': 8080},
        'public_ip': cfg.CACHED_PUBLIC_IP or 'N/A',
    }

    # Squid HTTP Proxy
    squid_pid = _run_cmd("pgrep -f 'squid -f' | head -1")
    if squid_pid:
        result['squid']['status'] = 'Running'
        # Count active connections
        conns = _run_cmd("netstat -tnp 2>/dev/null | grep ':3128' | grep ESTABLISHED | wc -l")
        result['squid']['connections'] = int(conns) if conns.isdigit() else 0
        # Parse cache stats from squid cache.log or manager
        try:
            cache_log = os.path.expanduser('~/uni-activity/storage/logs/squid-cache.log')
            if os.path.exists(cache_log):
                with open(cache_log, 'r') as f:
                    lines = f.readlines()[-50:]
                for line in lines:
                    if 'TCP_HIT' in line:
                        result['squid']['cache_hits'] += 1
                    elif 'TCP_MISS' in line:
                        result['squid']['cache_misses'] += 1
        except:
            pass

    # SOCKS5 Proxy (Python)
    socks5_pid = _run_cmd("pgrep -f 'socks5_proxy.py' | head -1")
    if socks5_pid:
        result['socks5']['status'] = 'Running'
        conns = _run_cmd("netstat -tnp 2>/dev/null | grep ':1080' | grep ESTABLISHED | wc -l")
        result['socks5']['connections'] = int(conns) if conns.isdigit() else 0

    # Nginx Load Balancer
    nginx_pid = _run_cmd("pgrep -f 'nginx' | head -1")
    if nginx_pid:
        result['nginx_lb']['status'] = 'Running'
        conns = _run_cmd("netstat -tnp 2>/dev/null | grep ':8088' | grep ESTABLISHED | wc -l")
        result['nginx_lb']['connections'] = int(conns) if conns.isdigit() else 0

    # Cloudflared
    cf_pid = _run_cmd("pgrep -f 'cloudflared' | head -1")
    if cf_pid:
        result['cloudflared']['status'] = 'Running'

    # Nginx upstream health (down markers)
    try:
        nginx_conf = '/data/data/com.termux/files/usr/etc/nginx/nginx.conf'
        if os.path.exists(nginx_conf):
            with open(nginx_conf, 'r') as f:
                content = f.read()
            down_count = content.count(' down')
            result['nginx_lb']['down_markers'] = down_count
    except:
        result['nginx_lb']['down_markers'] = 0

    # Worker health (all 7 workers)
    workers = []
    for port in [8000, 8002, 8003]:
        wstatus = _run_cmd(f"curl -s -o /dev/null -w '%{{http_code}}' http://127.0.0.1:{port}/health --connect-timeout 2 2>/dev/null")
        workers.append({'host': '127.0.0.1', 'port': port, 'phone': 'P1', 'status': wstatus})
    for port in [8000, 8002, 8003, 8004]:
        wstatus = _run_cmd(f"curl -s -o /dev/null -w '%{{http_code}}' http://192.168.1.140:{port}/health --connect-timeout 2 2>/dev/null")
        workers.append({'host': '192.168.1.140', 'port': port, 'phone': 'P2', 'status': wstatus})
    result['workers'] = workers

    # Squid allowed sites count
    try:
        squid_conf = '/data/data/com.termux/files/usr/etc/squid/squid.conf'
        if os.path.exists(squid_conf):
            with open(squid_conf, 'r') as f:
                lines = f.readlines()
            allowed = sum(1 for l in lines if 'allowed_sites' in l and 'dstdomain' in l)
            result['squid']['allowed_domains'] = allowed
    except:
        result['squid']['allowed_domains'] = 0

    # Egress security: blocked ports
    result['nginx_lb']['blocked_ports'] = [7, 9, 19, 22, 23, 25, 110, 111, 135, 139, 445, 512, 513, 514, 515]

    # ═══ Traffic & Throughput Metrics ═══
    traffic = {
        'bandwidth_rx': 0.0,
        'bandwidth_tx': 0.0,
        'rps': 0,
        'total_requests': 0,
        'total_bytes': 0,
        'total_bytes_human': '0 KB',
        'avg_response_time': 0.0,
        'cache_hit_ratio': 0.0,
        'top_domains': [],
        'recent_errors': 0,
    }

    # Bandwidth from network interface
    try:
        net_dev = '/proc/net/dev'
        if os.path.exists(net_dev):
            with open(net_dev, 'r') as f:
                lines = f.readlines()[2:]  # skip headers
            total_rx = 0
            total_tx = 0
            for line in lines:
                parts = line.split()
                if len(parts) >= 10:
                    iface = parts[0].rstrip(':')
                    if iface in ('wlan0', 'eth0', 'rmnet_data0'):
                        total_rx += int(parts[1])
                        total_tx += int(parts[9])
            traffic['total_bytes'] = total_rx + total_tx
            traffic['total_bytes_human'] = _human_bytes(total_rx + total_tx)
    except:
        pass

    # Parse Squid access log for RPS and stats
    try:
        access_log = os.path.expanduser('~/uni-activity/storage/logs/squid-access.log')
        if os.path.exists(access_log):
            # Get last 1000 lines for recent stats
            rps_count = 0
            error_count = 0
            total_size = 0
            domain_counts = {}
            response_times = []
            now_ts = time.time()

            with open(access_log, 'rb') as f:
                # Read last 100KB of file
                f.seek(0, 2)
                file_size = f.tell()
                read_size = min(100000, file_size)
                f.seek(max(0, file_size - read_size))
                data = f.read().decode('utf-8', errors='ignore')

            lines = data.strip().split('\n')[-200:]  # last 200 lines
            for line in lines:
                try:
                    parts = line.split()
                    if len(parts) < 4:
                        continue

                    # Parse timestamp (Unix epoch)
                    ts = float(parts[0])
                    if now_ts - ts < 60:  # last 60 seconds
                        rps_count += 1

                    # Parse status code
                    status = parts[3]
                    if 'DENIED' in status or 'ERR' in status:
                        error_count += 1

                    # Parse response size
                    if len(parts) > 5:
                        try:
                            size = int(parts[5])
                            total_size += size
                        except:
                            pass

                    # Parse domain
                    if len(parts) > 6:
                        domain = parts[6].split(':')[0]
                        domain_counts[domain] = domain_counts.get(domain, 0) + 1

                except:
                    continue

            traffic['rps'] = round(rps_count / 60.0, 1)  # requests per second
            traffic['recent_errors'] = error_count
            traffic['total_requests'] = len(lines)

            # Top domains
            sorted_domains = sorted(domain_counts.items(), key=lambda x: x[1], reverse=True)
            traffic['top_domains'] = [{'domain': d, 'count': c} for d, c in sorted_domains[:10]]

            # Cache hit ratio
            hits = sum(1 for l in lines if 'HIT' in l)
            misses = sum(1 for l in lines if 'MISS' in l)
            total_cache = hits + misses
            traffic['cache_hit_ratio'] = round(hits / total_cache * 100, 1) if total_cache > 0 else 0
    except:
        pass

    result['traffic'] = traffic

    # ═══ Connection & Concurrency Metrics ═══
    connections = {
        'squid_active': 0,
        'socks5_active': 0,
        'top_clients': [],
        'total_squid_conns': 0,
        'total_socks5_conns': 0,
    }

    # Active HTTP connections (Squid :3128)
    try:
        conns = _run_cmd("netstat -tnp 2>/dev/null | grep ':3128' | grep ESTABLISHED | wc -l")
        connections['squid_active'] = int(conns) if conns.isdigit() else 0
        # Total including TIME_WAIT
        conns_total = _run_cmd("netstat -tnp 2>/dev/null | grep ':3128' | wc -l")
        connections['total_squid_conns'] = int(conns_total) if conns_total.isdigit() else 0
    except:
        pass

    # Active SOCKS5 connections (port 1080)
    try:
        conns = _run_cmd("netstat -tnp 2>/dev/null | grep ':1080' | grep ESTABLISHED | wc -l")
        connections['socks5_active'] = int(conns) if conns.isdigit() else 0
        conns_total = _run_cmd("netstat -tnp 2>/dev/null | grep ':1080' | wc -l")
        connections['total_socks5_conns'] = int(conns_total) if conns_total.isdigit() else 0
    except:
        pass

    # Top client IPs (from Squid access log)
    try:
        access_log = os.path.expanduser('~/uni-activity/storage/logs/squid-access.log')
        if os.path.exists(access_log):
            client_ips = {}
            client_bytes = {}
            with open(access_log, 'rb') as f:
                f.seek(0, 2)
                file_size = f.tell()
                read_size = min(200000, file_size)
                f.seek(max(0, file_size - read_size))
                data = f.read().decode('utf-8', errors='ignore')

            lines = data.strip().split('\n')[-500:]
            for line in lines:
                try:
                    parts = line.split()
                    if len(parts) < 5:
                        continue
                    # Format: timestamp elapsed client code size method url
                    client = parts[2]
                    if client.startswith('192.168.') or client.startswith('127.'):
                        client_ips[client] = client_ips.get(client, 0) + 1
                        try:
                            size = int(parts[4])
                            client_bytes[client] = client_bytes.get(client, 0) + size
                        except:
                            pass
                except:
                    continue

            # Sort by request count
            sorted_clients = sorted(client_ips.items(), key=lambda x: x[1], reverse=True)
            connections['top_clients'] = [
                {
                    'ip': ip,
                    'requests': count,
                    'bytes': client_bytes.get(ip, 0),
                    'bytes_human': _human_bytes(client_bytes.get(ip, 0)),
                }
                for ip, count in sorted_clients[:10]
            ]
    except:
        pass

    result['connections'] = connections

    cfg._proxy_cache = {'t': now, 'data': result}
    return result


def _human_bytes(size):
    """Convert bytes to human readable format."""
    for unit in ['B', 'KB', 'MB', 'GB', 'TB']:
        if abs(size) < 1024.0:
            return f"{size:.1f} {unit}"
        size /= 1024.0
    return f"{size:.1f} PB"
