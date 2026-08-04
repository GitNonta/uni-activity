#!/data/data/com.termux/files/usr/bin/bash
# Watchdog สำหรับ Queue Worker & Auto Git Sync — restart & update อัตโนมัติ
APP=/data/data/com.termux/files/home/uni-activity
LOG=$APP/storage/logs/queue.log
SYNC_LOG=$APP/storage/logs/git-sync.log

mkdir -p $APP/storage/logs
echo "[$(date)] Watchdog started" >> $LOG

while true; do
  cd $APP
  
  # 1. Auto Git Sync (อัปเดตโค้ดตาม GitHub อัตโนมัติทุก 10 วิ)
  git fetch origin main > /dev/null 2>&1
  LOCAL_HASH=$(git rev-parse HEAD 2>/dev/null)
  REMOTE_HASH=$(git rev-parse origin/main 2>/dev/null)
  
  if [ -n "$LOCAL_HASH" ] && [ -n "$REMOTE_HASH" ] && [ "$LOCAL_HASH" != "$REMOTE_HASH" ]; then
    echo "[$(date)] New commit detected ($LOCAL_HASH -> $REMOTE_HASH). Updating server..." >> $SYNC_LOG
    git reset --hard origin/main >> $SYNC_LOG 2>&1
    php artisan config:clear >> $SYNC_LOG 2>&1
    php artisan route:clear >> $SYNC_LOG 2>&1
    php artisan cache:clear >> $SYNC_LOG 2>&1
    pkill -9 -f php-fpm > /dev/null 2>&1
    nohup php-fpm > /dev/null 2>&1 &
    echo "[$(date)] Server auto-updated successfully." >> $SYNC_LOG
  fi

  # 2. Queue Worker Watchdog
  if ! pgrep -f "artisan queue:work" > /dev/null; then
    echo "[$(date)] Queue Worker ตาย — กำลัง restart..." >> $LOG
    php artisan queue:work redis --queue=line-notifications,default --tries=3 --sleep=3 --max-time=3600 >> $LOG 2>&1 &
    sleep 5
  fi
  
  sleep 10
done
