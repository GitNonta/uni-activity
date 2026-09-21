#!/bin/bash
# Nginx Upstream Health Checker + Process Guard
#
# LIVENESS, NOT READINESS:
#   A worker is marked "down" ONLY when it accepts no HTTP connection at all
#   (curl reports 000 — node off, process dead, port closed).
#
#   ANY HTTP response keeps the worker in the pool — 200, 302, 401, 404, and
#   also 500/503. A 5xx from /health means the *application* is degraded, not
#   that the worker process is gone; nginx's own max_fails/fail_timeout already
#   handles per-request failures and failover.
#
#   Why this matters: the previous revision required HTTP 200 from /health.
#   When Postgres broke, /health answered 503 on every worker, so all 7 upstream
#   members got a "down" marker and nginx returned 502 for the entire site
#   (Cloudflare surfaced its own 502 page). The health check took the whole
#   site down on its own, and because the recovery branch also required 200,
#   the markers were never removed.
#
# Safety nets:
#   * refuses to empty the pool — if every member fails the liveness probe,
#     the config is left untouched instead of marking all of them down
#   * single-instance lock — overlapping cron runs cannot race nginx -s reload
#     ("bind() ... Address already in use")
#   * nginx -t is run before reload and the config is restored if it fails
#   * the reload result is actually logged, not swallowed by 2>/dev/null
#
# Also kills orphaned cloudflared processes (pointing at a non-local origin).
# Runs every 60 seconds via crontab.

NGINX_CONF="/data/data/com.termux/files/usr/etc/nginx/nginx.conf"
NGINX_PREFIX="/data/data/com.termux/files/home/.nginx"
LOG="$HOME/nginx-healthcheck.log"
LOCK="$HOME/.nginx-healthcheck.lock"
BAK="$NGINX_CONF.healthcheck.bak"

TS() { date "+%Y-%m-%d %H:%M:%S"; }

# ==========================================================================
#  0. Single-instance lock
# ==========================================================================
acquire_lock() {
    if mkdir "$LOCK" 2>/dev/null; then
        echo $$ > "$LOCK/pid" 2>/dev/null
        return 0
    fi
    holder=$(cat "$LOCK/pid" 2>/dev/null)
    if [ -n "$holder" ] && kill -0 "$holder" 2>/dev/null; then
        return 1                     # previous run is still working — normal
    fi
    rm -rf "$LOCK" 2>/dev/null       # stale lock left by a killed run
    mkdir "$LOCK" 2>/dev/null || return 1
    echo $$ > "$LOCK/pid" 2>/dev/null
    return 0
}

acquire_lock || exit 0
trap 'rm -rf "$LOCK" 2>/dev/null' EXIT

# ==========================================================================
#  1. Kill orphaned cloudflared (not targeting a localhost origin)
# ==========================================================================
# Legitimate tunnels on this device always point at a local origin —
# cf-manager's HTTP (:8088) and SSH tunnels, the runit-managed :8080 tunnel,
# etc. Killing those causes Error 1033 within seconds (Cloudflare deregisters
# the hostname once edge connections drop) and burns the quick-tunnel
# registration quota. Only orphans pointed elsewhere (historically port 80)
# are killed here.
pgrep -af cloudflared 2>/dev/null | while IFS= read -r line; do
    pid=$(echo "$line" | awk '{print $1}')
    argv0=$(echo "$line" | awk '{print $2}')

    # Only real cloudflared processes. 'pgrep -f' matches ANY command line that
    # merely mentions the word — a du/ls/grep walking a directory containing
    # cloudflared.log, for example — and killing those is destructive.
    case "$argv0" in
        *cloudflared) ;;
        *) continue ;;
    esac

    # Skip the runit supervisor/log pair
    echo "$line" | grep -qE "runsv|svlogd" && continue
    # Skip legitimate tunnels targeting a localhost origin
    echo "$line" | grep -qE -- '--url (http|https|ssh)://(127\.0\.0\.1|localhost):' && continue

    echo "[$(TS)] KILLING orphaned cloudflared PID $pid: $line" >> "$LOG"
    kill "$pid" 2>/dev/null
done

# ==========================================================================
#  2. Kill orphaned svc_cloudflared.sh processes
# ==========================================================================
pgrep -af "svc_cloudflared" 2>/dev/null | while IFS= read -r line; do
    pid=$(echo "$line" | awk '{print $1}')
    argv1=$(echo "$line" | awk '{print $2}')
    # Same hazard as above: require the actual script, not merely a command
    # line that names it.
    case "$argv1" in
        *svc_cloudflared*) ;;
        *) continue ;;
    esac
    echo "[$(TS)] KILLING orphaned svc_cloudflared PID $pid" >> "$LOG"
    kill "$pid" 2>/dev/null
done

# ==========================================================================
#  3. Liveness probes
# ==========================================================================
WORKERS=(
    "127.0.0.1:8000 p1-8000"
    "127.0.0.1:8002 p1-8002"
    "127.0.0.1:8003 p1-8003"
    "192.168.1.140:8000 p2-8000"
    "192.168.1.140:8002 p2-8002"
    "192.168.1.140:8003 p2-8003"
    "192.168.1.140:8004 p2-8004"
)

UP=0
DOWN=0
RESULTS=""

for entry in "${WORKERS[@]}"; do
    addr="${entry%% *}"
    label="${entry##* }"

    # 000 = no HTTP response at all (connection refused / host unreachable / timeout).
    # Anything else means the worker answered, so it is alive.
    CODE=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 2 --max-time 8 \
                "http://$addr/health" 2>/dev/null)
    [ -z "$CODE" ] && CODE=000

    RESULTS="${RESULTS}${addr}|${label}|${CODE}"$'\n'

    if [ "$CODE" = "000" ]; then
        DOWN=$((DOWN + 1))
    else
        UP=$((UP + 1))
    fi
done

if [ "$UP" -eq 0 ]; then
    echo "[$(TS)] WARNING: all $DOWN workers failed the liveness probe (no HTTP response)." >> "$LOG"
    echo "[$(TS)] WARNING: refusing to mark the whole pool down — that would 502 the entire site." >> "$LOG"
    tail -200 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG" 2>/dev/null
    exit 0
fi

# ==========================================================================
#  4. Apply pool membership (liveness only)
# ==========================================================================
cp -f "$NGINX_CONF" "$BAK" 2>/dev/null

CHANGED=0

while IFS='|' read -r addr label code; do
    [ -z "$addr" ] && continue

    if [ "$code" = "000" ]; then
        if ! grep -q "server $addr.*down" "$NGINX_CONF" 2>/dev/null; then
            sed -i "s|server $addr max_fails=[0-9]* fail_timeout=[0-9]*s;|server $addr max_fails=2 fail_timeout=30s down;|" "$NGINX_CONF"
            echo "[$(TS)] $label ($addr) DOWN (no HTTP response) — added down marker" >> "$LOG"
            CHANGED=1
        fi
    else
        if grep -q "server $addr.*down" "$NGINX_CONF" 2>/dev/null; then
            sed -i "s|server $addr max_fails=[0-9]* fail_timeout=[0-9]*s down|server $addr max_fails=2 fail_timeout=30s|" "$NGINX_CONF"
            echo "[$(TS)] $label ($addr) UP (HTTP $code) — removed down marker" >> "$LOG"
            CHANGED=1
        fi
    fi
done <<< "$RESULTS"

# ==========================================================================
#  5. Validate, then reload — and report what actually happened
# ==========================================================================
if [ "$CHANGED" -eq 1 ]; then
    if nginx -t -p "$NGINX_PREFIX" -c "$NGINX_CONF" >> "$LOG" 2>&1; then
        if nginx -s reload >> "$LOG" 2>&1; then
            echo "[$(TS)] Nginx reloaded (up=$UP down=$DOWN)" >> "$LOG"
        else
            echo "[$(TS)] WARNING: 'nginx -s reload' FAILED — config change is not live" >> "$LOG"
        fi
    else
        cp -f "$BAK" "$NGINX_CONF" 2>/dev/null
        echo "[$(TS)] ERROR: 'nginx -t' failed after edit — restored previous config, not reloading" >> "$LOG"
    fi
fi

# ==========================================================================
#  6. Trim log (keep last 200 lines)
# ==========================================================================
tail -200 "$LOG" > "$LOG.tmp" 2>/dev/null && mv "$LOG.tmp" "$LOG" 2>/dev/null
