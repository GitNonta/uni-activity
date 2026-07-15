import paramiko
import re

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"
NGINX_CONF = "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    return out.strip() + "\n" + err.strip()

sftp = client.open_sftp()

print("--- 1. Fixing Storage Symlink ---")
run_cmd(f"rm -rf {APP_DIR}/public/storage")
run_cmd(f"cd {APP_DIR} && php artisan storage:link")
# Check if symlink is correct now
print(run_cmd(f"ls -la {APP_DIR}/public | grep storage"))

print("--- 2. Moving Reverb to 8082 ---")
env_path = f"{APP_DIR}/.env"
env_content = ""
with sftp.file(env_path, 'r') as f:
    env_content = f.read().decode('utf-8')

env_content = re.sub(r'REVERB_PORT=.*', 'REVERB_PORT=8082', env_content)
with sftp.file(env_path, 'w') as f:
    f.write(env_content)

nginx_content = ""
with sftp.file(NGINX_CONF, 'r') as f:
    nginx_content = f.read().decode('utf-8')
nginx_content = nginx_content.replace('proxy_pass http://127.0.0.1:8081;', 'proxy_pass http://127.0.0.1:8082;')
with sftp.file(NGINX_CONF, 'w') as f:
    f.write(nginx_content)

sftp.close()

run_cmd("nginx -s reload || nginx")

print("--- 3. Restarting Reverb on 8082 ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 > storage/logs/reverb.log 2>&1 &")

client.close()
print("All fixes applied!")
