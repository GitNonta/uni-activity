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

print("--- Restarting Reverb ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > storage/logs/reverb.log 2>&1 & sleep 2")

print("--- Fixing Boot Script ---")
run_cmd("sed -i 's/nohup php ${APP_DIR}\/artisan reverb:start/nohup php ${APP_DIR}\/artisan reverb:start --host=0.0.0.0 --port=8082 <\\/dev\\/null/g' ~/.termux/boot/start_server.sh")

print("--- Check Reverb ---")
print(run_cmd("netstat -tuln | grep 8082"))

client.close()
