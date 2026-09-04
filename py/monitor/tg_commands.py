"""
monitor/tg_commands.py — Telegram bot command handlers (_cmd_* functions).
"""
import time, json, threading, subprocess, re, os, html
import monitor.config as cfg
from monitor.telegram import tg_send, tg_daily_report
from monitor.collectors import get_cf_url
from monitor.tunnel import push_active_url_to_github

# ── Telegram Bot Command Handler ─────────────────────────────────────────────
_tg_last_update_id: int = 0
_cmd_locks: dict        = {}   # cmd -> Lock — กันรันคำสั่งเดิมซ้ำพร้อมกัน (double-tap)
_unauth_warn_at: dict   = {}   # chat -> epoch — throttle แจ้งเตือน unauthorized chat
_handler_sem = threading.BoundedSemaphore(8)  # จำกัด handler ทำงานพร้อมกัน

def tg_handle_commands() -> None:
    """Long-poll Telegram getUpdates — block 25 วิ ตอบสนองทันทีแบบ Real-time เมื่อมี update.

    Raises on network/API errors so the poll loop can apply backoff
    (ก่อนหน้านี้ error ถูกกลืน → loop ยิง API ถี่ผิดปกติตอน net หลุด).
    """
    global _tg_last_update_id
    if not cfg.TELEGRAM_BOT_TOKEN or not cfg.TELEGRAM_CHAT_ID:
        time.sleep(60)
        return

    try:
        import urllib.request, urllib.error, json as _json
        url = (
            f"https://api.telegram.org/bot{cfg.TELEGRAM_BOT_TOKEN}/getUpdates"
            f"?offset={_tg_last_update_id + 1}&limit=10&timeout=25"
        )
        req = urllib.request.Request(url, headers={"User-Agent": "UniMonitor/2.0"})
        with urllib.request.urlopen(req, timeout=35) as r:
            data = _json.loads(r.read())

        for update in data.get("result", []):
            _tg_last_update_id = update["update_id"]
            msg  = update.get("message", {})
            chat = str(msg.get("chat", {}).get("id", ""))
            text = msg.get("text", "").strip()

            # ── ข้ามคำสั่งเก่า (ค้างจากก่อน restart) — ไม่งั้น bot จะ replay
            #    คำสั่งเก่าหลายชม.ก่อนหน้าเองทันทีที่ start ใหม่
            msg_age = time.time() - msg.get("date", 0)
            if msg_age > cfg.TG_CMD_MAX_AGE or msg_age < -5:
                continue

            if chat != cfg.TELEGRAM_CHAT_ID:
                # แจ้ง owner แบบ throttled — stranger ยิง message รัวๆ ไม่ให้กลายเป็นสแปม
                now = time.time()
                if now - _unauth_warn_at.get(chat, 0.0) > 60.0:
                    _unauth_warn_at[chat] = now
                    tg_send(f"⛔ Unauthorized: <code>{html.escape(chat)}</code>")
                continue

            # dispatch ใน thread แยก — ไม่บล็อก poll loop
            threading.Thread(target=_dispatch_command, args=(text,), daemon=True).start()

    except urllib.error.HTTPError as e:
        if e.code == 429:   # rate limited — respect retry_after
            try:
                wait = _json_loads(e.read().decode("utf-8", "ignore")).get(
                    "parameters", {}).get("retry_after", 5)
                time.sleep(min(wait + 1, cfg.TG_POLL_BACKOFF_MAX))
            except Exception:
                time.sleep(5)
        raise


def _json_loads(s: str) -> dict:
    import json as _json
    try:
        return _json.loads(s)
    except Exception:
        return {}


def tg_command_poll_thread() -> None:
    """Long-poll loop with exponential backoff on errors (1s → 30s cap).

    กัน busy-loop ยิง Telegram API ไม่ยั้งตอน network/API มีปัญหา.
    """
    backoff = 1.0
    while True:
        try:
            tg_handle_commands()
            backoff = 1.0
        except Exception:
            time.sleep(backoff)
            backoff = min(backoff * 2, float(cfg.TG_POLL_BACKOFF_MAX))


def _get_cmd_lock(cmd: str) -> threading.Lock:
    lock = _cmd_locks.get(cmd)
    if lock is None:
        lock = threading.Lock()
        _cmd_locks[cmd] = lock
    return lock


def _run_handler(cmd: str, fn) -> None:
    """Run one handler with duplicate-guard + concurrency cap + error reporting.

    - Lock per command: double-tap /tunnel_restart ไม่แข่ง kill/start กันเอง
    - Semaphore: command flood ไม่ spawn thread ล้นระบบ
    - try/except: handler crash ต้องแจ้ง user กลับ (เดิมเงียบ = ดูเหมือน bot พัง)
    """
    lock = _get_cmd_lock(cmd)
    if not lock.acquire(blocking=False):
        tg_send(f"⏳ <code>{html.escape(cmd)}</code> กำลังทำงานอยู่ — รอผลลัพธ์ก่อนรันซ้ำ")
        return
    try:
        if not _handler_sem.acquire(timeout=10):
            tg_send("⚠️ มีคำสั่งรอประมวลผลเยอะ — ลองใหม่อีกครั้งสักครู่")
            return
        try:
            fn()
        except Exception as e:
            tg_send(f"❌ <code>{html.escape(cmd)}</code> failed: "
                    f"<code>{html.escape(str(e)[:300])}</code>")
        finally:
            _handler_sem.release()
    finally:
        lock.release()


def _dispatch_command(text: str) -> None:
    """เรียก handler ตาม command ที่ได้รับ"""
    parts = text.split() if text else []
    if not parts:
        return
    cmd = parts[0].lower().split("@")[0]

    if cmd == "/proxy_test":
        target = parts[1] if len(parts) > 1 else "https://github.com"
        _run_handler(cmd, lambda: _cmd_proxy_test(target))
        return
    if cmd == "/proxy_block":
        if len(parts) < 2:
            tg_send("⚠️ ระบุโดเมนหรือ IP ที่ต้องการบล็อค เช่น: <code>/proxy_block tiktok.com</code>")
            return
        target = parts[1]
        reason = " ".join(parts[2:]) if len(parts) > 2 else "Block via Telegram"
        _run_handler(cmd, lambda: _cmd_proxy_block(target, reason))
        return
    if cmd == "/proxy_unblock":
        if len(parts) < 2:
            tg_send("⚠️ ระบุโดเมนหรือ IP ที่ต้องการปลดบล็อค เช่น: <code>/proxy_unblock tiktok.com</code>")
            return
        target = parts[1]
        _run_handler(cmd, lambda: _cmd_proxy_unblock(target))
        return

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
        # ── Proxy Traffic ──────────────────────────────
        "/proxy"               : _cmd_proxy,
        "/proxy_traffic"       : _cmd_proxy,
    }

    fn = handlers.get(cmd)
    if fn:
        # รันใน thread ของ dispatch เดียวกัน — _run_handler จัดการ lock/semaphore/error
        _run_handler(cmd, fn)
    elif cmd.startswith("/"):
        tg_send(f"❓ ไม่รู้จัก command: <code>{html.escape(cmd)}</code>\nพิมพ์ /help เพื่อดูคำสั่งทั้งหมด")


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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}

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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
    load  = s.get("load", [0, 0, 0])
    procs = s.get("advanced_metrics", {}).get("top_procs", [])
    icon  = "🔴" if load[0] > 8 else "🟡" if load[0] > 5 else "🟢"
    lines = [f"  {p['cpu']:>5}%  {html.escape(str(p['name']))}" for p in procs[:5]]
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
    alrts   = s.get("alerts", [])
    history = list(cfg.alerts_history)[:5]

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
            tg_send(f"📝 <b>Laravel Errors (ล่าสุด)</b>\n━━━━━━━━━━━━━━━━━━━━\n<code>{html.escape(out[:1000])}</code>")
        else:
            last = "\n".join(lines[-5:]) if lines else "ว่าง"
            tg_send(f"📝 <b>Laravel Log</b>\n━━━━━━━━━━━━━━━━━━━━\n✅ ไม่มี Errors\n<code>{html.escape(last[:500])}</code>")
    except Exception as e:
        tg_send(f"❌ อ่าน log ไม่ได้: {html.escape(str(e))}")


def _cmd_ports() -> None:
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
    procs = s.get("advanced_metrics", {}).get("top_procs", [])
    if not procs:
        tg_send("⏳ ยังไม่มีข้อมูล processes")
        return
    lines = [
        f"  {p['cpu']:>5}% CPU  {p['mem']:>4}% MEM  {html.escape(str(p['name'])[:30])}"
        for p in procs[:8]
    ]
    tg_send(
        f"🔝 <b>Top Processes</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        + "\n".join(lines)
    )


def _cmd_redis() -> None:
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
    pg = s.get("advanced_metrics", {}).get("postgres", {})
    tg_send(
        f"🐘 <b>PostgreSQL Stats</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"  Connections : {pg.get('connections',0)}\n"
        f"  DB Size     : {pg.get('db_size','N/A')}"
    )


def _cmd_network() -> None:
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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

    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}

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
        r = subprocess.run(["pgrep", "-a", "cloudflared"], capture_output=True,
                           text=True, timeout=5)
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
            r = subprocess.run(["tail", "-3", lp], capture_output=True, text=True,
                               timeout=5)
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
        etype = cfg.url_status.get("error", "UNKNOWN")
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

            if http_url:
                threading.Thread(target=push_active_url_to_github, args=(http_url, ssh_url or ""), daemon=True).start()

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

                threading.Thread(target=push_active_url_to_github, args=(http_url, old_data.get("ssh_url", "")), daemon=True).start()

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
                                   capture_output=True, text=True, timeout=5
                                   ).stdout.strip().splitlines()
            for line in procs:
                if ":80" in line and "8080" not in line and "8082" not in line:
                    pid = line.split()[0]
                    subprocess.run(["kill", "-9", pid], capture_output=True,
                                   timeout=5)
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

                threading.Thread(target=push_active_url_to_github, args=(old_data.get("url", ""), ssh_url), daemon=True).start()

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

    r = subprocess.run(["pgrep", "-c", "cloudflared"], capture_output=True,
                       text=True, timeout=5)
    count = r.stdout.strip()
    if count == "0":
        tg_send("ℹ️ ไม่มี Cloudflare Tunnel รันอยู่")
        return

    subprocess.run(["pkill", "-9", "cloudflared"], capture_output=True, timeout=5)
    time.sleep(1)

    # ยืนยัน
    r2 = subprocess.run(["pgrep", "-c", "cloudflared"], capture_output=True,
                        text=True, timeout=5)
    still = r2.stdout.strip()
    if still == "0":
        tg_send(f"🔴 <b>Cloudflare Tunnel หยุดแล้ว</b>\nหยุด {count} process(es)\nพิมพ์ /tunnel_restart เพื่อเปิดใหม่")
    else:
        tg_send(f"⚠️ ยังมี {still} process เหลืออยู่ — ลองอีกครั้ง")


def _cmd_tunnel_url() -> None:
    """แสดง Tunnel URLs ทั้งหมด (HTTP + SSH) กดลิงค์ได้"""
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}

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
            r = subprocess.run(["tail", "-20", path], capture_output=True,
                               text=True, timeout=5)
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
    log_text = html.escape("\n".join(lines_to_show))
    tg_send(
        f"📋 <b>Cloudflare Tunnel Log</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"<code>{log_text}</code>"
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
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
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
        "Redis"        : ["valkey-server",
                          "--port", "6379", "--bind", "0.0.0.0",
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
    cfg._tg_last_daily = 0  # reset timer
    with cfg._stats_lock:
        s = cfg._stats_cache.copy() if cfg._stats_cache else {}
    if s:
        tg_daily_report(s)
    else:
        tg_send("⏳ กำลังรวบรวมข้อมูล รอสักครู่...")


def _cmd_proxy() -> None:
    """แสดงสถานะและทราฟฟิก Proxy ปัจจุบัน"""
    from monitor.collectors import get_proxy_status
    p = get_proxy_status()
    sq = p.get('squid', {})
    s5 = p.get('socks5', {})
    tr = p.get('traffic', {})
    recent = p.get('recent_traffic', [])[:6]
    devices = p.get('device_breakdown', [])

    lines = [
        "🌐 <b>Proxy System Status & Traffic</b>",
        "━━━━━━━━━━━━━━━━━━━━",
        f"🦑 <b>Squid HTTP (:3128):</b> {sq.get('status', 'Stopped')} ({sq.get('connections', 0)} active conns)",
        f"🧦 <b>SOCKS5 (:1080):</b> {s5.get('status', 'Stopped')} ({s5.get('connections', 0)} active conns)",
        f"⚡ <b>RPS:</b> {tr.get('rps', 0)} req/s | <b>Cache Hit:</b> {tr.get('cache_hit_ratio', 0)}%",
        f"📦 <b>Total Traffic:</b> {tr.get('total_bytes_human', '0 KB')}",
        "",
        "📱 <b>Devices on Proxy:</b>",
    ]

    for d in devices[:4]:
        st_dot = "🟢" if d.get('active') else "⚪"
        lines.append(f"  {st_dot} {d.get('device')}: <code>{d.get('ip')}</code> — {d.get('requests')} reqs ({d.get('bytes_human')})")

    if recent:
        lines.append("")
        lines.append("🕒 <b>Recent Proxy Requests:</b>")
        for r in recent:
            st = "✅" if r.get('status_type') in ('tunnel', 'cached', 'success') else ("⏳" if r.get('status_type') == 'slow' else "❌")
            dest = html.escape(r.get('destination', ''))[:28]
            lines.append(f"  {st} <code>{r.get('time')}</code> <b>{r.get('device')}</b> → <code>{dest}</code> ({r.get('bytes_human')})")

    lines.append("\n💡 <i>ดูแบบ Real-time Dashboard ได้ที่แท็บ Proxy Management (:9999/#proxy)</i>")
    tg_send("\n".join(lines))


def _cmd_proxy_test(target: str) -> None:
    """ทดสอบการเข้าถึงเว็บไซต์ผ่าน Proxy และ Direct"""
    from monitor.proxy_manager import test_all_proxies
    tg_send(f"🔍 <b>กำลังทดสอบการเข้าถึง:</b> <code>{html.escape(target)}</code>\nกรุณารอสักครู่ (ยิงผ่าน Squid, SOCKS5, และ Direct)...")
    res = test_all_proxies(target, timeout=5)
    r = res.get("results", {})

    sq = r.get("squid", {})
    s5 = r.get("socks5", {})
    dr = r.get("direct", {})

    def format_ch(title, ch):
        icon = "✅" if ch.get("result") == "success" else ("🚫" if ch.get("result") == "blocked" else "❌")
        return f"{icon} <b>{title}:</b> {ch.get('status_text')} ({ch.get('latency_ms', 0)}ms)"

    msg = [
        "🎯 <b>Proxy Reachability Test Result</b>",
        "━━━━━━━━━━━━━━━━━━━━",
        f"🌐 <b>Target:</b> <code>{html.escape(target)}</code>",
        "",
        format_ch("Squid HTTP (:3128)", sq),
        format_ch("SOCKS5 (:1080)", s5),
        format_ch("Direct Connection", dr),
    ]
    tg_send("\n".join(msg))


def _cmd_proxy_block(target: str, reason: str) -> None:
    """สั่งบล็อคโดเมนหรือ IP"""
    from monitor.proxy_manager import add_block_target
    ok, res = add_block_target(target, reason=reason)
    if ok:
        tg_send(f"🚫 <b>บล็อคสำเร็จ:</b> <code>{html.escape(target)}</code>\nนโยบายมีผลทันทีบนทั้ง Squid และ SOCKS5")
    else:
        tg_send(f"⚠️ ไม่สามารถบล็อคได้: {html.escape(str(res))}")


def _cmd_proxy_unblock(target: str) -> None:
    """สั่งปลดบล็อคโดเมนหรือ IP"""
    from monitor.proxy_manager import remove_block_target
    ok, res = remove_block_target(target)
    if ok:
        tg_send(f"✅ <b>ปลดบล็อคสำเร็จ:</b> <code>{html.escape(target)}</code>\nทั้ง Squid และ SOCKS5 กลับมาอนุญาตการเข้าถึงแล้ว")
    else:
        tg_send(f"⚠️ ไม่สามารถปลดบล็อคได้: {html.escape(str(res))}")



