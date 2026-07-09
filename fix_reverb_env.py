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

reverb_env = """
BROADCAST_DRIVER=reverb
REVERB_APP_ID=uni-chat
REVERB_APP_KEY=uni-chat-key
REVERB_APP_SECRET=uni-chat-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8082
REVERB_SCHEME=http
"""

print("--- Updating .env ---")
run_cmd(f"sed -i 's/^BROADCAST_DRIVER=.*/BROADCAST_DRIVER=reverb/g' {APP_DIR}/.env")
run_cmd(f"echo '{reverb_env}' >> {APP_DIR}/.env")

print("--- Caching Config ---")
run_cmd(f"cd {APP_DIR} && php artisan config:cache")

print("--- Restarting Reverb ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > storage/logs/reverb.log 2>&1 &")

time.sleep(2)
print("Done!")
client.close()
