#!/bin/bash
# Phone 2 — Start Shizuku via ADB and verify MacroDroid
LOG=~/recovery.log
TS() { date "+%Y-%m-%d %H:%M:%S"; }

echo "[$(TS)] Starting Shizuku..." >> "$LOG"

# Connect to local ADB WiFi
adb connect 127.0.0.1:5555 2>/dev/null

# Check if Shizuku is running
if pgrep -f "shizuku" >/dev/null 2>&1; then
    echo "[$(TS)] Shizuku already running" >> "$LOG"
else
    # Try to start via USB ADB
    DEVICE=$(adb devices 2>/dev/null | grep -w device | head -1 | awk '{print $1}')
    if [ -n "$DEVICE" ]; then
        adb -s "$DEVICE" shell "sh /sdcard/Android/data/moe.shizuku.privileged.api/start.sh" 2>> "$LOG"
        sleep 2
        echo "[$(TS)] Shizuku started via $DEVICE" >> "$LOG"
    else
        echo "[$(TS)] No ADB device — cannot start Shizuku" >> "$LOG"
    fi
fi

# Check MacroDroid
if pgrep -f "macrodroid" >/dev/null 2>&1; then
    echo "[$(TS)] MacroDroid running" >> "$LOG"
else
    echo "[$(TS)] MacroDroid not running" >> "$LOG"
fi
