"""
Script to split monitor_server.py into monitor/ package modules.
Run: python py/split_monitor.py
"""
import os, re

SRC = "py/monitor_server.py"
OUT = "py/monitor"
os.makedirs(OUT, exist_ok=True)

src_lines = open(SRC, encoding="utf-8").read().splitlines()

def L(start, end):
    """Extract lines (1-indexed, inclusive), return as string."""
    return "\n".join(src_lines[start-1:end])

def fix_globals(code):
    """Replace bare global names with cfg.* references."""
    replacements = [
        ("with _stats_lock:",         "with cfg._stats_lock:"),
        ("_stats_cache.copy()",       "cfg._stats_cache.copy()"),
        ("if _stats_cache else",      "if cfg._stats_cache else"),
        ("_stats_cache = data",       "cfg._stats_cache = data"),
        ("global _stats_cache",       ""),
        ("global _tg_last_daily\n    _tg_last_daily = 0", "cfg._tg_last_daily = 0"),
        ("global _tg_last_daily",     ""),
        ("_tg_last_daily = time",     "cfg._tg_last_daily = time"),
        ("alerts_history)",           "cfg.alerts_history)"),
        ("alerts_history.appendleft", "cfg.alerts_history.appendleft"),
        ("list(alerts_history)",      "list(cfg.alerts_history)"),
        ("url_status.get(",           "cfg.url_status.get("),
        ("url_status.update(",        "cfg.url_status.update("),
        ('url_status["',              'cfg.url_status["'),
        ("active_alert_ids = ",       "cfg.active_alert_ids = "),
        ("active_alert_ids - ",       "cfg.active_alert_ids - "),
        ("active_alert_ids\n",        "cfg.active_alert_ids\n"),
        ("inspector_logs",            "cfg.inspector_logs"),
        ("remote_ai_logs",            "cfg.remote_ai_logs"),
        ("speedtest_data[",           "cfg.speedtest_data["),
        ("global speedtest_data",     ""),
        ("_ext_job.update(",          "with cfg._ext_lock:\n            cfg._ext_job.update("),
        ("_ext_job.get(",             "cfg._ext_job.get("),
        ('_ext_job["',               'cfg._ext_job["'),
        ("with _ext_lock:",           "with cfg._ext_lock:"),
        ("global _ext_job",           ""),
        ("prev_net_bytes",            "cfg.prev_net_bytes"),
        ("global prev_net_bytes",     ""),
        ("server_info_cache",         "cfg.server_info_cache"),
        ("global server_info_cache",  ""),
        ("line_status_cache",         "cfg.line_status_cache"),
        ("global line_status_cache",  ""),
        ("CACHED_PUBLIC_IP",          "cfg.CACHED_PUBLIC_IP"),
        ("global CACHED_PUBLIC_IP",   ""),
        ("ENV_PATH",                  "cfg.ENV_PATH"),
        ("NGINX_LOG",                 "cfg.NGINX_LOG"),
        ("STATIC_DIR",                "cfg.STATIC_DIR"),
        ("PORT",                      "cfg.PORT"),
        ("UDP_PORT_AI",               "cfg.UDP_PORT_AI"),
        ("UDP_PORT",                  "cfg.UDP_PORT"),
    ]
    for old, new in replacements:
        code = code.replace(old, new)
    return code

# ════════════════════════════════════════════════════════════════════════════════
# 1. tg_commands.py  (lines 150–1210)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(150, 1210))
content = (
    '"""\nmonitor/tg_commands.py — Telegram bot command handlers (_cmd_* functions).\n"""\n'
    "import time, json, threading, subprocess, re, os\n"
    "import monitor.config as cfg\n"
    "from monitor.telegram import tg_send, tg_daily_report\n\n"
) + body
open(f"{OUT}/tg_commands.py", "w", encoding="utf-8").write(content)
print(f"tg_commands.py   {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 2. speedtest.py  (lines 1211–1571)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(1211, 1571))
content = (
    '"""\nmonitor/speedtest.py — Speedtest threads (internal + external via Cloudflare).\n"""\n'
    "import time, threading, concurrent.futures\n"
    "import monitor.config as cfg\n\n"
) + body
open(f"{OUT}/speedtest.py", "w", encoding="utf-8").write(content)
print(f"speedtest.py     {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 3. tunnel.py  (lines 1572–1852)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(1572, 1852))
content = (
    '"""\nmonitor/tunnel.py — Cloudflare tunnel health checker (ping_url_thread).\n"""\n'
    "import time, threading, json, re, os, socket, ssl\n"
    "import urllib.parse, http.client, subprocess\n"
    "import monitor.config as cfg\n"
    "from monitor.telegram import tg_send\n\n"
) + body
open(f"{OUT}/tunnel.py", "w", encoding="utf-8").write(content)
print(f"tunnel.py        {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 4. collectors.py  (lines 1853–2631)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(1853, 2631))
content = (
    '"""\nmonitor/collectors.py — All get_*() data collection functions.\n"""\n'
    "import os, time, re, subprocess, socket, json\n"
    "import monitor.config as cfg\n\n"
) + body
open(f"{OUT}/collectors.py", "w", encoding="utf-8").write(content)
print(f"collectors.py    {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 5. alerts.py  (lines 2632–2777)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(2632, 2777))
content = (
    '"""\nmonitor/alerts.py — get_alerts(), collect_stats(), fetch_public_ip_loop().\n"""\n'
    "import time, threading, json\n"
    "import monitor.config as cfg\n"
    "from monitor.telegram import tg_alert, tg_resolved, tg_daily_report\n"
    "from monitor import collectors\n\n"
) + body
open(f"{OUT}/alerts.py", "w", encoding="utf-8").write(content)
print(f"alerts.py        {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 6. threads.py  (lines 2778–2915)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(2778, 2915))
content = (
    '"""\nmonitor/threads.py — Background worker threads (UDP, auto-sync, AI manager, stats collector, WS).\n"""\n'
    "import time, threading, json, os, socket, base64, hashlib, struct, subprocess\n"
    "import monitor.config as cfg\n"
    "from monitor.telegram import tg_send, tg_daily_report\n"
    "from monitor.alerts import collect_stats\n\n"
) + body
open(f"{OUT}/threads.py", "w", encoding="utf-8").write(content)
print(f"threads.py       {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 7. http_handler.py  (lines 2916–3454)
# ════════════════════════════════════════════════════════════════════════════════
body = fix_globals(L(2916, 3454))
content = (
    '"""\nmonitor/http_handler.py — MonitorHandler: do_GET, do_POST, do_OPTIONS, WebSocket upgrade.\n"""\n'
    "import time, json, os, re, socket, subprocess, threading\n"
    "from http.server import BaseHTTPRequestHandler\n"
    "import monitor.config as cfg\n"
    "from monitor.telegram import tg_send\n"
    "from monitor.alerts import collect_stats\n"
    "from monitor.speedtest import start_ext_speedtest, run_ext_speedtest_thread\n"
    "from monitor.threads import ws_handshake, ws_client_thread\n\n"
) + body
open(f"{OUT}/http_handler.py", "w", encoding="utf-8").write(content)
print(f"http_handler.py  {len(content.splitlines())} lines")

# ════════════════════════════════════════════════════════════════════════════════
# 8. __init__.py — export everything
# ════════════════════════════════════════════════════════════════════════════════
init = '''"""
monitor/__init__.py — Public API of the monitor package.
Import order matters: config first, then leaves, then dependents.
"""
from monitor import config
from monitor.telegram import tg_send, tg_alert, tg_resolved, tg_daily_report
from monitor.collectors import *
from monitor.alerts import get_alerts, collect_stats
from monitor.speedtest import (
    run_speedtest_thread, run_ext_speedtest_thread, start_ext_speedtest
)
from monitor.tunnel import ping_url_thread
from monitor.tg_commands import (
    tg_handle_commands, tg_command_poll_thread, _dispatch_command
)
from monitor.threads import (
    udp_receiver_thread, udp_ai_receiver_thread,
    auto_sync_thread, manage_ai_service_thread,
    stats_collector_thread, ws_client_thread, ws_handshake, ws_encode,
)
from monitor.http_handler import MonitorHandler
'''
open(f"{OUT}/__init__.py", "w", encoding="utf-8").write(init)
print(f"__init__.py      {len(init.splitlines())} lines")

print("\nAll modules created successfully!")
