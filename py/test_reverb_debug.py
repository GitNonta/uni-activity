import paramiko
import time

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

print(run_cmd("pkill -f 'artisan reverb'"))
print(run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 --debug > storage/logs/reverb.log 2>&1 &"))
time.sleep(2)
print("Curling...")
print(run_cmd("curl -v http://127.0.0.1:8082/app/uni-chat-key"))
time.sleep(2)
print("Logs:")
print(run_cmd(f"tail -n 20 {APP_DIR}/storage/logs/reverb.log"))

client.close()
