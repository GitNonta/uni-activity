import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

script = '''
APP_DIR="/data/data/com.termux/files/home/uni-activity"
URL=$(proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels | grep -o '"public_url":"[^"]*"' | head -1 | cut -d '"' -f 4)
if [ -n "$URL" ]; then
    sed -i "s|^LINE_CALLBACK_URL=.*|LINE_CALLBACK_URL=${URL}/line/callback|g" ${APP_DIR}/.env
    echo "Updated to ${URL}/line/callback"
    cd ${APP_DIR} && php artisan config:cache
fi
'''

stdin, stdout, stderr = client.exec_command(script)
print("OUT:", stdout.read().decode())
print("ERR:", stderr.read().decode())
client.close()
