#!/bin/bash
# Optimized Proxy Setup — Multiple Microsocks instances + TCP tuning
# Run: bash proxy-optimize.sh start|stop|status

INSTANCES=4
BASE_PORT=1080
LOG=~/proxy-optimize.log
TS() { date "+%Y-%m-%d %H:%M:%S"; }

start() {
    echo "[$(TS)] Starting optimized proxy..." >> "$LOG"

    # 1. TCP buffer tuning
    for f in /proc/sys/net/core/rmem_max /proc/sys/net/core/wmem_max; do
        echo 16777216 > "$f" 2>/dev/null
    done
    for f in /proc/sys/net/ipv4/tcp_rmem /proc/sys/net/ipv4/tcp_wmem; do
        echo "4096 87380 16777216" > "$f" 2>/dev/null
    done
    echo 3 > /proc/sys/net/ipv4/tcp_fastopen 2>/dev/null
    echo 1024 > /proc/sys/net/core/somaxconn 2>/dev/null
    echo "[$(TS)] TCP tuned: buffers=16MB, fastopen=3" >> "$LOG"

    # 2. Increase file descriptor limit
    ulimit -n 65536 2>/dev/null

    # 3. Kill old Microsocks
    kill $(pgrep microsocks) 2>/dev/null
    sleep 1

    # 4. Start multiple Microsocks instances
    for i in $(seq 0 $((INSTANCES-1))); do
        PORT=$((BASE_PORT + i))
        microsocks -p $PORT -i 0.0.0.0 &>/dev/null &
        echo "[$(TS)] Started microsocks on port $PORT (PID $!)" >> "$LOG"
    done

    echo "[$(TS)] $INSTANCES Microsocks instances started on ports $BASE_PORT-$((BASE_PORT+INSTANCES-1))" >> "$LOG"
    echo "Proxy started: $INSTANCES instances on ports $BASE_PORT-$((BASE_PORT+INSTANCES-1))"
}

stop() {
    kill $(pgrep microsocks) 2>/dev/null
    echo "[$(TS)] All Microsocks stopped" >> "$LOG"
    echo "Proxy stopped"
}

status() {
    echo "=== Proxy Status ==="
    echo "Instances: $(pgrep -c microsocks)"
    echo "Ports:"
    for i in $(seq 0 $((INSTANCES-1))); do
        PORT=$((BASE_PORT + i))
        if netstat -tlnp 2>/dev/null | grep -q ":$PORT "; then
            echo "  :$PORT ✅ listening"
        else
            echo "  :$PORT ❌ not listening"
        fi
    done
    echo ""
    echo "Active connections:"
    for i in $(seq 0 $((INSTANCES-1))); do
        PORT=$((BASE_PORT + i))
        COUNT=$(netstat -tn 2>/dev/null | grep ":$PORT" | wc -l)
        echo "  :$PORT → $COUNT connections"
    done
}

case "${1:-status}" in
    start) start ;;
    stop) stop ;;
    status) status ;;
    *) echo "Usage: $0 {start|stop|status}" ;;
esac
