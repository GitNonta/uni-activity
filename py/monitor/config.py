"""
monitor/config.py — Shared constants, paths, and mutable global state.
All modules import from here; nothing here imports from other monitor modules.
"""
import os
import time
import collections
from pathlib import Path

# ── Paths ─────────────────────────────────────────────────────────────────────
ENV_PATH   = "/data/data/com.termux/files/home/uni-activity/.env"
NGINX_LOG  = "/data/data/com.termux/files/usr/var/log/nginx/access.log"
STATIC_DIR = Path(__file__).parent.parent.parent / "monitor-ui" / "dist"

# ── Ports ─────────────────────────────────────────────────────────────────────
PORT        = 9999
UDP_PORT    = 9998
UDP_PORT_AI = 9997

# ── Dual-node: tunnel ต้องชี้ไปที่ Nginx Load Balancer (ไม่ใช่ Octane ตรงๆ) ──
LB_PORT            = 8088
TUNNEL_TARGET_URL  = os.environ.get("TUNNEL_TARGET_URL", f"http://127.0.0.1:{LB_PORT}")

# ── Shared mutable state ──────────────────────────────────────────────────────
inspector_logs   = collections.deque(maxlen=100)
remote_ai_logs   = collections.deque(maxlen=200)
url_status       = {"online": False, "ping_ms": 0, "error": "", "url": ""}
alerts_history   = collections.deque(maxlen=100)
active_alert_ids: set = set()

# ── Telegram config ───────────────────────────────────────────────────────────
TELEGRAM_BOT_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "")
TELEGRAM_CHAT_ID   = os.environ.get("TELEGRAM_CHAT_ID", "")

if (not TELEGRAM_BOT_TOKEN or not TELEGRAM_CHAT_ID) and os.path.exists(ENV_PATH):
    try:
        with open(ENV_PATH, "r", encoding="utf-8") as _f:
            for _line in _f:
                _line = _line.strip()
                if _line.startswith("TELEGRAM_BOT_TOKEN=") and not TELEGRAM_BOT_TOKEN:
                    TELEGRAM_BOT_TOKEN = _line.split("=", 1)[1].strip().strip('"\'')
                elif _line.startswith("TELEGRAM_CHAT_ID=") and not TELEGRAM_CHAT_ID:
                    TELEGRAM_CHAT_ID = _line.split("=", 1)[1].strip().strip('"\'')
    except Exception:
        pass

ALERT_MIN_INTERVAL = 600   # ขั้นต่ำ 10 นาที ระหว่าง alert เดิม
STARTUP_GRACE      = 90    # วินาที หลัง start ไม่ส่ง cf_offline

# ── Telegram internal state (mutable) ────────────────────────────────────────
_tg_sent_ids: set         = set()
_tg_resolved: set         = set()
_tg_last_daily: float     = 0.0
_tg_alert_cooldown: dict  = {}
_monitor_start_time: float = time.time()
_tg_last_update_id: int   = 0

# ── Stats cache (shared between threads) ──────────────────────────────────────
import threading
_stats_cache: dict  = {}
_stats_lock         = threading.Lock()
CACHED_PUBLIC_IP: str = ""

# ── Speedtest data ────────────────────────────────────────────────────────────
speedtest_data = {
    "status": "idle", "stage": "idle",
    "ping_ms": 0, "jitter_ms": 0,
    "download_mbps": 0, "upload_mbps": 0,
    "server": {"name": "Auto-Select Server", "code": "AUTO", "latency_ms": 0},
    "last_test": None
}
_ext_job = {
    "status": "idle", "stage": "idle",
    "ping": 0.0, "jitter": 0.0, "ping_min": 0.0, "ping_max": 0.0,
    "download": 0.0, "upload": 0.0,
    "method": "TCP:443", "server": "Cloudflare (1.1.1.1)", "error": None,
}
_ext_lock = threading.Lock()

# ── Server info cache ─────────────────────────────────────────────────────────
server_info_cache = None
line_status_cache = {"status": "Checking...", "error": None, "last_check": 0}
prev_net_bytes    = {"rx": 0, "tx": 0, "time": 0.0}

# ── Network counters (for get_network() in collectors) ────────────────────────
last_rx:       int   = 0
last_tx:       int   = 0
last_net_time: float = 0.0
