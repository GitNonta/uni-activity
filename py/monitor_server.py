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
url_status = {"online": False, "ping_ms": 0, "error": "", "url": ""}
alerts_history = collections.deque(maxlen=100)
active_alert_ids = set()

# ── Telegram Alerting ────────────────────────────────────────────────────────
TELEGRAM_BOT_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "")
TELEGRAM_CHAT_ID   = os.environ.get("TELEGRAM_CHAT_ID", "")
_tg_sent_ids: set  = set()   # ป้องกันส่งซ้ำ (cache_key = alert_id:bucket)
_tg_resolved: set  = set()   # track alert ที่ resolved แล้ว
_tg_last_daily: float = 0.0  # สำหรับ daily report
_tg_alert_cooldown: dict = {}  # alert_id -> last_sent timestamp (ป้องกัน spam)
_monitor_start_time: float = time.time()  # เวลาที่ monitor เริ่มทำงาน

ALERT_MIN_INTERVAL = 600   # ขั้นต่ำ 10 นาที ระหว่าง alert เดิม
STARTUP_GRACE     = 90    # วินาที หลัง start ไม่ส่ง cf_offline (รอ tunnel ขึ้น)

def tg_send(text: str, parse_mode: str = "HTML") -> bool:
    """ส่งข้อความไป Telegram — return True ถ้าสำเร็จ"""
    if not TELEGRAM_BOT_TOKEN or not TELEGRAM_CHAT_ID:
        return False
    try:
        import urllib.request, json as _json
        payload = _json.dumps({
            "chat_id": TELEGRAM_CHAT_ID,
            "text": text,
            "parse_mode": parse_mode,
            "disable_web_page_preview": True,
        }).encode()
        req = urllib.request.Request(
            f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage",
            data=payload,
            headers={"Content-Type": "application/json"},
            method="POST",
        )
        with urllib.request.urlopen(req, timeout=8) as r:
            return r.status == 200
    except Exception:
        return False

def tg_alert(alert_id: str, alert_type: str, message: str) -> None:
    """ส่ง alert ใหม่ (ส่งซ้ำได้อีกครั้งหลัง ALERT_MIN_INTERVAL วินาที)"""
    now = time.time()

    # ── Startup grace: ไม่ส่ง cf_offline ในช่วง 90 วิแรก ──────────────────
    if alert_id == "cf_offline" and (now - _monitor_start_time) < STARTUP_GRACE:
        return

    # ── Cooldown ต่อ alert_id: ขั้นต่ำ ALERT_MIN_INTERVAL วินาที ────────────
    last_sent = _tg_alert_cooldown.get(alert_id, 0.0)
    if now - last_sent < ALERT_MIN_INTERVAL:
        return
    _tg_alert_cooldown[alert_id] = now

    # ── Bucket dedup (ป้องกัน race condition) ────────────────────────────────
    cache_key = f"{alert_id}:{int(now // ALERT_MIN_INTERVAL)}"
    if cache_key in _tg_sent_ids:
        return
    _tg_sent_ids.add(cache_key)

    # จำกัด size: ลบเฉพาะ bucket เก่า ไม่ clear ทั้งหมด
    if len(_tg_sent_ids) > 500:
        current_bucket = int(now // ALERT_MIN_INTERVAL)
        expired = {k for k in _tg_sent_ids if ":" in k and int(k.rsplit(":", 1)[1]) < current_bucket - 1}
        _tg_sent_ids -= expired
        if len(_tg_sent_ids) > 500:  # ยังเยอะอยู่ ลบครึ่งเก่า
            _tg_sent_ids.clear()

    icon = "🚨" if alert_type == "critical" else "⚠️"
    ts   = time.strftime("%H:%M:%S")
    text = (
        f"{icon} <b>UniActivity Server Alert</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"📋 <b>{message}</b>\n"
        f"🕐 {ts}\n"
        f"🆔 <code>{alert_id}</code>"
    )
    threading.Thread(target=tg_send, args=(text,), daemon=True).start()

def tg_resolved(alert_id: str, message: str) -> None:
    """แจ้งว่า alert หายแล้ว (ส่งแค่ครั้งเดียว)"""
    if alert_id in _tg_resolved:
        return
    _tg_resolved.add(alert_id)
    ts   = time.strftime("%H:%M:%S")
    text = (
        f"✅ <b>Resolved</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"📋 {message}\n"
        f"🕐 {ts}"
    )
    threading.Thread(target=tg_send, args=(text,), daemon=True).start()

def tg_daily_report(stats: dict) -> None:
    """ส่ง daily summary ทุก 24 ชั่วโมง"""
    global _tg_last_daily
    if time.time() - _tg_last_daily < 86400:
        return
    _tg_last_daily = time.time()
    mem  = stats.get("memory", {})
    disk = stats.get("disk", {})
    load = stats.get("load", [0, 0, 0])
    svcs = stats.get("services", {})
    running = sum(1 for s in svcs.values() if "Running" in s)
    total   = len(svcs)
    ts = time.strftime("%Y-%m-%d %H:%M")
    text = (
        f"📊 <b>Daily Server Report</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"🕐 {ts}\n"
        f"⚡ Load Avg  : {load[0]} / {load[1]} / {load[2]}\n"
        f"💾 RAM       : {mem.get('used_mb',0)}/{mem.get('total_mb',0)} MB "
        f"({mem.get('percent',0)}%)\n"
        f"💿 Disk      : {disk.get('used_gb',0)}/{disk.get('total_gb',0)} GB "
        f"({disk.get('percent',0)}%)\n"
        f"🔧 Services  : {running}/{total} Running\n"
        f"🌐 Uptime    : {stats.get('uptime','N/A')}"
    )
    threading.Thread(target=tg_send, args=(text,), daemon=True).start()


# ── Telegram Bot Command Handler ─────────────────────────────────────────────
_tg_last_update_id: int = 0
_tg_cmd_queue: "queue.Queue[str]" = None   # กำหนดใน __main__

def tg_handle_commands() -> None:
    """Long-poll Telegram getUpdates — block 20 วิ แต่ตอบสนองทันทีเมื่อมี update"""
    global _tg_last_update_id
    if not TELEGRAM_BOT_TOKEN or not TELEGRAM_CHAT_ID:
        return

    try:
        import urllib.request, json as _json
        # long-poll 20 วิ — Telegram จะ return ทันทีเมื่อมี update ใหม่
        # ไม่ต้อง sleep เพิ่มอีก
        url = (
            f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/getUpdates"
            f"?offset={_tg_last_update_id + 1}&limit=10&timeout=5"
        )
        req = urllib.request.Request(url, headers={"User-Agent": "UniMonitor/2.0"})
        with urllib.request.urlopen(req, timeout=10) as r:
            data = _json.loads(r.read())

        for update in data.get("result", []):
            _tg_last_update_id = update["update_id"]
            msg  = update.get("message", {})
            chat = str(msg.get("chat", {}).get("id", ""))
            text = msg.get("text", "").strip()

            if chat != TELEGRAM_CHAT_ID:
                tg_send(f"⛔ Unauthorized: <code>{chat}</code>")
                continue

            # dispatch ใน thread แยก — ไม่บล็อก poll loop
            threading.Thread(target=_dispatch_command, args=(text,), daemon=True).start()

    except Exception:
        pass


def tg_command_poll_thread() -> None:
    """Long-poll loop — ไม่มี sleep เพิ่ม รอ response จาก Telegram แทน"""
    while True:
        try:
            tg_handle_commands()
        except Exception:
            time.sleep(1)   # sleep แค่ตอน error เท่านั้น


def _dispatch_command(text: str) -> None:
    """เรียก handler ตาม command ที่ได้รับ"""
    cmd = text.split()[0].lower().split("@")[0] if text else ""

    handlers = {
        "/start"          : _cmd_start,
        "/help"           : _cmd_help,
        "/status"         : _cmd_status,
        "/services"       : _cmd_services,
        "/load"           : _cmd_load,
        "/disk"           : _cmd_disk,
        "/memory"         : _cmd_memory,
        "/alerts"         : _cmd_alerts,
        "/logs"           : _cmd_logs,
        "/ports"          : _cmd_ports,
        "/top"            : _cmd_top,
        "/redis"          : _cmd_redis,
        "/db"             : _cmd_db,
        "/network"        : _cmd_network,
        "/restart"        : _cmd_restart,
        "/clear"          : _cmd_clear_cache,
        "/report"         : _cmd_force_report,
        # ── Cloudflare Tunnel ──────────────────────────
        "/tunnel"              : _cmd_tunnel,
        "/tunnel_status"       : _cmd_tunnel,
        "/tunnel_url"          : _cmd_tunnel_url,
        "/tunnel_restart"      : _cmd_tunnel_restart,
        "/tunnel_restart_http" : _cmd_tunnel_restart_http,
        "/tunnel_restart_ssh"  : _cmd_tunnel_restart_ssh,
        "/tunnel_stop"         : _cmd_tunnel_stop,
        "/tunnel_log"          : _cmd_tunnel_log,
        "/tunnel_help"         : _cmd_tunnel_seturl,
    }

    fn = handlers.get(cmd)
    if fn:
        threading.Thread(target=fn, daemon=True).start()
    elif cmd.startswith("/"):
        tg_send(f"❓ ไม่รู้จัก command: <code>{cmd}</code>\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมด")


# ── Command Implementations ───────────────────────────────────────────────────

def _cmd_start() -> None:
    tg_send(
        "👋 <b>UniActivity Server Monitor</b>\n"
        "━━━━━━━━━━━━━━━━━━━━\n"
        "Bot พร้อมรับคำสั่งแล้ว!\n\n"
        "พิมพ์ /help เพื่อดูคำสั่งทั้งหมด"
    )


def _cmd_help() -> None:
    tg_send(
        "📋 <b>คำสั่งที่ใช้ได้</b>\n"
        "━━━━━━━━━━━━━━━━━━━━\n"
        "📊 <b>ภาพรวม</b>\n"
        "  /status   — สรุปสถานะทุกอย่าง\n"
        "  /report   — Daily report ทันที\n"
        "  /alerts   — Alert ที่ active อยู่\n\n"
        "⚙️ <b>Services</b>\n"
        "  /services — สถานะ services ทั้งหมด\n"
        "  /ports    — Ports ที่เปิดอยู่\n"
        "  /redis    — Redis stats\n"
        "  /db       — PostgreSQL stats\n\n"
        "📈 <b>Resources</b>\n"
        "  /load     — CPU Load average\n"
        "  /memory   — RAM & Swap usage\n"
        "  /disk     — Disk usage\n"
        "  /network  — Network stats\n"
        "  /top      — Top 5 processes\n\n"
        "🌐 <b>Cloudflare Tunnel</b>\n"
        "  /tunnel              — สถานะ Tunnel ทั้งหมด\n"
        "  /tunnel_url          — URLs ทั้งหมด + SSH command\n"
        "  /tunnel_restart      — Restart ทั้ง HTTP + SSH\n"
        "  /tunnel_restart_http — Restart เฉพาะ HTTP (:8080)\n"
        "  /tunnel_restart_ssh  — Restart เฉพาะ SSH (:80)\n"
        "  /tunnel_stop         — หยุด Tunnel ทั้งหมด\n"
        "  /tunnel_log          — Tunnel log ล่าสุด\n\n"
        "📝 <b>Logs</b>\n"
        "  /logs     — Laravel error log ล่าสุด\n\n"
        "🔧 <b>Actions</b>\n"
        "  /restart  — Restart services ที่ down\n"
        "  /clear    — Clear Laravel caches"
    )


def _cmd_status() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}

    if not s:
        tg_send("⏳ กำลังรวบรวมข้อมูล...")
        return

    mem   = s.get("memory", {})
    disk  = s.get("disk", {})
    load  = s.get("load", [0, 0, 0])
    svcs  = s.get("services", {})
    alrts = s.get("alerts", [])
    temp  = s.get("temp", "N/A")
    uptime = s.get("uptime", "N/A")

    running = sum(1 for v in svcs.values() if "Running" in v)
    total   = len(svcs)
    svc_icon = "✅" if running == total else "⚠️"

    load_icon = "🔴" if load[0] > 8 else "🟡" if load[0] > 5 else "🟢"
    mem_icon  = "🔴" if mem.get("percent",0) > 90 else "🟡" if mem.get("percent",0) > 75 else "🟢"
    disk_icon = "🔴" if disk.get("percent",0) > 90 else "🟡" if disk.get("percent",0) > 75 else "🟢"
    alert_icon = "🚨" if alrts else "✅"

    ts = time.strftime("%Y-%m-%d %H:%M:%S")
    tg_send(
        f"📊 <b>Server Status</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"🕐 {ts}\n"
        f"⏱ Uptime : {uptime}\n\n"
        f"{load_icon} Load     : {load[0]} / {load[1]} / {load[2]}\n"
        f"{mem_icon} RAM      : {mem.get('used_mb',0)}/{mem.get('total_mb',0)} MB "
        f"({mem.get('percent',0)}%)\n"
        f"{disk_icon} Disk     : {disk.get('used_gb',0)}/{disk.get('total_gb',0)} GB "
        f"({disk.get('percent',0)}%)\n"
        f"🌡 Temp    : {temp}°C\n"
        f"{svc_icon} Services : {running}/{total} Running\n"
        f"{alert_icon} Alerts   : {len(alrts)} active"
        + (("\n\n🚨 <b>Active Alerts:</b>\n" + "\n".join(f"  • {a['message']}" for a in alrts)) if alrts else "")
    )


def _cmd_services() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    svcs = s.get("services", {})
    if not svcs:
        tg_send("⏳ ยังไม่มีข้อมูล services")
        return

    lines = []
    for name, status in svcs.items():
        ok = "Running" in status
        lines.append(f"  {'✅' if ok else '❌'} {name:<20} {status}")

    ts = time.strftime("%H:%M:%S")
    tg_send(
        f"🔧 <b>Services Status</b>  ({ts})\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        + "\n".join(lines)
    )


def _cmd_load() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    load  = s.get("load", [0, 0, 0])
    procs = s.get("advanced_metrics", {}).get("top_procs", [])
    icon  = "🔴" if load[0] > 8 else "🟡" if load[0] > 5 else "🟢"
    lines = [f"  {p['cpu']:>5}%  {p['name']}" for p in procs[:5]]
    tg_send(
        f"{icon} <b>CPU Load Average</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  1 min  : <b>{load[0]}</b>\n"
        f"  5 min  : {load[1]}\n"
        f"  15 min : {load[2]}\n\n"
        f"<b>Top Processes:</b>\n"
        + ("\n".join(lines) if lines else "  N/A")
    )


def _cmd_memory() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    mem  = s.get("memory", {})
    icon = "🔴" if mem.get("percent",0) > 90 else "🟡" if mem.get("percent",0) > 75 else "🟢"
    tg_send(
        f"{icon} <b>Memory Usage</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  Total     : {mem.get('total_mb',0)} MB\n"
        f"  Used      : {mem.get('used_mb',0)} MB  ({mem.get('percent',0)}%)\n"
        f"  Available : {mem.get('available_mb',0)} MB"
    )


def _cmd_disk() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    disk = s.get("disk", {})
    icon = "🔴" if disk.get("percent",0) > 90 else "🟡" if disk.get("percent",0) > 75 else "🟢"
    tg_send(
        f"{icon} <b>Disk Usage</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  Total : {disk.get('total_gb',0)} GB\n"
        f"  Used  : {disk.get('used_gb',0)} GB  ({disk.get('percent',0)}%)\n"
        f"  Free  : {round(disk.get('total_gb',0) - disk.get('used_gb',0), 2)} GB"
    )


def _cmd_alerts() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    alrts   = s.get("alerts", [])
    history = list(alerts_history)[:5]

    if not alrts:
        msg = "✅ <b>ไม่มี Active Alerts</b>\n━━━━━━━━━━━━━━━━━━━━\n"
    else:
        lines = [f"  🚨 {a['message']}" for a in alrts]
        msg = f"🚨 <b>Active Alerts ({len(alrts)})</b>\n━━━━━━━━━━━━━━━━━━━━\n" + "\n".join(lines) + "\n"

    if history:
        msg += "\n<b>ประวัติ 5 รายการล่าสุด:</b>\n"
        for h in history:
            msg += f"  [{h.get('time','')}] {h.get('message','')}\n"

    tg_send(msg)


def _cmd_logs() -> None:
    import subprocess
    try:
        log_path = "/data/data/com.termux/files/home/uni-activity/storage/logs/laravel.log"
        result = subprocess.run(
            ["tail", "-30", log_path],
            capture_output=True, text=True, timeout=5
        )
        lines = result.stdout.strip().splitlines()
        errors = [l for l in lines if any(k in l for k in ["ERROR", "CRITICAL", "Exception", "exception"])]
        if errors:
            out = "\n".join(errors[-10:])
            tg_send(f"📝 <b>Laravel Errors (ล่าสุด)</b>\n━━━━━━━━━━━━━━━━━━━━\n<code>{out[:1000]}</code>")
        else:
            last = "\n".join(lines[-5:]) if lines else "ว่าง"
            tg_send(f"📝 <b>Laravel Log</b>\n━━━━━━━━━━━━━━━━━━━━\n✅ ไม่มี Errors\n<code>{last[:500]}</code>")
    except Exception as e:
        tg_send(f"❌ อ่าน log ไม่ได้: {e}")


def _cmd_ports() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    ports = s.get("listening_ports", [])
    known = {
        8022: "SSH", 5432: "PostgreSQL", 6379: "Redis",
        8080: "Nginx", 8082: "Reverb", 8000: "PHP/Artisan",
        9999: "Monitor", 9998: "UDP Inspector", 9997: "UDP AI",
    }
    lines = []
    for p in sorted(ports):
        name = known.get(p, "")
        lines.append(f"  :{p:<6} {name}")
    tg_send(
        f"🌐 <b>Listening Ports</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        + ("\n".join(lines) if lines else "  N/A")
    )


def _cmd_top() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    procs = s.get("advanced_metrics", {}).get("top_procs", [])
    if not procs:
        tg_send("⏳ ยังไม่มีข้อมูล processes")
        return
    lines = [
        f"  {p['cpu']:>5}% CPU  {p['mem']:>4}% MEM  {p['name'][:30]}"
        for p in procs[:8]
    ]
    tg_send(
        f"🔝 <b>Top Processes</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        + "\n".join(lines)
    )


def _cmd_redis() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    redis = s.get("advanced_metrics", {}).get("redis", {})
    queue = s.get("advanced_metrics", {}).get("queue", {})
    tg_send(
        f"🗄 <b>Redis Stats</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  Memory  : {redis.get('used_memory','N/A')}\n"
        f"  Clients : {redis.get('clients',0)}\n"
        f"  Queue   : {queue.get('pending',0)} pending\n"
        f"  Failed  : {queue.get('failed',0)} jobs"
    )


def _cmd_db() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    pg = s.get("advanced_metrics", {}).get("postgres", {})
    tg_send(
        f"🐘 <b>PostgreSQL Stats</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  Connections : {pg.get('connections',0)}\n"
        f"  DB Size     : {pg.get('db_size','N/A')}"
    )


def _cmd_network() -> None:
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    net  = s.get("advanced_metrics", {}).get("net_speeds", {})
    neti = s.get("network_info", {})
    pub_ip = s.get("public_ip", "N/A")
    tg_send(
        f"📡 <b>Network Stats</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  Public IP  : {pub_ip}\n"
        f"  Local IP   : {neti.get('local_ip','N/A')}\n"
        f"  Interface  : {neti.get('interface','N/A')}\n"
        f"  ↓ Download : {net.get('rx_kbps',0)} KB/s\n"
        f"  ↑ Upload   : {net.get('tx_kbps',0)} KB/s"
    )


def _cmd_tunnel() -> None:
    """แสดงสถานะ Cloudflare Tunnel ทั้งหมด รวม SSH URL"""
    import subprocess

    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}

    cf_url    = s.get("cf_url", "Not Found")
    cf_status = s.get("cf_status", {})
    online    = cf_status.get("online", False)
    ping_ms   = cf_status.get("ping_ms", 0)
    cf_stats  = s.get("advanced_metrics", {}).get("cloudflared", {})
    latency   = cf_stats.get("latency_ms", 0)
    http_url  = cf_stats.get("http_url", cf_url)
    ssh_url   = cf_stats.get("ssh_url", "")
    http_ok   = cf_stats.get("http_online", online)
    ssh_ok    = cf_stats.get("ssh_online", False)

    # processes
    proc_count = 0
    pids = []
    try:
        r = subprocess.run(["pgrep", "-a", "cloudflared"], capture_output=True, text=True)
        lines = [l for l in r.stdout.strip().splitlines() if l]
        proc_count = len(lines)
        pids = [l.split()[0] for l in lines]
    except Exception:
        pass

    # metrics
    metrics_up = False
    try:
        r = subprocess.run(["curl", "-s", "-m", "1", "http://127.0.0.1:20241/metrics"],
                           capture_output=True, text=True, timeout=2)
        metrics_up = r.returncode == 0 and len(r.stdout) > 10
    except Exception:
        pass

    # log line
    log_line = ""
    for lp in ["/data/data/com.termux/files/home/cloudflared.log",
               "/data/data/com.termux/files/usr/var/log/sv/cloudflared/current"]:
        try:
            r = subprocess.run(["tail", "-3", lp], capture_output=True, text=True)
            if r.stdout.strip():
                log_line = r.stdout.strip().splitlines()[-1][:100]
                break
        except Exception:
            pass

    ts         = time.strftime("%H:%M:%S")
    proc_icon  = "✅" if proc_count > 0 else "❌"
    met_icon   = "✅" if metrics_up else "⚠️"

    # error description
    error_line = ""
    if not online:
        etype = url_status.get("error", "UNKNOWN")
        edesc = {
            "DNS_FAIL"    : "DNS resolve ล้มเหลว",
            "TIMEOUT"     : "Connection Timeout",
            "SSL_ERROR"   : "SSL/TLS Error",
            "CONN_REFUSED": "Connection Refused",
            "HTTP_502"    : "HTTP 502 Bad Gateway",
            "HTTP_521"    : "HTTP 521 Web Server Down",
            "HTTP_522"    : "HTTP 522 Timed Out",
            "HTTP_523"    : "HTTP 523 Unreachable",
            "HTTP_524"    : "HTTP 524 Timeout",
            "NO_URL"      : "ไม่มี URL ในระบบ",
        }.get(etype, etype)
        error_line = f"⚠️ Error   : {edesc}\n"

    # ── สร้าง URL lines ──
    http_icon = "🟢" if http_ok else "🔴"
    ssh_icon  = "🟢" if ssh_ok  else "🔴"

    http_line = (
        f"{http_icon} HTTP/App  :\n"
        f"  <a href='{http_url}'>{http_url}</a>\n"
        if http_url and http_url != "Not Found"
        else f"🔴 HTTP/App  : ⚠️ ไม่มี URL\n"
    )

    ssh_line = (
        f"{ssh_icon} SSH Tunnel:\n"
        f"  <a href='{ssh_url}'>{ssh_url}</a>\n"
        if ssh_url
        else "⚪ SSH Tunnel: ไม่พบ URL\n"
    )

    # SSH connection command
    ssh_cmd_line = ""
    if ssh_url:
        domain = ssh_url.replace("https://", "").replace("http://", "").strip("/")
        ssh_cmd_line = (
            f"\n💻 <b>คำสั่ง SSH ผ่าน Cloudflare:</b>\n"
            f"<code>ssh -o ProxyCommand='cloudflared access ssh --hostname {domain}' "
            f"u0_a175@{domain} -p 22</code>\n"
        )

    tg_send(
        f"🌐 <b>Cloudflare Tunnel Status</b>  ({ts})\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"{error_line}"
        f"{proc_icon} Processes : {proc_count}"
        + (f"  (PID: {', '.join(pids[:3])})" if pids else "") + "\n"
        + f"⚡ HTTP Ping : {ping_ms} ms\n"
        f"📶 QUIC RTT  : {latency} ms\n"
        f"{met_icon} Metrics   : {'OK' if metrics_up else 'N/A'}\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        + http_line
        + ssh_line
        + ssh_cmd_line
        + (f"\n📋 Log:\n<code>{log_line}</code>" if log_line else "")
    )


def _cmd_tunnel_restart() -> None:
    """Restart Cloudflare Tunnel ทั้ง 2 (HTTP :8080 + SSH :80) และรอ URLs ใหม่"""
    import subprocess, re

    tg_send(
        "🔄 <b>Restarting ALL Cloudflare Tunnels...</b>\n"
        "━━━━━━━━━━━━━━━━━━━━\n"
        "⏳ กำลัง restart:\n"
        "  • Tunnel 1 — HTTP / Laravel (:8080)\n"
        "  • Tunnel 2 — SSH (:80)\n"
        "รอ URLs ใหม่สักครู่..."
    )

    def do_restart():
        try:
            # ── 1. Kill ทุก cloudflared ───────────────────────────────────
            subprocess.run(["pkill", "-9", "cloudflared"], capture_output=True)
            time.sleep(3)

            log_http = "/data/data/com.termux/files/home/cloudflared.log"
            log_ssh  = "/data/data/com.termux/files/home/cloudflared-ssh.log"

            # clear logs
            for lp in [log_http, log_ssh]:
                with open(lp, "w") as f:
                    f.write("")

            # ── 2. Start Tunnel 1 — HTTP :8080 ───────────────────────────
            subprocess.Popen(
                f"nohup cloudflared tunnel --url http://127.0.0.1:8080 "
                f"--no-autoupdate > {log_http} 2>&1 &",
                shell=True,
            )
            time.sleep(2)

            # ── 3. Start Tunnel 2 — SSH :80 ──────────────────────────────
            subprocess.Popen(
                f"nohup cloudflared tunnel --url http://127.0.0.1:80 "
                f"--no-autoupdate > {log_ssh} 2>&1 &",
                shell=True,
            )

            # ── 4. รอ URLs จากทั้งสอง logs (timeout 40 วิ) ────────────────
            http_url = None
            ssh_url  = None

            for _ in range(40):
                time.sleep(1)
                try:
                    if not http_url:
                        with open(log_http, "r") as f:
                            content = f.read()
                        m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                        if m:
                            http_url = m.group(0)
                except Exception:
                    pass

                try:
                    if not ssh_url:
                        with open(log_ssh, "r") as f:
                            content = f.read()
                        m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                        if m:
                            ssh_url = m.group(0)
                except Exception:
                    pass

                if http_url and ssh_url:
                    break

            # fallback: อ่านจาก metrics ports
            if not http_url or not ssh_url:
                import urllib.request as _ureq
                for port, key in [(20241, "http"), (20242, "ssh")]:
                    try:
                        resp = _ureq.urlopen(f"http://127.0.0.1:{port}/metrics", timeout=2)
                        content = resp.read().decode()
                        m = re.search(r'userHostname="(https://[^"]+)"', content)
                        if m:
                            if key == "http" and not http_url:
                                http_url = m.group(1)
                            elif key == "ssh" and not ssh_url:
                                ssh_url = m.group(1)
                    except Exception:
                        pass

            # ── 5. อัพเดต .env และ active_url.json ───────────────────────
            env_path  = "/data/data/com.termux/files/home/uni-activity/.env"
            json_path = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"

            if http_url and os.path.exists(env_path):
                with open(env_path, "r") as f:
                    env_lines = f.readlines()
                with open(env_path, "w") as f:
                    for line in env_lines:
                        f.write(f"APP_URL={http_url}\n" if line.startswith("APP_URL=") else line)

            os.makedirs(os.path.dirname(json_path), exist_ok=True)
            with open(json_path, "w") as f:
                json.dump({
                    "url"        : http_url or "",
                    "ssh_url"    : ssh_url  or "",
                    "updated_at" : time.strftime("%Y-%m-%d %H:%M:%S"),
                }, f)

            # ── 6. แจ้งผล ─────────────────────────────────────────────────
            h_icon = "✅" if http_url else "❌"
            s_icon = "✅" if ssh_url  else "❌"
            ts     = time.strftime("%H:%M:%S")

            http_line = (
                f"{h_icon} <b>HTTP / Laravel App:</b>\n"
                f"<a href='{http_url}'>{http_url}</a>"
                if http_url else f"{h_icon} HTTP URL: ไม่ได้รับ"
            )
            ssh_line = (
                f"{s_icon} <b>SSH Tunnel:</b>\n"
                f"<a href='{ssh_url}'>{ssh_url}</a>\n\n"
                f"💻 <b>SSH Command:</b>\n"
                f"<code>ssh -o ProxyCommand='cloudflared access ssh "
                f"--hostname {ssh_url.replace('https://','').strip('/')}' "
                f"u0_a175@{ssh_url.replace('https://','').strip('/')} -p 22</code>"
                if ssh_url else f"{s_icon} SSH URL: ไม่ได้รับ"
            )

            tg_send(
                f"✅ <b>Tunnels Restarted!</b>  ({ts})\n"
                f"━━━━━━━━━━━━━━━━━━━━\n"
                f"{http_line}\n\n"
                f"{ssh_line}\n\n"
                f"📝 .env และ active_url.json อัพเดตแล้ว"
            )

        except Exception as e:
            tg_send(f"❌ Restart ล้มเหลว: {e}")

    threading.Thread(target=do_restart, daemon=True).start()


def _cmd_tunnel_restart_http() -> None:
    """Restart เฉพาะ HTTP Tunnel (:8080 → Laravel App)"""
    import subprocess, re

    tg_send(
        "🔄 <b>Restarting HTTP Tunnel only...</b>\n"
        "━━━━━━━━━━━━━━━━━━━━\n"
        "⏳ Tunnel: HTTP / Laravel (:8080)\n"
        "SSH Tunnel จะยังทำงานปกติ"
    )

    def do_http():
        try:
            log_http = "/data/data/com.termux/files/home/cloudflared.log"

            # ── 1. Kill เฉพาะ cloudflared ที่ forward :8080 ─────────────────
            # ใช้ pkill -f เพื่อ match argument จริงๆ ไม่ใช่แค่ตัวเลขใน path
            subprocess.run(
                ["pkill", "-9", "-f", "cloudflared.*8080"],
                capture_output=True
            )
            time.sleep(3)  # รอให้ process ตายจริงก่อน

            # ── 2. Clear log หลัง kill (ไม่ใช่ก่อน) ─────────────────────────
            with open(log_http, "w") as f:
                f.write("")

            # ── 3. Start cloudflared ใหม่ ─────────────────────────────────
            subprocess.Popen(
                f"nohup cloudflared tunnel --url http://127.0.0.1:8080 "
                f"--no-autoupdate > {log_http} 2>&1 &",
                shell=True,
            )

            # ── 4. รอ URL ใหม่จาก log (สูงสุด 45 วิ) ─────────────────────
            http_url = None
            for _ in range(45):
                time.sleep(1)
                try:
                    with open(log_http, "r") as f:
                        content = f.read()
                    # ต้องเจอ URL ที่ขึ้นต้นด้วย https:// และมี subdomain จริง
                    # (ไม่ใช่ https://api.trycloudflare.com)
                    m = re.search(
                        r'https://[a-zA-Z0-9]+-[a-zA-Z0-9]+-[a-zA-Z0-9-]+\.trycloudflare\.com',
                        content
                    )
                    if m:
                        http_url = m.group(0)
                        break
                    # fallback: pattern ทั่วไป (กัน edge case subdomain สั้น)
                    m2 = re.search(
                        r'https://(?!api\b)[a-zA-Z0-9][a-zA-Z0-9-]+\.trycloudflare\.com',
                        content
                    )
                    if m2:
                        http_url = m2.group(0)
                        break
                except Exception:
                    pass

            # ── 5. fallback: metrics port (รอให้เก่าตายก่อน ~10 วิ) ────────
            if not http_url:
                time.sleep(5)  # รอให้ cloudflared เก่าตาย + ใหม่ขึ้น metrics
                try:
                    import urllib.request as _ur
                    resp = _ur.urlopen("http://127.0.0.1:20241/metrics", timeout=3)
                    raw = resp.read().decode()
                    m = re.search(r'userHostname="(https://(?!api)[^"]+)"', raw)
                    if m:
                        http_url = m.group(1)
                except Exception:
                    pass

            if http_url:
                # อัพเดต .env
                env_path = "/data/data/com.termux/files/home/uni-activity/.env"
                if os.path.exists(env_path):
                    with open(env_path, "r") as f:
                        env_lines = f.readlines()
                    with open(env_path, "w") as f:
                        for line in env_lines:
                            f.write(f"APP_URL={http_url}\n" if line.startswith("APP_URL=") else line)

                # อัพเดต active_url.json
                jp = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
                os.makedirs(os.path.dirname(jp), exist_ok=True)
                old_data = {}
                try:
                    with open(jp, "r") as f:
                        old_data = json.load(f)
                except Exception:
                    pass
                old_data.update({"url": http_url, "updated_at": time.strftime("%Y-%m-%d %H:%M:%S")})
                with open(jp, "w") as f:
                    json.dump(old_data, f)

                ts = time.strftime("%H:%M:%S")
                tg_send(
                    f"✅ <b>HTTP Tunnel Restarted!</b>  ({ts})\n"
                    f"━━━━━━━━━━━━━━━━━━━━\n"
                    f"🌐 <b>HTTP / Laravel App:</b>\n"
                    f"<a href='{http_url}'>{http_url}</a>\n\n"
                    f"📝 .env อัพเดตแล้ว\n"
                    f"🔐 SSH Tunnel ไม่ได้รับผลกระทบ"
                )
            else:
                tg_send(
                    "⚠️ HTTP Tunnel started แต่ยังไม่ได้ URL\n"
                    "รอสักครู่แล้วลอง /tunnel"
                )
        except Exception as e:
            tg_send(f"❌ HTTP Restart ล้มเหลว: {e}")

    threading.Thread(target=do_http, daemon=True).start()



def _cmd_tunnel_restart_ssh() -> None:
    """Restart เฉพาะ SSH Tunnel (:80)"""
    import subprocess, re

    tg_send(
        "🔄 <b>Restarting SSH Tunnel only...</b>\n"
        "━━━━━━━━━━━━━━━━━━━━\n"
        "⏳ Tunnel: SSH (:80)\n"
        "HTTP/Laravel Tunnel จะยังทำงานปกติ"
    )

    def do_ssh():
        try:
            # kill เฉพาะ cloudflared ตัวที่ connect :80
            procs = subprocess.run(["pgrep", "-a", "cloudflared"],
                                   capture_output=True, text=True).stdout.strip().splitlines()
            for line in procs:
                if ":80" in line and "8080" not in line and "8082" not in line:
                    pid = line.split()[0]
                    subprocess.run(["kill", "-9", pid], capture_output=True)
            time.sleep(3)

            log_ssh = "/data/data/com.termux/files/home/cloudflared-ssh.log"
            with open(log_ssh, "w") as f:
                f.write("")

            subprocess.Popen(
                f"nohup cloudflared tunnel --url http://127.0.0.1:80 "
                f"--no-autoupdate > {log_ssh} 2>&1 &",
                shell=True,
            )

            # รอ URL ใหม่ (40 วิ)
            ssh_url = None
            for _ in range(40):
                time.sleep(1)
                try:
                    with open(log_ssh, "r") as f:
                        content = f.read()
                    m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                    if m:
                        ssh_url = m.group(0)
                        break
                except Exception:
                    pass

            # fallback metrics port 20242
            if not ssh_url:
                try:
                    import urllib.request as _ur
                    resp = _ur.urlopen("http://127.0.0.1:20242/metrics", timeout=3)
                    m = re.search(r'userHostname="(https://[^"]+)"', resp.read().decode())
                    if m:
                        ssh_url = m.group(1)
                except Exception:
                    pass

            if ssh_url:
                # อัพเดต active_url.json (เฉพาะ ssh_url field)
                jp = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
                os.makedirs(os.path.dirname(jp), exist_ok=True)
                old_data = {}
                try:
                    with open(jp, "r") as f:
                        old_data = json.load(f)
                except Exception:
                    pass
                old_data.update({"ssh_url": ssh_url, "updated_at": time.strftime("%Y-%m-%d %H:%M:%S")})
                with open(jp, "w") as f:
                    json.dump(old_data, f)

                domain = ssh_url.replace("https://", "").strip("/")
                ts = time.strftime("%H:%M:%S")
                tg_send(
                    f"✅ <b>SSH Tunnel Restarted!</b>  ({ts})\n"
                    f"━━━━━━━━━━━━━━━━━━━━\n"
                    f"🔐 <b>SSH Tunnel URL:</b>\n"
                    f"<a href='{ssh_url}'>{ssh_url}</a>\n\n"
                    f"💻 <b>SSH Command:</b>\n"
                    f"<code>ssh -o ProxyCommand='cloudflared access ssh "
                    f"--hostname {domain}' u0_a175@{domain} -p 22</code>\n\n"
                    f"🔑 <b>Direct (LAN):</b>\n"
                    f"<code>ssh -p 8022 u0_a175@192.168.1.222</code>\n\n"
                    f"🌐 HTTP Tunnel ไม่ได้รับผลกระทบ"
                )
            else:
                tg_send(
                    "⚠️ SSH Tunnel started แต่ยังไม่ได้ URL\n"
                    "รอสักครู่แล้วลอง /tunnel"
                )
        except Exception as e:
            tg_send(f"❌ SSH Restart ล้มเหลว: {e}")

    threading.Thread(target=do_ssh, daemon=True).start()


def _cmd_tunnel_stop() -> None:
    """หยุด Cloudflare Tunnel ทั้งหมด"""
    import subprocess

    r = subprocess.run(["pgrep", "-c", "cloudflared"], capture_output=True, text=True)
    count = r.stdout.strip()
    if count == "0":
        tg_send("ℹ️ ไม่มี Cloudflare Tunnel รันอยู่")
        return

    subprocess.run(["pkill", "-9", "cloudflared"], capture_output=True)
    time.sleep(1)

    # ยืนยัน
    r2 = subprocess.run(["pgrep", "-c", "cloudflared"], capture_output=True, text=True)
    still = r2.stdout.strip()
    if still == "0":
        tg_send(f"🔴 <b>Cloudflare Tunnel หยุดแล้ว</b>\nหยุด {count} process(es)\nพิมพ์ /tunnel_restart เพื่อเปิดใหม่")
    else:
        tg_send(f"⚠️ ยังมี {still} process เหลืออยู่ — ลองอีกครั้ง")


def _cmd_tunnel_url() -> None:
    """แสดง Tunnel URLs ทั้งหมด (HTTP + SSH) กดลิงค์ได้"""
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}

    cf_url   = s.get("cf_url", "Not Found")
    cf_stat  = s.get("cf_status", {})
    online   = cf_stat.get("online", False)
    ping_ms  = cf_stat.get("ping_ms", 0)
    error    = cf_stat.get("error", "")
    cf_adv   = s.get("advanced_metrics", {}).get("cloudflared", {})
    http_url = cf_adv.get("http_url", cf_url)
    ssh_url  = cf_adv.get("ssh_url", "")
    http_ok  = cf_adv.get("http_online", online)
    ssh_ok   = cf_adv.get("ssh_online", False)

    ts  = time.strftime("%H:%M:%S")
    msg = f"🔗 <b>Tunnel URLs</b>  ({ts})\n━━━━━━━━━━━━━━━━━━━━\n"

    # HTTP/App URL
    h_icon = "🟢" if http_ok else "🔴"
    if http_url and http_url != "Not Found":
        msg += (
            f"\n{h_icon} <b>HTTP / Laravel App</b>"
            + (f"  ({ping_ms} ms)" if http_ok else f"  [{error}]") + "\n"
            f"<a href='{http_url}'>{http_url}</a>\n"
        )
    else:
        msg += f"\n🔴 <b>HTTP / Laravel App</b>\n⚠️ ไม่มี URL\n"

    # SSH Tunnel URL
    s_icon = "🟢" if ssh_ok else "🔴"
    if ssh_url:
        domain = ssh_url.replace("https://", "").replace("http://", "").strip("/")
        msg += (
            f"\n{s_icon} <b>SSH Tunnel</b>"
            + (" (Online)" if ssh_ok else " (Offline)") + "\n"
            f"<a href='{ssh_url}'>{ssh_url}</a>\n\n"
            f"💻 <b>คำสั่ง SSH ผ่าน Cloudflare:</b>\n"
            f"<code>ssh -o ProxyCommand='cloudflared access ssh "
            f"--hostname {domain}' u0_a175@{domain} -p 22</code>\n\n"
            f"🔑 <b>Direct SSH (LAN):</b>\n"
            f"<code>ssh -p 8022 u0_a175@192.168.1.222</code>"
        )
    else:
        msg += (
            f"\n⚪ <b>SSH Tunnel</b>\n"
            f"ไม่พบ URL (Tunnel :80 อาจไม่ได้รัน)\n\n"
            f"💡 <b>Direct SSH (LAN):</b>\n"
            f"<code>ssh -p 8022 u0_a175@192.168.1.222</code>"
        )

    msg += f"\n\n<i>URL เปลี่ยนทุกครั้งที่ restart tunnel</i>"
    tg_send(msg)


def _cmd_tunnel_log() -> None:
    """แสดง Cloudflare Tunnel log ล่าสุด"""
    import subprocess

    log_paths = [
        "/data/data/com.termux/files/home/cloudflared.log",
        "/data/data/com.termux/files/usr/var/log/sv/cloudflared/current",
    ]
    output = ""
    for path in log_paths:
        try:
            r = subprocess.run(["tail", "-20", path], capture_output=True, text=True)
            if r.stdout.strip():
                output = r.stdout.strip()
                break
        except Exception:
            pass

    if not output:
        tg_send("📋 ไม่พบ Cloudflare log")
        return

    # กรองเฉพาะ lines สำคัญ
    important = []
    for line in output.splitlines():
        if any(k in line.lower() for k in ["error", "fail", "url", "tunnel", "connect", "start", "trycloudflare"]):
            important.append(line[-120:])

    lines_to_show = important[-10:] if important else output.splitlines()[-10:]
    tg_send(
        f"📋 <b>Cloudflare Tunnel Log</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"<code>{'chr(10)'.join(lines_to_show)}</code>"
    )


def _cmd_tunnel_seturl() -> None:
    """แสดง URL พร้อม QR instructions"""
    cf_url = get_cf_url()
    tg_send(
        f"🌐 <b>Share Tunnel URL</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"URL ปัจจุบัน:\n<code>{cf_url}</code>\n\n"
        f"<b>คำสั่งที่เกี่ยวข้อง:</b>\n"
        f"  /tunnel_url          — ดู URLs ทั้งหมด + SSH command\n"
        f"  /tunnel_restart      — Restart ทั้ง HTTP + SSH\n"
        f"  /tunnel_restart_http — Restart เฉพาะ HTTP (:8080)\n"
        f"  /tunnel_restart_ssh  — Restart เฉพาะ SSH (:80)\n"
        f"  /tunnel_stop         — หยุด Tunnel\n"
        f"  /tunnel_log          — ดู log\n"
        f"  /tunnel              — สถานะทุกอย่าง"
    )


def _cmd_restart() -> None:
    """Restart services ที่ Stopped อยู่"""
    import subprocess
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    svcs = s.get("services", {})
    stopped = [name for name, st in svcs.items() if st == "Stopped"]

    if not stopped:
        tg_send("✅ ทุก service Running อยู่แล้ว ไม่ต้อง restart")
        return

    tg_send(f"🔄 กำลัง restart: {', '.join(stopped)}...")
    restarted = []
    failed    = []

    restart_cmds = {
        "Nginx"        : ["nginx", "-s", "reload"],
        "PHP-FPM"      : ["php-fpm", "--daemonize"],
        "Redis"        : ["redis-server",
                          "/data/data/com.termux/files/usr/etc/redis.conf",
                          "--daemonize", "yes"],
        "PostgreSQL"   : ["pg_ctl", "start", "-D",
                          "/data/data/com.termux/files/usr/var/lib/postgresql"],
        "Queue Worker" : None,
        "Reverb"       : None,
    }

    for svc in stopped:
        cmd = restart_cmds.get(svc)
        try:
            if cmd:
                subprocess.Popen(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                restarted.append(svc)
            elif svc in ("Queue Worker", "Reverb"):
                app = "/data/data/com.termux/files/home/uni-activity"
                artisan_cmd = "reverb:start --host=0.0.0.0 --port=8082" if svc == "Reverb" else "queue:work redis --sleep=3 --tries=3"
                subprocess.Popen(
                    f"cd {app} && nohup php artisan {artisan_cmd} > /dev/null 2>&1 &",
                    shell=True
                )
                restarted.append(svc)
            else:
                failed.append(svc)
        except Exception:
            failed.append(svc)

    msg = ""
    if restarted:
        msg += "✅ Restarted: " + ", ".join(restarted) + "\n"
    if failed:
        msg += "❌ Failed: " + ", ".join(failed)
    tg_send(msg or "✅ Done")


def _cmd_clear_cache() -> None:
    """Clear Laravel caches"""
    import subprocess
    app = "/data/data/com.termux/files/home/uni-activity"
    tg_send("🧹 กำลัง clear caches...")
    cmds = [
        ["php", "artisan", "config:clear"],
        ["php", "artisan", "cache:clear"],
        ["php", "artisan", "route:clear"],
        ["php", "artisan", "view:clear"],
    ]
    results = []
    for cmd in cmds:
        try:
            r = subprocess.run(cmd, cwd=app, capture_output=True, text=True, timeout=15)
            ok = "error" not in r.stdout.lower() and r.returncode == 0
            results.append(f"  {'✅' if ok else '❌'} {cmd[2]}")
        except Exception as e:
            results.append(f"  ❌ {cmd[2]}: {e}")

    tg_send("🧹 <b>Clear Cache Results</b>\n━━━━━━━━━━━━━━━━━━━━\n" + "\n".join(results))


def _cmd_force_report() -> None:
    """บังคับส่ง report ทันที"""
    global _tg_last_daily
    _tg_last_daily = 0  # reset timer
    with _stats_lock:
        s = _stats_cache.copy() if _stats_cache else {}
    if s:
        tg_daily_report(s)
    else:
        tg_send("⏳ กำลังรวบรวมข้อมูล รอสักครู่...")



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
    import urllib.parse, http.client, socket, ssl, time, subprocess, re

    # ── State tracking ────────────────────────────────────────────────────────
    _fail_count        = 0          # consecutive failures
    _last_restart_time = 0.0        # ป้องกัน restart loop
    _last_error_type   = ""         # DNS / TIMEOUT / HTTP_xxx / SSL / UNKNOWN
    # หมายเหตุ: ไม่ส่ง "Tunnel Recovered" — แจ้งเฉพาะตอนล่มเท่านั้น

    FAIL_THRESHOLD     = 3          # fail กี่ครั้งติดก่อน restart
    RESTART_COOLDOWN   = 120        # วินาที ระหว่าง auto-restart
    CHECK_INTERVAL     = 15         # วินาที ระหว่างการเช็ค

    def resolve_dns_udp(domain: str, dns_server: str = "8.8.8.8") -> str | None:
        """DNS lookup ผ่าน UDP โดยตรง — bypass Android DNS cache"""
        try:
            packet = bytearray([0x12,0x34,0x01,0x00,0x00,0x01,0x00,0x00,0x00,0x00,0x00,0x00])
            for part in domain.split("."):
                packet.append(len(part))
                packet.extend(part.encode("ascii"))
            packet.append(0)
            packet.extend([0x00,0x01,0x00,0x01])
            sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            sock.settimeout(3)
            sock.sendto(packet, (dns_server, 53))
            data, _ = sock.recvfrom(512)
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
                atype  = (data[idx]   << 8) + data[idx+1]
                rdlen  = (data[idx+8] << 8) + data[idx+9]
                idx   += 10
                if atype == 1 and rdlen == 4:
                    return ".".join(str(b) for b in data[idx:idx+4])
                idx += rdlen
        except Exception:
            pass
        return None

    def detect_error(exc: Exception, domain: str) -> str:
        """วิเคราะห์ exception → คืน error type + คำอธิบาย"""
        msg = str(exc).lower()
        # DNS failure
        if any(k in msg for k in ["name or service", "nodename", "gaierror", "dns", "resolve"]):
            return "DNS_FAIL"
        if resolve_dns_udp(domain) is None:
            return "DNS_FAIL"
        # Timeout
        if any(k in msg for k in ["timed out", "timeout", "time out"]):
            return "TIMEOUT"
        # SSL / TLS
        if any(k in msg for k in ["ssl", "certificate", "handshake", "tls"]):
            return "SSL_ERROR"
        # Connection refused
        if any(k in msg for k in ["refused", "connection refused", "111"]):
            return "CONN_REFUSED"
        # HTTP error codes
        for code in ["502", "503", "504", "521", "522", "523", "524", "530"]:
            if code in msg:
                return f"HTTP_{code}"
        return "UNKNOWN"

    def do_restart_tunnel() -> str | None:
        """Kill cloudflared → start ทั้ง 2 ใหม่ → รอ HTTP URL → return URL หรือ None"""
        try:
            subprocess.run(["pkill", "-9", "cloudflared"], capture_output=True)
            time.sleep(3)

            log_http = "/data/data/com.termux/files/home/cloudflared.log"
            log_ssh  = "/data/data/com.termux/files/home/cloudflared-ssh.log"

            for lp in [log_http, log_ssh]:
                with open(lp, "w") as f:
                    f.write("")

            # Tunnel 1 — HTTP :8080
            subprocess.Popen(
                f"nohup cloudflared tunnel --url http://127.0.0.1:8080 "
                f"--no-autoupdate > {log_http} 2>&1 &",
                shell=True,
            )
            time.sleep(2)

            # Tunnel 2 — SSH :80
            subprocess.Popen(
                f"nohup cloudflared tunnel --url http://127.0.0.1:80 "
                f"--no-autoupdate > {log_ssh} 2>&1 &",
                shell=True,
            )

            # รอ HTTP URL (สูงสุด 40 วิ)
            new_url = None
            ssh_url = None
            for _ in range(40):
                time.sleep(1)
                try:
                    if not new_url:
                        with open(log_http, "r") as f:
                            content = f.read()
                        m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                        if m:
                            new_url = m.group(0)
                except Exception:
                    pass
                try:
                    if not ssh_url:
                        with open(log_ssh, "r") as f:
                            content = f.read()
                        m = re.search(r"https://[a-zA-Z0-9-]+\.trycloudflare\.com", content)
                        if m:
                            ssh_url = m.group(0)
                except Exception:
                    pass
                if new_url and ssh_url:
                    break

            if new_url:
                # อัพเดต .env
                env_path = "/data/data/com.termux/files/home/uni-activity/.env"
                if os.path.exists(env_path):
                    with open(env_path, "r") as f:
                        env_lines = f.readlines()
                    with open(env_path, "w") as f:
                        for line in env_lines:
                            f.write(f"APP_URL={new_url}\n" if line.startswith("APP_URL=") else line)

                # อัพเดต active_url.json
                json_path = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
                os.makedirs(os.path.dirname(json_path), exist_ok=True)
                with open(json_path, "w") as f:
                    json.dump({
                        "url"        : new_url,
                        "ssh_url"    : ssh_url or "",
                        "updated_at" : time.strftime("%Y-%m-%d %H:%M:%S"),
                    }, f)

            return new_url
        except Exception:
            return None

    # ── Main loop ─────────────────────────────────────────────────────────────
    while True:
        time.sleep(2)
        url = get_cf_url()

        # ไม่มี URL หรือเป็น local address
        if not url or url == "Not Found" or any(
            loc in url for loc in ["localhost", "127.0.0.1", "192.168."]
        ):
            url_status["online"]     = False
            url_status["ping_ms"]    = 0
            url_status["error"]      = "NO_URL"
            url_status["url"]        = url or ""
            time.sleep(CHECK_INTERVAL)
            continue

        url_status["url"] = url
        parsed = urllib.parse.urlparse(url)
        domain = parsed.netloc
        error_type = ""

        try:
            t0 = time.time()

            # 1. DNS via UDP
            ip = resolve_dns_udp(domain)
            if not ip:
                raise Exception(f"DNS_FAIL: cannot resolve {domain}")

            # 2. HTTP HEAD request
            if parsed.scheme == "https":
                ctx  = ssl._create_unverified_context()
                conn = http.client.HTTPSConnection(ip, timeout=5, context=ctx)
            else:
                conn = http.client.HTTPConnection(ip, timeout=5)

            conn.request("HEAD", "/", headers={"Host": domain, "User-Agent": "UniMonitor/2.0"})
            resp = conn.getresponse()

            # HTTP 5xx / Cloudflare error codes = ถือว่า tunnel พัง
            if resp.status in (502, 503, 521, 522, 523, 524, 530):
                raise Exception(f"HTTP_{resp.status}")

            ping_ms = int((time.time() - t0) * 1000)
            url_status.update({"online": True, "ping_ms": ping_ms, "error": "", "url": url})

            _fail_count      = 0
            error_type       = ""
            # ไม่ส่ง Recovered — แจ้งเฉพาะตอนล่มเท่านั้น

        except Exception as exc:
            error_type = detect_error(exc, domain)
            url_status.update({"online": False, "ping_ms": 0, "error": error_type, "url": url})
            _fail_count    += 1
            _consecutive_ok = 0

            # ──────────────────────────────────────────────────────────────────
            # Auto-restart เมื่อ fail ถึง threshold และผ่าน cooldown
            # ──────────────────────────────────────────────────────────────────
            if _fail_count >= FAIL_THRESHOLD and (time.time() - _last_restart_time) > RESTART_COOLDOWN:
                _last_restart_time = time.time()
                ts = time.strftime("%H:%M:%S")

                # แจ้งก่อน restart
                error_desc = {
                    "DNS_FAIL"    : "🔴 DNS ไม่สามารถ resolve ได้ (อาจ Tunnel ตาย)",
                    "TIMEOUT"     : "⏱ Connection Timeout",
                    "SSL_ERROR"   : "🔒 SSL/TLS Error",
                    "CONN_REFUSED": "🚫 Connection Refused",
                    "HTTP_502"    : "💥 HTTP 502 Bad Gateway",
                    "HTTP_503"    : "🔧 HTTP 503 Service Unavailable",
                    "HTTP_521"    : "☁️ HTTP 521 Web Server Down",
                    "HTTP_522"    : "⏰ HTTP 522 Connection Timed Out",
                    "HTTP_523"    : "🔌 HTTP 523 Origin Unreachable",
                    "HTTP_524"    : "⌛ HTTP 524 A Timeout Occurred",
                    "HTTP_530"    : "🌐 HTTP 530 Cloudflare Error",
                }.get(error_type, f"❓ {error_type}")

                tg_send(
                    f"🚨 <b>Tunnel Failure Detected!</b>\n"
                    f"━━━━━━━━━━━━━━━━━━━━\n"
                    f"❌ Error  : {error_desc}\n"
                    f"🔗 URL   : <a href='{url}'>{url}</a>\n"
                    f"🔢 Fails : {_fail_count} consecutive\n"
                    f"🕐 Time  : {ts}\n\n"
                    f"🔄 กำลัง Auto-restart Tunnel..."
                )

                # restart ใน background thread
                def _auto_restart(old_url=url, err=error_type):
                    new_url = do_restart_tunnel()
                    ts2 = time.strftime("%H:%M:%S")

                    # อ่าน SSH URL จาก active_url.json
                    new_ssh = ""
                    try:
                        jp = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
                        with open(jp, "r") as f:
                            new_ssh = json.load(f).get("ssh_url", "")
                    except Exception:
                        pass

                    if new_url:
                        h_line = f"<a href='{new_url}'>{new_url}</a>"
                        s_line = (
                            f"\n🔐 SSH URL:\n<a href='{new_ssh}'>{new_ssh}</a>"
                            if new_ssh else ""
                        )
                        tg_send(
                            f"✅ <b>Auto-Restart สำเร็จ!</b>\n"
                            f"━━━━━━━━━━━━━━━━━━━━\n"
                            f"🌐 HTTP URL:\n{h_line}"
                            f"{s_line}\n"
                            f"📝 .env อัพเดตแล้ว\n"
                            f"🕐 {ts2}"
                        )
                    else:
                        tg_send(
                            f"❌ <b>Auto-Restart ล้มเหลว!</b>\n"
                            f"━━━━━━━━━━━━━━━━━━━━\n"
                            f"ไม่ได้รับ URL ใหม่จาก Cloudflare\n"
                            f"🕐 {ts2}\n"
                            f"💡 ลองพิมพ์ /tunnel_restart"
                        )

                threading.Thread(target=_auto_restart, daemon=True).start()
                _fail_count = 0   # reset หลัง trigger restart

        time.sleep(CHECK_INTERVAL)

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

    _services_cache = status
    _services_cache_time = _time.time()
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
    # ── กรอง: ไม่ alert ถ้า url_status ยังไม่มีข้อมูล หรืออยู่ในช่วง startup grace ──
    cf_st = stats.get("cf_status", {})
    cf_online  = cf_st.get("online", True)   # default True เพื่อไม่ false-alert ตอนเริ่ม
    cf_has_url = bool(cf_st.get("url", ""))  # ต้องมี URL ก่อนจึงจะ alert ได้
    in_grace   = (time.time() - _monitor_start_time) < STARTUP_GRACE
    if not cf_online and cf_has_url and not in_grace:
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
            
    # Track history + Telegram alerts
    current_ids = set()
    from datetime import datetime
    for a in alerts:
        current_ids.add(a["id"])
        if a["id"] not in active_alert_ids:
            # บันทึก history
            history_item = a.copy()
            history_item["time"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            alerts_history.appendleft(history_item)
            # ส่ง Telegram เฉพาะ alert ใหม่
            tg_alert(a["id"], a["type"], a["message"])

    # แจ้ง resolved เมื่อ alert หายไป
    for resolved_id in (active_alert_ids - current_ids):
        _tg_resolved.discard(resolved_id)   # reset เพื่อให้ส่งได้อีกถ้าเกิดซ้ำ
        resolved_msg = {
            "cf_offline"    : "Cloudflare Tunnel กลับมา Online แล้ว",
            "service_crash" : "Services กลับมา Running แล้ว",
            "high_load"     : "CPU Load กลับสู่ระดับปกติแล้ว",
            "high_temp"     : "อุณหภูมิ Server กลับสู่ระดับปกติแล้ว",
            "high_mem"      : "Memory Usage กลับสู่ระดับปกติแล้ว",
            "high_disk"     : "Disk Space กลับสู่ระดับปกติแล้ว",
        }.get(resolved_id, f"{resolved_id} resolved")
        tg_resolved(resolved_id, resolved_msg)

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
        "github_deploy_logs": get_github_sync_logs_dict(),
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
        },
        "public_ip": CACHED_PUBLIC_IP
    }
    stats["alerts"] = get_alerts(stats)
    stats["alerts_history"] = list(alerts_history)
    return stats

CACHED_PUBLIC_IP = ""

def fetch_public_ip_loop():
    global CACHED_PUBLIC_IP
    import urllib.request
    while True:
        try:
            req = urllib.request.Request("https://api.ipify.org", headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=10) as response:
                ip = response.read().decode('utf-8').strip()
                if ip:
                    CACHED_PUBLIC_IP = ip
        except Exception:
            pass
        import time
        time.sleep(600)  # every 10 mins

threading.Thread(target=fetch_public_ip_loop, daemon=True).start()

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
            import time
            time.sleep(1)

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
    global _stats_cache
    while True:
        try:
            data = collect_stats()
            with _stats_lock:
                _stats_cache = data
            # Daily report ทุก 24 ชั่วโมง
            tg_daily_report(data)
        except Exception:
            pass
        time.sleep(5)   # ← collect ทุก 5 วินาที แทนทุก 2 วินาที

def ws_client_thread(conn):
    """Push cached stats to one WS client every 5 s — zero extra subprocess calls."""
    try:
        while True:
            with _stats_lock:
                snapshot = _stats_cache.copy() if _stats_cache else {}
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
    
    t_auto_sync = threading.Thread(target=auto_sync_thread, daemon=True)
    t_auto_sync.start()

    # ── Background stats collector (แทนการ collect ใน ws_client_thread) ──
    t_stats = threading.Thread(target=stats_collector_thread, daemon=True)
    t_stats.start()

    # ── Telegram command poll ─────────────────────────────────────────────
    t_tg_cmd = threading.Thread(target=tg_command_poll_thread, daemon=True)
    t_tg_cmd.start()

    server = ThreadingHTTPServer(("", PORT), MonitorHandler)
    server.allow_reuse_address = True
    print(f"[Monitor] Serving at http://0.0.0.0:{PORT}")

    # ── Telegram startup notification ────────────────────────
    def _send_startup():
        time.sleep(5)  # รอให้ services เริ่มก่อน
        ts = time.strftime("%Y-%m-%d %H:%M:%S")
        tg_send(
            f"🟢 <b>Monitor Server Started</b>\n"
            f"━━━━━━━━━━━━━━━━━━━━\n"
            f"🕐 {ts}\n"
            f"🌐 Port: {PORT}\n"
            f"📡 Alerts: <b>Active</b>\n\n"
            f"<i>จะแจ้งเตือนเมื่อ:</i>\n"
            f"• 🚨 Service down\n"
            f"• ⚠️ CPU load &gt; 6.0\n"
            f"• ⚠️ Memory &gt; 90%\n"
            f"• ⚠️ Disk &gt; 90%\n"
            f"• ⚠️ Temp &gt; 75°C\n"
            f"• ⚠️ Traffic spike\n"
            f"• 🌐 Cloudflare offline\n"
            f"• 📊 Daily report ทุก 24 ชม."
        )
    threading.Thread(target=_send_startup, daemon=True).start()

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        tg_send("🔴 <b>Monitor Server Stopped</b>\n🕐 " + time.strftime("%Y-%m-%d %H:%M:%S"))
        print("Shutting down.")
