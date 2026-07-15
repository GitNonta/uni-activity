import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

boot_script = """#!/data/data/com.termux/files/usr/bin/bash
termux-wake-lock

APP_DIR="/data/data/com.termux/files/home/uni-activity"

# 1. Start PostgreSQL
pg_ctl -D /data/data/com.termux/files/usr/var/lib/postgresql start

# 2. Start PHP-FPM
pkill php-fpm
php-fpm

# 3. Start Nginx
pkill nginx
nginx

# 4. Start Redis
pkill redis-server
redis-server --daemonize yes

# 5. Start Laravel Queue Worker
pkill -f 'artisan queue'
nohup php ${APP_DIR}/artisan queue:work > ${APP_DIR}/storage/logs/queue.log 2>&1 &

# 6. Start Laravel Reverb
pkill -f 'artisan reverb'
nohup php ${APP_DIR}/artisan reverb:start --host=0.0.0.0 --port=8082 > ${APP_DIR}/storage/logs/reverb.log 2>&1 &

# 7. Start Cloudflared in Ubuntu
pkill -f 'cloudflared'
nohup proot-distro login ubuntu -- bash -c "cloudflared tunnel --url http://127.0.0.1:8080 > ${APP_DIR}/cloudflared.log 2>&1" &

# 8. Start AI Scan Service (InsightFace on Port 8001)
pkill -f 'ai_service/server.py'
nohup proot-distro login ubuntu -- bash -c "cd /data/data/com.termux/files/home/uni-activity/ai_service && /root/ai_project/venv/bin/python server.py > server.log 2>&1" &

# 9. Wait for Cloudflared and update URL
python ${APP_DIR}/py/start_cf_ubuntu.py

# 10. Start Monitor Server (Port 9999)
pkill -f 'monitor_server.py'
nohup python ${APP_DIR}/py/monitor_server.py </dev/null > ${APP_DIR}/monitor.log 2>&1 &
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace")

sftp = client.open_sftp()
with sftp.file("/data/data/com.termux/files/home/.termux/boot/start_server.sh", 'w') as f:
    f.write(boot_script)
sftp.close()

run_cmd("chmod +x ~/.termux/boot/start_server.sh")

client.close()
print("Boot script updated successfully.")
