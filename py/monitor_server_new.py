"""
Uni-Activity Monitor Backend — Entry Point
Serves React build + WebSocket for real-time stats.
Port: 9999

All logic is in py/monitor/ package.
"""
import sys
import os

# Make sure py/ is in path when running as: python py/monitor_server.py
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import time
import threading
from http.server import ThreadingHTTPServer

import monitor.config as cfg
from monitor.telegram import tg_send
from monitor.tunnel import ping_url_thread
from monitor.tg_commands import tg_command_poll_thread
from monitor.threads import (
    udp_receiver_thread, udp_ai_receiver_thread,
    auto_sync_thread, manage_ai_service_thread,
    stats_collector_thread,
)
from monitor.http_handler import MonitorHandler
from monitor.alerts import fetch_public_ip_loop


if __name__ == "__main__":
    # ── Background threads ────────────────────────────────────────────────────
    threading.Thread(target=udp_receiver_thread,     daemon=True).start()
    threading.Thread(target=udp_ai_receiver_thread,  daemon=True).start()
    threading.Thread(target=ping_url_thread,          daemon=True).start()
    threading.Thread(target=manage_ai_service_thread, daemon=True).start()
    threading.Thread(target=auto_sync_thread,         daemon=True).start()
    threading.Thread(target=stats_collector_thread,   daemon=True).start()
    threading.Thread(target=tg_command_poll_thread,   daemon=True).start()
    threading.Thread(target=fetch_public_ip_loop,     daemon=True).start()

    # ── HTTP Server ────────────────────────────────────────────────────────────
    server = ThreadingHTTPServer(("", cfg.PORT), MonitorHandler)
    server.allow_reuse_address = True
    print(f"[Monitor] Serving at http://0.0.0.0:{cfg.PORT}")

    # ── Telegram startup notification ─────────────────────────────────────────
    def _send_startup():
        time.sleep(5)
        ts = time.strftime("%Y-%m-%d %H:%M:%S")
        tg_send(
            f"🟢 <b>Monitor Server Started</b>\n"
            f"━━━━━━━━━━━━━━━━━━━━\n"
            f"🕐 {ts}\n"
            f"🌐 Port: {cfg.PORT}\n"
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
