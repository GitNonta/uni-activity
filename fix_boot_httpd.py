import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

script = '''
sed -i "s|pkill nginx|pkill httpd\\npkill nginx|g" /data/data/com.termux/files/home/.termux/boot/start_server.sh
'''

client.exec_command(script)
client.close()
