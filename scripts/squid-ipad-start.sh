#!/bin/bash
# Start Squid iPad proxy on port 3129 (separate from main Squid on 3128)
CONF="$HOME/squid-ipad.conf"
CACHE_DIR="$HOME/squid-ipad-cache"
PID_FILE="$HOME/squid-ipad.pid"
MAIN_PID="/data/data/com.termux/files/usr/var/run/squid.pid"

# Temporarily rename main PID file to avoid conflict
if [ -f "$MAIN_PID" ]; then
    mv "$MAIN_PID" "${MAIN_PID}.bak"
fi

# Start iPad squid
squid -f "$CONF" -z 2>/dev/null  # init cache
squid -f "$CONF" -N -d 0 &
SQUID_PID=$!

# Restore main PID file
sleep 1
if [ -f "${MAIN_PID}.bak" ]; then
    mv "${MAIN_PID}.bak" "$MAIN_PID"
fi

echo "iPad Squid started on port 3129, PID: $SQUID_PID"
wait $SQUID_PID
