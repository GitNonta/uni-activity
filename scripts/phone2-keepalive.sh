#!/bin/bash
# Phone 2 — Termux Keepalive Setup
# Install: bash phone2-keepalive.sh install
# Remove:  bash phone2-keepalive.sh remove
ACTION=${1:-install}

case "$ACTION" in
    install)
        echo "=== Installing Termux Keepalive ==="
        
        # 1. Wakelock
        termux-wake-lock 2>/dev/null && echo "✓ Wakelock acquired"
        
        # 2. Termux:Boot startup script
        mkdir -p ~/.termux/boot
        cat > ~/.termux/boot/start-services.sh << 'BOOT'
#!/bin/bash
termux-wake-lock 2>/dev/null
cd /data/data/com.termux/files/home/uni-activity
sshd 2>/dev/null
adb start-server 2>/dev/null
for p in 8000 8002 8003 8004; do
    nohup php artisan serve --host=0.0.0.0 --port=$p &>/dev/null &
done
nohup python3 py/monitor_server.py &>/dev/null &
crond 2>/dev/null
bash ~/phone2-recovery.sh 2>/dev/null
BOOT
        chmod +x ~/.termux/boot/start-services.sh
        echo "✓ Termux:Boot configured"
        
        # 3. Crontab
        (crontab -l 2>/dev/null | grep -v "phone2-recovery"; echo "*/5 * * * * bash ~/phone2-recovery.sh 2>/dev/null") | crontab -
        echo "✓ Crontab installed"
        
        # 4. Start crond
        pgrep crond >/dev/null 2>&1 || crond 2>/dev/null
        echo "✓ crond started"
        
        echo ""
        echo "=== Keepalive installed ==="
        echo "Install Termux:Boot app from F-Droid for auto-start on boot."
        echo "Disable battery optimization for Termux, Shizuku, MacroDroid."
        ;;
    remove)
        termux-wake-unlock 2>/dev/null
        rm -f ~/.termux/boot/start-services.sh 2>/dev/null
        crontab -l 2>/dev/null | grep -v "phone2-recovery" | crontab -
        echo "✓ Removed"
        ;;
    status)
        echo "=== Termux Keepalive Status ==="
        echo "Boot script: $([ -f ~/.termux/boot/start-services.sh ] && echo installed || echo missing)"
        echo "Crontab: $(crontab -l 2>/dev/null | grep recovery || echo not set)"
        echo "crond: $(pgrep crond >/dev/null && echo running || echo stopped)"
        ;;
    *)
        echo "Usage: $0 {install|remove|status}"
        ;;
esac
