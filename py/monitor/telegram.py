"""
monitor/telegram.py — Resilient queue-based Telegram messaging with auto-retry and rate limiting.
"""
import time
import threading
import queue
import urllib.request
import urllib.error
import json as _json
import re
from collections import deque

import monitor.config as cfg

# ── Message Queue & Worker ───────────────────────────────────────────────────
_tg_queue: queue.Queue = queue.Queue(maxsize=500)
_worker_started = False
_worker_lock = threading.Lock()


def _strip_html_tags(text: str) -> str:
    """Fallback: strip HTML tags if Telegram rejects invalid markup."""
    clean = re.sub(r"<[^>]+>", "", text)
    return clean.replace("&amp;", "&").replace("&lt;", "<").replace("&gt;", ">").replace("&quot;", '"')


def _send_http(text: str, parse_mode: str | None = "HTML") -> bool:
    """Send a single message via Telegram Bot API with retry on 429 and fallback on 400."""
    if not cfg.TELEGRAM_BOT_TOKEN or not cfg.TELEGRAM_CHAT_ID:
        return False

    url = f"https://api.telegram.org/bot{cfg.TELEGRAM_BOT_TOKEN}/sendMessage"

    for attempt in range(3):
        try:
            body = {
                "chat_id": cfg.TELEGRAM_CHAT_ID,
                "text": text,
                "disable_web_page_preview": True,
            }
            if parse_mode:
                body["parse_mode"] = parse_mode

            payload = _json.dumps(body).encode("utf-8")
            req = urllib.request.Request(
                url,
                data=payload,
                headers={"Content-Type": "application/json"},
                method="POST",
            )
            with urllib.request.urlopen(req, timeout=12) as r:
                return r.status == 200

        except urllib.error.HTTPError as e:
            # 1. Rate limited (429) -> wait and retry
            if e.code == 429:
                try:
                    err_data = _json.loads(e.read().decode("utf-8", "ignore"))
                    wait_sec = err_data.get("parameters", {}).get("retry_after", 2)
                    time.sleep(wait_sec + 0.5)
                except Exception:
                    time.sleep(2)
                continue

            # 2. Bad request (400) -> HTML parse error -> fallback to plain text
            elif e.code == 400 and parse_mode == "HTML":
                text = _strip_html_tags(text)
                parse_mode = None
                continue
            else:
                break

        except Exception:
            # Network hiccup or timeout -> backoff and retry
            time.sleep(1.0 * (attempt + 1))

    return False


def _send_worker_loop():
    """Background worker that drains _tg_queue with pacing (0.25s) to avoid 429 rate limits."""
    while True:
        try:
            item = _tg_queue.get()
            if item is None:
                break
            text, parse_mode = item
            _send_http(text, parse_mode)
            _tg_queue.task_done()
            time.sleep(0.25)  # Pacing: max ~4 messages/sec (well within Telegram 30/sec limit)
        except Exception:
            time.sleep(0.5)


def _ensure_worker():
    global _worker_started
    if not _worker_started:
        with _worker_lock:
            if not _worker_started:
                t = threading.Thread(target=_send_worker_loop, daemon=True, name="TelegramSendWorker")
                t.start()
                _worker_started = True


# Start worker immediately
_ensure_worker()


def tg_send(text: str, parse_mode: str = "HTML") -> bool:
    """Non-blocking: Enqueue message for reliable background delivery."""
    if not cfg.TELEGRAM_BOT_TOKEN or not cfg.TELEGRAM_CHAT_ID:
        return False
    _ensure_worker()
    try:
        _tg_queue.put_nowait((text, parse_mode))
        return True
    except queue.Full:
        # If queue is full, drop oldest and insert
        try:
            _tg_queue.get_nowait()
        except Exception:
            pass
        _tg_queue.put((text, parse_mode))
        return True


# ── Global burst limiter (circuit breaker สำหรับ alert/resolved) ─────────────
_tg_burst_times: deque = deque()


def _alert_burst_allow() -> bool:
    """Allow at most TG_ALERT_BURST_LIMIT alert/resolved messages per window.

    Interactive command replies (tg_send) are NOT limited — only automatic
    alerts, so a burst of simultaneous incidents can never flood the chat.
    """
    now = time.time()
    while _tg_burst_times and now - _tg_burst_times[0] > cfg.TG_ALERT_BURST_WINDOW:
        _tg_burst_times.popleft()
    if len(_tg_burst_times) >= cfg.TG_ALERT_BURST_LIMIT:
        return False
    _tg_burst_times.append(now)
    return True


def tg_alert(alert_id: str, alert_type: str, message: str) -> None:
    """Send alert with cooldown and deduplication."""
    # Circuit breaker: drop excess alert messages during an incident storm
    if not _alert_burst_allow():
        return

    now = time.time()

    # Startup grace: do not send cf_offline during first 90s
    if alert_id == "cf_offline" and (now - cfg._monitor_start_time) < cfg.STARTUP_GRACE:
        return

    # Cooldown per alert_id
    last_sent = cfg._tg_alert_cooldown.get(alert_id, 0.0)
    if now - last_sent < cfg.ALERT_MIN_INTERVAL:
        return
    cfg._tg_alert_cooldown[alert_id] = now

    # Bucket dedup
    cache_key = f"{alert_id}:{int(now // cfg.ALERT_MIN_INTERVAL)}"
    if cache_key in cfg._tg_sent_ids:
        return
    cfg._tg_sent_ids.add(cache_key)

    if len(cfg._tg_sent_ids) > 500:
        current_bucket = int(now // cfg.ALERT_MIN_INTERVAL)
        expired = {k for k in cfg._tg_sent_ids if ":" in k and int(k.rsplit(":", 1)[1]) < current_bucket - 1}
        cfg._tg_sent_ids -= expired
        if len(cfg._tg_sent_ids) > 500:
            cfg._tg_sent_ids.clear()

    icon = "🚨" if alert_type == "critical" else "⚠️"
    ts   = time.strftime("%H:%M:%S")
    text = (
        f"{icon} <b>UniActivity Server Alert</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"📋 <b>{message}</b>\n"
        f"🕐 {ts}\n"
        f"🆔 <code>{alert_id}</code>"
    )
    tg_send(text)


def tg_resolved(alert_id: str, message: str) -> None:
    """Notify alert resolved (with cooldown to prevent flap spam)."""
    # Circuit breaker (shared with tg_alert)
    if not _alert_burst_allow():
        return

    if alert_id in cfg._tg_resolved:
        return

    # Resolved cooldown: ไม่ส่ง resolved ซ้ำภายใน ALERT_RESOLVED_MIN_INTERVAL
    # กันกรณี alert flap (เกิด→หาย→เกิด) จาก threshold oscillation
    now = time.time()
    last = cfg._tg_resolved_cooldown.get(alert_id, 0.0)
    if now - last < cfg.ALERT_RESOLVED_MIN_INTERVAL:
        return
    cfg._tg_resolved_cooldown[alert_id] = now

    cfg._tg_resolved.add(alert_id)
    ts   = time.strftime("%H:%M:%S")
    text = (
        f"✅ <b>Resolved</b>\n"
        f"━━━━━━━━━━━━━━━━━━━━\n"
        f"📋 {message}\n"
        f"🕐 {ts}"
    )
    tg_send(text)


def tg_daily_report(stats: dict) -> None:
    """Daily summary report every 24 hours."""
    if time.time() - cfg._tg_last_daily < 86400:
        return
    cfg._tg_last_daily = time.time()
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
    tg_send(text)
