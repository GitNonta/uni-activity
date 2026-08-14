"""
monitor/telegram.py — Telegram send/alert/resolved/daily-report helpers.
"""
import time
import threading
from monitor.config import (
    TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID,
    ALERT_MIN_INTERVAL, STARTUP_GRACE,
    _tg_sent_ids, _tg_resolved, _tg_alert_cooldown, _monitor_start_time,
)
import monitor.config as cfg


def tg_send(text: str, parse_mode: str = "HTML") -> bool:
    """ส่งข้อความไป Telegram — return True ถ้าสำเร็จ"""
    if not cfg.TELEGRAM_BOT_TOKEN or not cfg.TELEGRAM_CHAT_ID:
        return False
    try:
        import urllib.request, json as _json
        payload = _json.dumps({
            "chat_id": cfg.TELEGRAM_CHAT_ID,
            "text": text,
            "parse_mode": parse_mode,
            "disable_web_page_preview": True,
        }).encode()
        req = urllib.request.Request(
            f"https://api.telegram.org/bot{cfg.TELEGRAM_BOT_TOKEN}/sendMessage",
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

    # Startup grace: ไม่ส่ง cf_offline ในช่วง 90 วิแรก
    if alert_id == "cf_offline" and (now - cfg._monitor_start_time) < cfg.STARTUP_GRACE:
        return

    # Cooldown ต่อ alert_id
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
    threading.Thread(target=tg_send, args=(text,), daemon=True).start()


def tg_resolved(alert_id: str, message: str) -> None:
    """แจ้งว่า alert หายแล้ว (ส่งแค่ครั้งเดียว)"""
    if alert_id in cfg._tg_resolved:
        return
    cfg._tg_resolved.add(alert_id)
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
    threading.Thread(target=tg_send, args=(text,), daemon=True).start()
