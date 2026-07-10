#!/data/data/com.termux/files/usr/bin/zsh
# Watchdog สำหรับ Queue Worker — restart อัตโนมัติถ้าตาย
APP=/data/data/com.termux/files/home/uni-activity
LOG=$APP/storage/logs/queue.log

echo "[$(date)] Watchdog started" >> $LOG

while true; do
  if ! pgrep -f "artisan queue:work" > /dev/null; then
    echo "[$(date)] Queue Worker ตาย — กำลัง restart..." >> $LOG
    cd $APP && php artisan queue:work --tries=3 --sleep=3 --max-time=3600 >> $LOG 2>&1 &
    sleep 5
  fi
  sleep 10
done
