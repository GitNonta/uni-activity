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
cd ${APP_DIR}
php artisan queue:work --queue=default,line-notifications --stop-when-empty
'''

stdin, stdout, stderr = client.exec_command(script)
print("OUT:", stdout.read().decode())
print("ERR:", stderr.read().decode())
client.close()
