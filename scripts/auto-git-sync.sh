#!/data/data/com.termux/files/usr/bin/bash
# Auto Git Sync Watchdog for Termux Production Server
# Checks origin/main every 10s and automatically updates code & clears cache

APP="/data/data/com.termux/files/home/uni-activity"
LOG="$APP/storage/logs/git-sync.log"

mkdir -p "$APP/storage/logs"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Auto Git Sync daemon started" >> "$LOG"

while true; do
  cd "$APP" || exit 1
  git fetch origin main > /dev/null 2>&1
  
  LOCAL_HASH=$(git rev-parse HEAD 2>/dev/null)
  REMOTE_HASH=$(git rev-parse origin/main 2>/dev/null)
  
  if [ -n "$LOCAL_HASH" ] && [ -n "$REMOTE_HASH" ] && [ "$LOCAL_HASH" != "$REMOTE_HASH" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] New commit detected ($LOCAL_HASH -> $REMOTE_HASH). Updating server..." >> "$LOG"
    
    git reset --hard origin/main >> "$LOG" 2>&1
    php artisan config:clear >> "$LOG" 2>&1
    php artisan route:clear >> "$LOG" 2>&1
    php artisan cache:clear >> "$LOG" 2>&1
    
    # Restart PHP-FPM for OPcache reset
    pkill -9 -f php-fpm > /dev/null 2>&1
    nohup php-fpm > /dev/null 2>&1 &
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Auto-deploy completed successfully." >> "$LOG"
  fi
  
  sleep 10
done
