#!/usr/bin/env python3
"""
EMERGENCY FIX - Reverb Service Quick Restart
For immediate recovery when Reverb is down
"""

import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

print("🚨 EMERGENCY REVERB RESTART 🚨\n")

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    
    def run(cmd):
        stdin, stdout, stderr = client.exec_command(cmd)
        return stdout.read().decode(errors="replace")
    
    # Step 1: Kill old Reverb
    print("1️⃣  Killing old Reverb processes...")
    run("pkill -9 -f 'artisan reverb'")
    time.sleep(2)
    print("   ✓ Done\n")
    
    # Step 2: Check Redis
    print("2️⃣  Checking Redis...")
    redis_check = run("pgrep redis-server")
    if not redis_check.strip():
        print("   ⚠ Redis not running, starting...")
        run("redis-server --daemonize yes")
        time.sleep(2)
    print("   ✓ Redis OK\n")
    
    # Step 3: Check PHP-FPM
    print("3️⃣  Checking PHP-FPM...")
    php_check = run("pgrep php-fpm")
    if not php_check.strip():
        print("   ⚠ PHP-FPM not running, starting...")
        run("php-fpm")
        time.sleep(2)
    print("   ✓ PHP-FPM OK\n")
    
    # Step 4: Check Nginx
    print("4️⃣  Checking Nginx...")
    nginx_check = run("pgrep nginx")
    if not nginx_check.strip():
        print("   ⚠ Nginx not running, starting...")
        run("nginx")
        time.sleep(2)
    else:
        print("   ℹ Reloading Nginx config...")
        run("nginx -s reload")
    print("   ✓ Nginx OK\n")
    
    # Step 5: Start Reverb
    print("5️⃣  Starting Reverb...")
    run("""cd /data/data/com.termux/files/home/uni-activity && \
nohup php artisan reverb:start --host=0.0.0.0 --port=8082 \
> storage/logs/reverb.log 2>&1 &""")
    time.sleep(3)
    
    # Verify
    reverb_check = run("pgrep -f 'artisan reverb'")
    if reverb_check.strip():
        print("   ✓ Reverb started successfully!\n")
        
        # Show recent log
        print("📋 Recent Reverb log:")
        log = run("tail -n 15 /data/data/com.termux/files/home/uni-activity/storage/logs/reverb.log")
        print(log)
        
        # Show status
        print("\n📊 Service Status:")
        status = run("""ps aux | grep -E '(redis|php-fpm|nginx|reverb)' | grep -v grep | \
awk '{print $11, $12, $13}'""")
        print(status)
        
        print("\n✅ REVERB IS NOW ONLINE!")
        print("🌐 WebSocket: ws://192.168.1.222:8080/app/uni-chat-key")
        print("🌐 HTTP: http://192.168.1.222:8080")
    else:
        print("   ✗ Failed to start Reverb\n")
        print("📋 Error log:")
        error_log = run("tail -n 30 /data/data/com.termux/files/home/uni-activity/storage/logs/reverb.log")
        print(error_log)
    
    client.close()
    
except Exception as e:
    print(f"\n❌ ERROR: {e}")
    import traceback
    traceback.print_exc()
