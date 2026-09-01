#!/bin/bash
# Nginx Upstream Health Checker
# Checks /health on each worker, marks dead workers as "down" in nginx.conf
# Runs every 30 seconds via crontab

NGINX_CONF="/data/data/com.termux/files/usr/etc/nginx/nginx.conf"
LOG=~/nginx-healthcheck.log
TS() { date "+%H:%M:%S"; }

# Workers to check: "address:port label"
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
    host="${addr%%:*}"
    port="${addr##*:}"

    # Check /health endpoint
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 3 "http://$addr/health" 2>/dev/null)

    if [ "$HTTP_CODE" = "200" ]; then
        # Worker is UP — remove "down" marker if present
        if grep -q "server $addr.*down" "$NGINX_CONF" 2>/dev/null; then
            sed -i "s|server $addr max_fails=[0-9]* fail_timeout=[0-9]*s down|server $addr max_fails=2 fail_timeout=30s|" "$NGINX_CONF"
            echo "[$(TS)] $label ($addr) UP — removed down marker" >> "$LOG"
            CHANGED=1
        fi
    else
        # Worker is DOWN — add "down" marker if not present
        if ! grep -q "server $addr.*down" "$NGINX_CONF" 2>/dev/null; then
            sed -i "s|server $addr max_fails=[0-9]* fail_timeout=[0-9]*s;|server $addr max_fails=2 fail_timeout=30s down;|" "$NGINX_CONF"
            echo "[$(TS)] $label ($addr) DOWN (HTTP $HTTP_CODE) — added down marker" >> "$LOG"
            CHANGED=1
        fi
    fi
done

# Reload nginx if any changes were made
if [ "$CHANGED" -eq 1 ]; then
    nginx -t 2>/dev/null && nginx -s reload 2>/dev/null
    echo "[$(TS)] Nginx reloaded" >> "$LOG"
fi

# Trim log
tail -100 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG" 2>/dev/null
