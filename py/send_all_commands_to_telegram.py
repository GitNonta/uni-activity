"""
send_all_commands_to_telegram.py — Dispatch all Telegram bot commands sequentially.
"""
import sys
import os
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import monitor.config as cfg
import monitor.telegram as tg
from monitor.tg_commands import _dispatch_command
from monitor.alerts import collect_stats

print(f"TELEGRAM_BOT_TOKEN: {'SET' if cfg.TELEGRAM_BOT_TOKEN else 'MISSING'}")
print(f"TELEGRAM_CHAT_ID:   {cfg.TELEGRAM_CHAT_ID or 'MISSING'}")

# Ensure stats cache has fresh real server data
print("Collecting fresh stats...")
fresh_stats = collect_stats()
with cfg._stats_lock:
    cfg._stats_cache = fresh_stats

tg.tg_send("🚀 <b>[Auto Test] เริ่มรันคำสั่งทั้งหมดส่งเข้า Telegram...</b>")
time.sleep(1)

commands_to_run = [
    "/start",
    "/help",
    "/status",
    "/services",
    "/load",
    "/memory",
    "/disk",
    "/network",
    "/top",
    "/ports",
    "/redis",
    "/db",
    "/alerts",
    "/logs",
    "/tunnel",
    "/tunnel_url",
    "/tunnel_log",
    "/tunnel_help",
    "/report",
]

for i, cmd in enumerate(commands_to_run, 1):
    print(f"[{i}/{len(commands_to_run)}] Dispatching {cmd} ...")
    _dispatch_command(cmd)
    time.sleep(1.2)  # rate limit delay for Telegram API

tg.tg_send("🏁 <b>[Auto Test] ส่งคำสั่งทั้งหมดครบถ้วนเรียบร้อยแล้ว (19 คำสั่ง)</b>")
print("Done sending all commands to Telegram!")
