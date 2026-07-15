import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

run_cmd("sed -i 's|^APP_URL=.*|APP_URL=https://4f15-171-97-255-47.ngrok-free.app|g' /data/data/com.termux/files/home/uni-activity/.env")
print(run_cmd("cd /data/data/com.termux/files/home/uni-activity && php artisan config:clear"))

client.close()
