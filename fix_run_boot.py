import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

boot_script = """#!/data/data/com.termux/files/usr/bin/bash
termux-wake-lock

pg_ctl -D /data/data/com.termux/files/usr/var/lib/postgresql start
pkill php-fpm
php-fpm
pkill nginx
nginx

APP_DIR="/data/data/com.termux/files/home/uni-activity"
pkill -f 'artisan queue'
nohup php ${APP_DIR}/artisan queue:work > ${APP_DIR}/storage/logs/queue.log 2>&1 &
pkill -f 'artisan reverb'
nohup php ${APP_DIR}/artisan reverb:start --host=0.0.0.0 --port=8082 > ${APP_DIR}/storage/logs/reverb.log 2>&1 &

pkill ngrok
nohup proot-distro login ubuntu -- ngrok http 8080 --config=/root/ngrok.yml > ${APP_DIR}/ngrok_ubuntu.log 2>&1 &

sleep 10
URL=$(proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels | grep -o '\"public_url\":\"[^\"]*\"' | head -1 | cut -d '\"' -f 4)

if [ -n "$URL" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=${URL}|g" ${APP_DIR}/.env
    cd ${APP_DIR} && php artisan config:clear
fi
"""

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

sftp = client.open_sftp()
with sftp.file("/data/data/com.termux/files/home/.termux/boot/start_server.sh", 'w') as f:
    f.write(boot_script)
sftp.close()

run_cmd("bash ~/.termux/boot/start_server.sh")

client.close()
