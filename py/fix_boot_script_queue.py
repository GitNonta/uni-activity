import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

script = '''#!/data/data/com.termux/files/usr/bin/bash
termux-wake-lock

APP_DIR="/data/data/com.termux/files/home/uni-activity"

pg_ctl -D /data/data/com.termux/files/usr/var/lib/postgresql start
pkill php-fpm
php-fpm
pkill nginx
nginx

pkill ngrok
nohup proot-distro login ubuntu -- ngrok http 8080 --config=/root/ngrok.yml > ${APP_DIR}/ngrok_ubuntu.log 2>&1 &

sleep 10
URL=$(proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels | grep -o '"public_url":"[^"]*"' | head -1 | cut -d '"' -f 4)

if [ -n "$URL" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=${URL}|g" ${APP_DIR}/.env
    sed -i "s|^LINE_CALLBACK_URL=.*|LINE_CALLBACK_URL=${URL}/line/callback|g" ${APP_DIR}/.env
    cd ${APP_DIR}
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

pkill -f 'artisan queue'
nohup php ${APP_DIR}/artisan queue:work --queue=default,line-notifications > ${APP_DIR}/storage/logs/queue.log 2>&1 &

pkill -f 'artisan reverb'
nohup php ${APP_DIR}/artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > ${APP_DIR}/storage/logs/reverb.log 2>&1 &
'''

sftp = client.open_sftp()
with sftp.file('/data/data/com.termux/files/home/.termux/boot/start_server.sh', 'w') as f:
    f.write(script)
sftp.close()

# Also run the queue worker now to clear pending jobs
client.exec_command('pkill -f "artisan queue"')
client.exec_command('cd /data/data/com.termux/files/home/uni-activity && nohup php artisan queue:work --queue=default,line-notifications > storage/logs/queue.log 2>&1 &')

client.close()
print("Boot script fixed and queue worker started manually.")
