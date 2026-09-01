#!/bin/bash
# Phone 2 Auto-Recovery — PHP Workers, Monitor, ADB
LOG=~/recovery.log
PROJECT=/data/data/com.termux/files/home/uni-activity
TS() { date "+%Y-%m-%d %H:%M:%S"; }

termux-wake-lock 2>/dev/null

# PHP Workers (4 on Phone 2)
for p in 8000 8002 8003 8004; do
    if ! curl -s -o /dev/null --connect-timeout 2 "http://127.0.0.1:$p/" 2>/dev/null; then
        echo "[$(TS)] Worker :$p DOWN — restarting" >> "$LOG"
        cd "$PROJECT" && nohup php artisan serve --host=0.0.0.0 --port=$p &>/dev/null &
    fi
done

# Monitor
if ! curl -s -o /dev/null --connect-timeout 2 "http://127.0.0.1:9999/" 2>/dev/null; then
    echo "[$(TS)] Monitor DOWN" >> "$LOG"
    cd "$PROJECT" && nohup python3 py/monitor_server.py &>/dev/null &
fi

# SSHD
if ! pgrep -f sshd >/dev/null 2>&1; then
    echo "[$(TS)] SSHD DOWN" >> "$LOG"
    sshd 2>/dev/null
fi

# Trim log
tail -200 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG" 2>/dev/null
