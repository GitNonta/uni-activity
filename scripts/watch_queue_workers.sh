#!/data/data/com.termux/files/usr/bin/bash
# watch_queue_workers.sh — auto-restart the Redis queue worker on THIS node if it dies.
#
# Why: `php artisan queue:work` can exit on a transient Valkey/Redis drop
# (predis throws "Error while reading line from the server" and the process dies),
# and unlike the web workers there was no watchdog to bring it back — so jobs
# silently stopped being processed until a manual restart. A tiny bash loop
# (~few KB) is never the Android OOM killer's target, so it survives and
# restarts the worker in seconds.
#
# Usage (detached, survives SSH close):
#   setsid bash watch_queue_workers.sh > /dev/null 2>&1 < /dev/null &

cd "$HOME/uni-activity" || exit 1
LOG="storage/logs/queue-watchdog.log"

# Must match the queue list the boot script starts
# (scripts/boot-node1.sh / scripts/boot-node2.sh).
QUEUES="ai,notifications,exports,line-notifications,sync,stats,images,default"

log() { echo "[$(date '+%F %T')] $*" >> "$LOG"; }

LAST_RESTART=0   # epoch seconds (restart cooldown)

restart_worker() {
    local now
    now=$(date +%s)
    # Cooldown: max one restart per 60s, prevents restart storms if the worker
    # crashes instantly (e.g. Redis down or a bad deploy).
    if (( now - LAST_RESTART < 60 )); then
        return
    fi
    LAST_RESTART=$now
    log "queue worker DOWN — restarting"
    pkill -f "artisan queue:work" 2>/dev/null
    setsid nohup php artisan queue:work redis --queue="$QUEUES" --sleep=3 --tries=3 --timeout=90 \
        --no-interaction > storage/logs/queue.log 2>&1 < /dev/null &
    log "queue worker restarted (pid $!)"
}

log "queue worker watchdog started (queues: $QUEUES)"
while true; do
    if pgrep -f "artisan queue:work" > /dev/null 2>&1; then
        # Worker healthy — clear cooldown so a genuine later kill (e.g. OOM)
        # is restarted immediately, not blocked by the crash-loop cooldown
        # from an earlier restart.
        LAST_RESTART=0
    else
        restart_worker
    fi
    sleep 10
done
