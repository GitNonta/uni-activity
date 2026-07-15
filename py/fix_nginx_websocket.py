import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

print("--- Fixing Nginx Config ---")
run_cmd("sed -i 's/proxy_set_header Upgrade $http_upgrade;/proxy_set_header Upgrade \"websocket\";/g' /data/data/com.termux/files/usr/etc/nginx/nginx.conf")

print("--- Restarting Nginx ---")
run_cmd("nginx -s reload")

print("--- Restarting Reverb ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd("cd /data/data/com.termux/files/home/uni-activity && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > storage/logs/reverb.log 2>&1 &")

time.sleep(2)
print("Done!")
client.close()
