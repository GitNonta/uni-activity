#!/data/data/com.termux/files/usr/bin/bash
# start-cluster.sh — Phone 2 (WORKER) auto-start via Termux:Boot
# Starts: sshd, AI service, 3 web workers + watchdog, queue worker.
# Phone 2 has no runit and no local DB — everything connects to Phone 1 (192.168.1.222).
# Idempotent — safe to re-run; each step skips if already running.
# Log: ~/boot-cluster.log

PROJ="$HOME/uni-activity"
LOG="$HOME/boot-cluster.log"

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

# ── 0. Wake lock FIRST ──────────────────────────────────────────────────────
termux-wake-lock 2>/dev/null && log "wake-lock acquired"

# ── 1. Wait for network (max ~120s, then continue anyway) ───────────────────
for i in $(seq 1 60); do
    ping -c 1 -W 1 1.1.1.1 > /dev/null 2>&1 && { log "network up"; break; }
    sleep 2
done

# ── 1b. Clear stale Laravel caches (prevent serving old DB/Redis credentials) ──
(cd "$PROJ" && php artisan config:clear > /dev/null 2>&1 && php artisan route:clear > /dev/null 2>&1; true)

# ── 2. SSH daemon (:8022) ───────────────────────────────────────────────────
if ! pgrep -x sshd > /dev/null 2>&1; then
    sshd
    log "sshd started"
fi

# ── 3. AI service (proot, :8001) ────────────────────────────────────────────
if ! (echo > /dev/tcp/127.0.0.1/8001) 2>/dev/null; then
    setsid proot-distro login ubuntu -- bash -c \
        "cd $PROJ/ai_service && /root/ai_project/venv/bin/python server.py > server.log 2>&1" \
        > /dev/null 2>&1 < /dev/null &
    log "AI service starting (models load 60-90s)"
fi

# ── 4. Web workers (3× artisan serve) + watchdog ───────────────────────────
bash "$HOME/start_web_workers.sh" >> "$LOG" 2>&1
if ! pgrep -f "watch_web_workers[.]sh" > /dev/null 2>&1; then
    setsid bash "$HOME/watch_web_workers.sh" > /dev/null 2>&1 < /dev/null &
    log "web-worker watchdog started"
fi

# ── 5. Queue worker ─────────────────────────────────────────────────────────
if ! pgrep -f "artisan queue:work" > /dev/null 2>&1; then
    (cd "$PROJ" && setsid nohup php artisan queue:work redis --sleep=3 --tries=3 --timeout=90 \
        --no-interaction > storage/logs/queue.log 2>&1 < /dev/null &)
    log "queue worker started"
fi

log "===== Phone 2 boot complete ====="
