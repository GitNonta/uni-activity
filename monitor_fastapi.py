import asyncio
import json
import os
import re
import time
from pathlib import Path

from fastapi import FastAPI, WebSocket, WebSocketDisconnect
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, FileResponse
from fastapi.staticfiles import StaticFiles

ENV_PATH = "/data/data/com.termux/files/home/uni-activity/.env"
NGINX_LOG = "/data/data/com.termux/files/usr/var/log/nginx/access.log"
CF_LOG = "/data/data/com.termux/files/home/uni-activity/cloudflared.log"
STATIC_DIR = Path(__file__).parent / "monitor-ui" / "dist"

app = FastAPI(title="Uni-Activity Monitor")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Serve React static files if they exist
if STATIC_DIR.exists():
    app.mount("/assets", StaticFiles(directory=STATIC_DIR / "assets"), name="assets")


def get_cf_url() -> str:
    """Read current Cloudflare URL from .env"""
    if os.path.exists(ENV_PATH):
        with open(ENV_PATH, "r") as f:
            for line in f:
                if line.startswith("APP_URL="):
                    url = line.split("=", 1)[1].strip()
                    if "trycloudflare.com" in url or "ngrok" in url:
                        return url
    return "Not Found"


def get_memory() -> dict:
    """Parse /proc/meminfo"""
    mem = {"total": 0, "available": 0, "used": 0, "percent": 0}
    try:
        with open("/proc/meminfo") as f:
            lines = f.readlines()
        info = {}
        for line in lines:
            parts = line.split()
            if len(parts) >= 2:
                info[parts[0].rstrip(":")] = int(parts[1])
        total = info.get("MemTotal", 0)
        available = info.get("MemAvailable", 0)
        used = total - available
        mem = {
            "total_mb": round(total / 1024),
            "available_mb": round(available / 1024),
            "used_mb": round(used / 1024),
            "percent": round((used / total) * 100, 1) if total > 0 else 0,
        }
    except Exception:
        pass
    return mem


def get_load() -> list:
    """Parse /proc/loadavg"""
    try:
        with open("/proc/loadavg") as f:
            parts = f.read().split()
            return [float(parts[0]), float(parts[1]), float(parts[2])]
    except Exception:
        return [0.0, 0.0, 0.0]


def get_network() -> dict:
    """Parse /proc/net/dev for wlan0"""
    result = {}
    try:
        with open("/proc/net/dev") as f:
            lines = f.readlines()
        for line in lines[2:]:
            parts = line.split(":")
            if len(parts) == 2:
                iface = parts[0].strip()
                if iface == "wlan0":
                    stats = parts[1].split()
                    result = {
                        "rx_mb": round(int(stats[0]) / 1048576, 2),
                        "tx_mb": round(int(stats[8]) / 1048576, 2),
                    }
    except Exception:
        pass
    return result


def get_nginx_logs() -> list:
    """Get last 15 nginx access log entries"""
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
            ip = meta[0] if len(meta) > 0 else "?"
            time_str = meta[3][1:] if len(meta) > 3 else "?"
            req = parts[1]
            status_parts = parts[2].split()
            status = status_parts[0] if status_parts else "?"
            size = status_parts[1] if len(status_parts) > 1 else "0"
            ua = parts[5] if len(parts) > 5 else ""
            logs.append({
                "ip": ip,
                "time": time_str,
                "req": req,
                "status": status,
                "size": size,
                "ua": ua,
            })
    except Exception:
        pass
    return logs


def collect_stats() -> dict:
    return {
        "timestamp": int(time.time()),
        "cf_url": get_cf_url(),
        "memory": get_memory(),
        "load": get_load(),
        "network": get_network(),
        "logs": get_nginx_logs(),
    }


@app.get("/api/stats")
async def api_stats():
    return collect_stats()


@app.websocket("/ws")
async def websocket_endpoint(websocket: WebSocket):
    await websocket.accept()
    try:
        while True:
            stats = collect_stats()
            await websocket.send_text(json.dumps(stats))
            await asyncio.sleep(2)
    except WebSocketDisconnect:
        pass


@app.get("/")
async def root():
    index_file = STATIC_DIR / "index.html"
    if index_file.exists():
        return FileResponse(str(index_file))
    return HTMLResponse("<h2>React build not found. Push dist/ to monitor-ui/dist/</h2>")
