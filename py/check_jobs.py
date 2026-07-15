import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

script = '''
cd /data/data/com.termux/files/home/uni-activity && php artisan tinker --execute="echo DB::table('jobs')->count();"
'''

stdin, stdout, stderr = client.exec_command(script)
print("JOBS:", stdout.read().decode())
client.close()
