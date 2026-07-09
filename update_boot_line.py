import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

new_script = '''#!/data/data/com.termux/files/usr/bin/bash
termux-wake-lock

APP_DIR="/data/data/com.termux/files/home/uni-activity"

pg_ctl -D /data/data/com.termux/files/usr/var/lib/postgresql start
pkill php-fpm
php-fpm
pkill -f 'cloudflared'
# Start Cloudflare tunnel
nohup proot-distro login ubuntu -- bash -c "cloudflared tunnel --url http://127.0.0.1:8080 > /data/data/com.termux/files/home/uni-activity/cloudflared.log 2>&1" &
echo "Cloudflare Tunnel started"

# Update .env with new CF URL
python ${APP_DIR}/start_cf_ubuntu.py

pkill -f 'artisan queue'
nohup php ${APP_DIR}/artisan queue:work > ${APP_DIR}/storage/logs/queue.log 2>&1 &

pkill -9 -f 'artisan reverb'
nohup php ${APP_DIR}/artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > ${APP_DIR}/storage/logs/reverb.log 2>&1 &

pkill -9 -f 'monitor_server.py'
nohup python ${APP_DIR}/monitor_server.py </dev/null > ${APP_DIR}/monitor.log 2>&1 &
echo "Monitor server started on port 9999"
'''

sftp = client.open_sftp()
with sftp.file('/data/data/com.termux/files/home/.termux/boot/start_server.sh', 'w') as f:
    f.write(new_script)
sftp.close()
client.close()
print("Updated successfully!")
