"""
╔══════════════════════════════════════════════════════════════╗
║          Uni-Activity Server Auto-Heal Watchdog              ║
║  ตรวจสอบ services ทุก 30 วิ — restart อัตโนมัติเมื่อล่ม  ║
╚══════════════════════════════════════════════════════════════╝

Services ที่ดูแล (ตามลำดับ dependency):
  1. Redis        — ต้องขึ้นก่อน Queue/Reverb
  2. Nginx        — web server
  3. Queue Worker — ต้องการ Redis
  4. Reverb       — WebSocket server
  5. Monitor      — dashboard port 9999
  6. Cloudflared  — Cloudflare Tunnel
"""

import paramiko
import time
import logging
import sys
import os
from datetime import datetime

# --- Config ---
SSH_HOST     = "192.168.1.222"
SSH_PORT     = 8022
SSH_USER     = "u0_a175"
SSH_PASS     = "2345678A"

APP_DIR      = "/data/data/com.termux/files/home/uni-activity"
TERMUX_HOME  = "/data/data/com.termux/files/home"

CHECK_INTERVAL   = 30   # sec between checks
SSH_TIMEOUT      = 15   # sec SSH timeout
RESTART_WAIT     = 5    # sec wait after restart
MAX_RESTART_MINS = 10   # min cooldown before re-restarting same service

# --- Logging ---
os.makedirs("watchdog_logs", exist_ok=True)
log_file = f"watchdog_logs/watchdog_{datetime.now().strftime('%Y%m%d')}.log"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[
        logging.FileHandler(log_file, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ]
)
log = logging.getLogger("watchdog")

# --- Cooldown State ---
last_restart: dict = {}

def can_restart(name: str) -> bool:
    t = last_restart.get(name, 0)
    return (time.time() - t) > (MAX_RESTART_MINS * 60)

def mark_restarted(name: str):
    last_restart[name] = time.time()

# --- SSH Helper ---
def get_ssh_client():
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(
            hostname=SSH_HOST, port=SSH_PORT,
            username=SSH_USER, password=SSH_PASS,
            timeout=SSH_TIMEOUT, banner_timeout=SSH_TIMEOUT,
            auth_timeout=SSH_TIMEOUT
        )
        return client
    except Exception as e:
        log.error(f"SSH connect failed: {e}")
        return None

def run(client, cmd, timeout=15):
    try:
        _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
        out = stdout.read().decode(errors="replace").strip()
        err = stderr.read().decode(errors="replace").strip()
        return out, err
    except Exception as e:
        return "", str(e)

# --- Check Functions (port/ping-based for reliability) ---

def _port_listening(c, port: int) -> bool:
    """ตรวจจาก port จริง — ไม่ถูก pgrep name หลอก"""
    out, _ = run(c, f"netstat -tlnp 2>/dev/null | grep ':{port} ' || ss -tlnp 2>/dev/null | grep ':{port} '")
    return bool(out.strip())

def is_redis_running(c):
    # redis-cli ping ตรงที่สุด
    out, _ = run(c, "redis-cli ping 2>/dev/null")
    return out.strip() == "PONG"

def is_nginx_running(c):
    # Nginx ฟัง port 8080 (nginx proxies via cloudflared to 8080)
    return _port_listening(c, 8080)

def is_queue_running(c):
    out, _ = run(c, "pgrep -f 'artisan queue:work' | head -1")
    return bool(out.strip())

def is_reverb_running(c):
    # Reverb WebSocket ฟัง port 8082
    return _port_listening(c, 8082)

def is_monitor_running(c):
    # Monitor dashboard ต้องฟัง port 9999 จริงๆ
    return _port_listening(c, 9999)

def is_cloudflared_running(c):
    out, _ = run(c, "pgrep -f 'cloudflared tunnel' | head -1")
    return bool(out.strip())

# --- Restart Functions ---
def restart_redis(c):
    log.warning("RESTART: Redis...")
    run(c, "pkill -9 -f redis-server 2>/dev/null; sleep 1")
    run(c, f"nohup redis-server </dev/null >{APP_DIR}/storage/logs/redis.log 2>&1 &", timeout=5)
    time.sleep(RESTART_WAIT)
    ok = is_redis_running(c)
    log.info(f"  Redis restart {'OK' if ok else 'FAILED'}")
    return ok

def restart_nginx(c):
    log.warning("RESTART: Nginx...")
    run(c, "pkill -9 -f 'nginx: master' 2>/dev/null; sleep 1")
    run(c, "nginx 2>&1", timeout=5)
    time.sleep(RESTART_WAIT)
    ok = is_nginx_running(c)
    log.info(f"  Nginx restart {'OK' if ok else 'FAILED'}")
    return ok

def restart_queue(c):
    log.warning("RESTART: Queue Worker...")
    run(c, "pkill -9 -f 'artisan queue:work' 2>/dev/null; sleep 1")
    cmd = (f"nohup php {APP_DIR}/artisan queue:work redis "
           f"--queue=line-notifications,default "
           f"--tries=3 --sleep=3 --max-time=3600 "
           f"</dev/null >{APP_DIR}/storage/logs/queue.log 2>&1 &")
    run(c, f"cd {APP_DIR} && {cmd}", timeout=5)
    time.sleep(RESTART_WAIT)
    ok = is_queue_running(c)
    log.info(f"  Queue Worker restart {'OK' if ok else 'FAILED'}")
    return ok

def restart_reverb(c):
    log.warning("RESTART: Reverb...")
    run(c, "pkill -9 -f 'artisan reverb:start' 2>/dev/null; sleep 1")
    cmd = (f"nohup php {APP_DIR}/artisan reverb:start "
           f"--host=0.0.0.0 --port=8082 "
           f"</dev/null >{APP_DIR}/storage/logs/reverb.log 2>&1 &")
    run(c, f"cd {APP_DIR} && {cmd}", timeout=5)
    time.sleep(RESTART_WAIT)
    ok = is_reverb_running(c)
    log.info(f"  Reverb restart {'OK' if ok else 'FAILED'}")
    return ok

def restart_monitor(c):
    log.warning("RESTART: Monitor Server...")
    run(c, "pkill -9 -f 'monitor_server.py' 2>/dev/null; sleep 1")
    run(c, f"nohup python {APP_DIR}/py/monitor_server.py </dev/null >{APP_DIR}/monitor.log 2>&1 &", timeout=5)
    time.sleep(RESTART_WAIT)
    ok = is_monitor_running(c)
    log.info(f"  Monitor Server restart {'OK' if ok else 'FAILED'}")
    return ok

def _get_new_cf_url(c, wait_secs: int = 25) -> str | None:
    """รอ cloudflared negotiate URL ใหม่ — return URL หรือ None"""
    import re
    deadline = time.time() + wait_secs
    while time.time() < deadline:
        log_text, _ = run(c, f"cat {TERMUX_HOME}/cloudflared.log 2>/dev/null | tail -60")
        match = re.search(r'https://[a-z0-9\-]+\.trycloudflare\.com', log_text)
        if match:
            return match.group(0)
        time.sleep(3)
    return None

def _update_env_url(c, new_url: str):
    """update APP_URL ใน .env และ clear Laravel cache"""
    import re
    log.info(f"  Updating .env APP_URL → {new_url}")
    run(c, f"sed -i 's|APP_URL=.*|APP_URL={new_url}|g' {APP_DIR}/.env")
    # Update REVERB_HOST ด้วย (ถ้าใช้ CF domain)
    domain = re.sub(r'https?://', '', new_url)
    env_out, _ = run(c, f"grep REVERB_HOST {APP_DIR}/.env")
    if 'trycloudflare.com' in env_out:
        run(c, f"sed -i 's|REVERB_HOST=.*|REVERB_HOST={domain}|g' {APP_DIR}/.env")
    # Clear cache
    log.info("  Clearing Laravel cache...")
    out, _ = run(c, f"cd {APP_DIR} && php artisan optimize:clear 2>&1", timeout=30)
    log.info(f"  Cache clear: {out[:80]}")

def restart_cloudflared(c):
    log.warning("RESTART: Cloudflared (Anonymous Tunnel)...")
    run(c, f"rm -f {APP_DIR}/cloudflared.log")
    run(c, "pkill -9 -f 'cloudflared tunnel' 2>/dev/null; sleep 1")

    CF_CMD = (
        "cloudflared tunnel"
        " --protocol quic"
        " --ha-connections 4"
        " --no-autoupdate"
        " --proxy-keepalive-connections 100"
        " --proxy-keepalive-timeout 90s"
        " --proxy-connect-timeout 10s"
        " --metrics 0.0.0.0:20241"
        " --url http://127.0.0.1:8080"
    )
    run(c, f"nohup {CF_CMD} </dev/null >{APP_DIR}/cloudflared.log 2>&1 &", timeout=5)

    # รอ URL ใหม่ (trycloudflare.com URL เปลี่ยนทุก restart)
    log.info("  Waiting for new tunnel URL (up to 30s)...")
    new_url = _get_new_cf_url(c, wait_secs=30)

    ok = is_cloudflared_running(c)
    log.info(f"  Cloudflared restart {'OK ✅' if ok else 'FAILED ❌'}")

    if ok and new_url:
        log.info(f"  New URL: {new_url}")
        _update_env_url(c, new_url)
    elif ok and not new_url:
        log.warning("  Cloudflared running but URL not found yet")

    return ok


# --- Service Definitions (dependency order) ---
SERVICES = [
    {"name": "Redis",          "check": is_redis_running,       "restart": restart_redis,       "cascade": ["Queue Worker", "Reverb"]},
    {"name": "Nginx",          "check": is_nginx_running,       "restart": restart_nginx,       "cascade": []},
    {"name": "Queue Worker",   "check": is_queue_running,       "restart": restart_queue,       "cascade": []},
    {"name": "Reverb",         "check": is_reverb_running,      "restart": restart_reverb,      "cascade": []},
    {"name": "Monitor Server", "check": is_monitor_running,     "restart": restart_monitor,     "cascade": []},
    {"name": "Cloudflared",    "check": is_cloudflared_running, "restart": restart_cloudflared, "cascade": []},
]

# --- Main Check Loop ---
def check_and_heal(forced: set = None):
    forced = forced or set()
    status = {}

    client = get_ssh_client()
    if not client:
        log.error("Cannot SSH — skipping this round")
        return {}

    try:
        for svc in SERVICES:
            name = svc["name"]
            is_up = svc["check"](client)
            status[name] = is_up

            if is_up:
                log.debug(f"[OK] {name}")
            else:
                log.warning(f"[DOWN] {name}")
                if can_restart(name) or name in forced:
                    ok = svc["restart"](client)
                    mark_restarted(name)
                    status[name] = ok
                    if ok:
                        for dep in svc.get("cascade", []):
                            forced.add(dep)
                else:
                    mins_left = MAX_RESTART_MINS - int((time.time() - last_restart.get(name, 0)) / 60)
                    log.warning(f"  Cooldown {mins_left} min left for {name}")
    finally:
        client.close()

    return status

def main():
    log.info("=" * 60)
    log.info("  Uni-Activity Auto-Heal Watchdog STARTED")
    log.info(f"  Target : {SSH_HOST}:{SSH_PORT}")
    log.info(f"  Interval: {CHECK_INTERVAL}s  |  Cooldown: {MAX_RESTART_MINS}m")
    log.info(f"  Log file: {log_file}")
    log.info("=" * 60)

    round_num = 0
    while True:
        round_num += 1
        log.info(f"--- Round {round_num} ---")
        try:
            status = check_and_heal()
            if status:
                icons = {True: "OK", False: "DOWN"}
                summary = "  |  ".join(f"[{icons[v]}] {k}" for k, v in status.items())
                log.info(summary)
                if not all(status.values()):
                    still_down = [k for k, v in status.items() if not v]
                    log.warning(f"Still DOWN after recovery: {', '.join(still_down)}")
        except KeyboardInterrupt:
            log.info("Watchdog stopped by user.")
            break
        except Exception as e:
            log.error(f"Unexpected error: {e}", exc_info=True)

        time.sleep(CHECK_INTERVAL)

if __name__ == "__main__":
    main()
