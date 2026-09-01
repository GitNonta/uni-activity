#!/bin/bash
# Phone 2 — Worker Health Checker + Auto-restart
# Checks /health on local workers, restarts dead ones
LOG=~/phone2-healthcheck.log
PROJECT=/data/data/com.termux/files/home/uni-activity
TS() { date "+%Y-%m-%d %H:%M:%S"; }

for p in 8000 8002 8003 8004; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 3 "http://127.0.0.1:$p/health" 2>/dev/null)
    if [ "$HTTP_CODE" != "200" ]; then
        echo "[$(TS)] Worker :$p DOWN (HTTP $HTTP_CODE) — restarting" >> "$LOG"
        PID=$(lsof -ti :$p 2>/dev/null)
        [ -n "$PID" ] && kill "$PID" 2>/dev/null
        cd "$PROJECT" && nohup php artisan serve --host=0.0.0.0 --port=$p &>/dev/null &
    fi
done
tail -100 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG" 2>/dev/null
