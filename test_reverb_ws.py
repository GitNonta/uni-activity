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

print("--- Restarting Reverb ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 --debug > storage/logs/reverb.log 2>&1 &")
time.sleep(2)

print("--- Testing WS ---")
cmd = "curl -v -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H 'Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==' -H 'Sec-WebSocket-Version: 13' http://127.0.0.1:8082/app/uni-chat-key"
print(run_cmd(cmd))

time.sleep(1)
print("--- Checking Reverb Log ---")
print(run_cmd(f"tail -n 20 {APP_DIR}/storage/logs/reverb.log"))

client.close()
