#!/data/data/com.termux/files/usr/bin/bash
# start-cluster.sh — Phone 1 (MASTER) auto-start via Termux:Boot
# Starts: runit services (cloudflared/nginx/postgres/sshd), redis, AI,
#         3 web workers + watchdog, reverb, queue worker, monitor.
# Idempotent — safe to re-run; each step skips if already running.
# Log: ~/boot-cluster.log

PREFIX="/data/data/com.termux/files/usr"
PROJ="$HOME/uni-activity"
LOG="$HOME/boot-cluster.log"
SVDIR="$PREFIX/var/service"

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

# ── 0. Wake lock FIRST (prevents CPU sleep during boot) ─────────────────────
termux-wake-lock 2>/dev/null && log "wake-lock acquired"

# ── 1. Wait for network (max ~120s, then continue anyway) ───────────────────
for i in $(seq 1 60); do
    ping -c 1 -W 1 1.1.1.1 > /dev/null 2>&1 && { log "network up"; break; }
    sleep 2
done

# ── 1b. Clear stale Laravel caches (prevent serving old DB/Redis credentials) ──
(cd "$PROJ" && php artisan config:clear > /dev/null 2>&1 && php artisan route:clear > /dev/null 2>&1; true)

# ── 2. Start runit service daemon if not running ────────────────────────────
#    (runit manages cloudflared/nginx/postgres/sshd — auto-restart on crash)
export SVDIR
if ! pgrep -x runsvdir > /dev/null 2>&1; then
    service-daemon start 2>/dev/null
    sleep 3
    log "runsvdir started"
fi

# ── 3. Start nginx LB, cloudflared tunnel, postgres, sshd via runit ────────
for svc in cloudflared nginx postgres sshd; do
    sv up "$svc" 2>/dev/null && log "sv up $svc" || true
done
sleep 3

# ── 4. Valkey datastore (not under runit) — bind 0.0.0.0 + requirepass from .env ─
# Valkey 9.x เป็น drop-in แทน Redis — ใช้ port/password เดิม โปรแกรม (predis) ไม่ต้องแก้
# หมายเหตุ: RDB ของ Redis 8.8 (format v14) โหลดใน Valkey ไม่ได้ → ถ้าต้อง migrate ข้อมูลเก่า
# ต้องคัดลอกแบบ key-by-key (ดู scripts/migrate_redis_to_valkey.php)
if ! (echo > /dev/tcp/127.0.0.1/6379) 2>/dev/null; then
    RPW=$(awk -F= '/^REDIS_PASSWORD=/{print $2}' "$PROJ/.env" | tr -d '"\r')
    valkey-server --daemonize yes --port 6379 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-data" --dbfilename dump.rdb 2>/dev/null
    sleep 1
    log "valkey started on :6379"
fi

# ── 5. Web workers (3× artisan serve) + watchdog ───────────────────────────
bash "$HOME/start_web_workers.sh" >> "$LOG" 2>&1
if ! pgrep -f "watch_web_workers[.]sh" > /dev/null 2>&1; then
    setsid bash "$HOME/watch_web_workers.sh" > /dev/null 2>&1 < /dev/null &
    log "web-worker watchdog started"
fi

# ── 6. Reverb (WebSocket :8082, 0.0.0.0) ───────────────────────────────────
if ! (echo > /dev/tcp/127.0.0.1/8082) 2>/dev/null; then
    (cd "$PROJ" && setsid nohup php artisan reverb:start --host=0.0.0.0 --port=8082 --no-interaction \
        > storage/logs/reverb.log 2>&1 < /dev/null &)
    log "reverb started on :8082"
fi

# ── 7. Queue worker ─────────────────────────────────────────────────────────
if ! pgrep -f "artisan queue:work" > /dev/null 2>&1; then
    (cd "$PROJ" && setsid nohup php artisan queue:work redis --sleep=3 --tries=3 --timeout=90 \
        --no-interaction > storage/logs/queue.log 2>&1 < /dev/null &)
    log "queue worker started"
fi

# ── 8. AI service (proot, :8001) ────────────────────────────────────────────
if ! (echo > /dev/tcp/127.0.0.1/8001) 2>/dev/null; then
    setsid proot-distro login ubuntu -- bash -c \
        "cd $PROJ/ai_service && /root/ai_project/venv/bin/python server.py > server.log 2>&1" \
        > /dev/null 2>&1 < /dev/null &
    log "AI service starting (models load 60-90s)"
fi

# ── 9. Monitor (Telegram alerts + stats) ────────────────────────────────────
if ! pgrep -f "monitor_server[.]py" > /dev/null 2>&1; then
    (cd "$PROJ" && setsid nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 < /dev/null &)
    log "monitor started"
fi

log "===== Phone 1 boot complete ====="
