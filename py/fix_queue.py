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
pkill -f 'artisan queue'
nohup php ${APP_DIR}/artisan queue:work --queue=default,line-notifications > ${APP_DIR}/storage/logs/queue.log 2>&1 &
echo "Queue restarted with line-notifications queue enabled"

sed -i "s|queue:work >|queue:work --queue=default,line-notifications >|g" /data/data/com.termux/files/home/.termux/boot/start_server.sh
echo "start_server.sh updated"
'''

stdin, stdout, stderr = client.exec_command(script)
print("OUT:", stdout.read().decode())
client.close()
