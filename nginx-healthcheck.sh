#!/bin/bash
# Nginx Upstream Health Checker + Process Guard
# Checks /health on each worker, marks dead workers as "down"
# Also kills orphaned cloudflared processes (pointing to wrong port)
# Runs every 30 seconds via crontab

NGINX_CONF="/data/data/com.termux/files/usr/etc/nginx/nginx.conf"
LOG=~/nginx-healthcheck.log
TS() { date "+%Y-%m-%d %H:%M:%S"; }

# ══════════════════════════════════════════════════════════
#  1. Kill orphaned cloudflared (pointing to port 80)
# ══════════════════════════════════════════════════════════
CORRECT_CF="cloudflared tunnel --no-autoupdate --url http://127.0.0.1:8080"
pgrep -af cloudflared 2>/dev/null | while IFS= read -r line; do
    pid=$(echo "$line" | awk '{print $1}')
    # Skip runsv/svlogd processes
    echo "$line" | grep -qE "runsv|svlogd" && continue
    # Skip the correct cloudflared
    echo "$line" | grep -q "url http://127.0.0.1:8080" && continue
    # Skip if it's just our own grep
    echo "$line" | grep -q "grep" && continue
    # This is an orphaned cloudflared — kill it
    echo "[$(TS)] KILLING orphaned cloudflared PID $pid: $line" >> "$LOG"
    kill "$pid" 2>/dev/null
done

# ══════════════════════════════════════════════════════════
#  2. Kill orphaned svc_cloudflared.sh processes
# ══════════════════════════════════════════════════════════
pgrep -af "svc_cloudflared" 2>/dev/null | while IFS= read -r line; do
    pid=$(echo "$line" | awk '{print $1}')
    echo "[$(TS)] KILLING orphaned svc_cloudflared PID $pid" >> "$LOG"
    kill "$pid" 2>/dev/null
done

# ══════════════════════════════════════════════════════════
#  3. Worker Health Checks
# ══════════════════════════════════════════════════════════
WORKERS=(
    "127.0.0.1:8000 p1-8000"
    "127.0.0.1:8002 p1-8002"
    "127.0.0.1:8003 p1-8003"
    "192.168.1.140:8000 p2-8000"
    "192.168.1.140:8002 p2-8002"
    "192.168.1.140:8003 p2-8003"
    "192.168.1.140:8004 p2-8004"
)

CHANGED=0

for entry in "${WORKERS[@]}"; do
    addr="${entry%% *}"
    label="${entry##* }"

    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 3 "http://$addr/health" 2>/dev/null)

    if [ "$HTTP_CODE" = "200" ]; then
        if grep -q "server $addr.*down" "$NGINX_CONF" 2>/dev/null; then
            sed -i "s|server $addr max_fails=[0-9]* fail_timeout=[0-9]*s down|server $addr max_fails=2 fail_timeout=30s|" "$NGINX_CONF"
            echo "[$(TS)] $label ($addr) UP — removed down marker" >> "$LOG"
            CHANGED=1
        fi
    else
        if ! grep -q "server $addr.*down" "$NGINX_CONF" 2>/dev/null; then
            sed -i "s|server $addr max_fails=[0-9]* fail_timeout=[0-9]*s;|server $addr max_fails=2 fail_timeout=30s down;|" "$NGINX_CONF"
            echo "[$(TS)] $label ($addr) DOWN (HTTP $HTTP_CODE) — added down marker" >> "$LOG"
            CHANGED=1
        fi
    fi
done

# ══════════════════════════════════════════════════════════
#  4. Reload Nginx if changes were made
# ══════════════════════════════════════════════════════════
if [ "$CHANGED" -eq 1 ]; then
    nginx -t 2>/dev/null && nginx -s reload 2>/dev/null
    echo "[$(TS)] Nginx reloaded" >> "$LOG"
fi

# ══════════════════════════════════════════════════════════
#  5. Trim log (keep last 200 lines)
# ══════════════════════════════════════════════════════════
tail -200 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG" 2>/dev/null
