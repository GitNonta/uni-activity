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
    return stdout.read().decode(errors="replace")

sftp = client.open_sftp()

print("--- 1. Modifying Nginx to proxy WebSockets ---")
nginx_content = ""
with sftp.file(NGINX_CONF, 'r') as f:
    nginx_content = f.read().decode('utf-8')

proxy_block = """
        location /app/ {
            proxy_pass http://127.0.0.1:8081;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host $host;
        }
"""

if "location /app/" not in nginx_content:
    # Insert before location ~ \.php$
    nginx_content = nginx_content.replace("location ~ \\.php$ {", proxy_block + "\n        location ~ \\.php$ {")
    with sftp.file(NGINX_CONF, 'w') as f:
        f.write(nginx_content)
    print("Added /app/ WebSocket proxy block to Nginx")
else:
    print("WebSocket proxy block already exists in Nginx")

print("--- 2. Updating .env REVERB_PORT to 8081 ---")
env_path = f"{APP_DIR}/.env"
env_content = ""
with sftp.file(env_path, 'r') as f:
    env_content = f.read().decode('utf-8')

env_content = re.sub(r'REVERB_PORT=.*', 'REVERB_PORT=8081', env_content)
with sftp.file(env_path, 'w') as f:
    f.write(env_content)

sftp.close()

print("--- 3. Restarting Nginx ---")
run_cmd("nginx -s reload || nginx")

print("--- 4. Starting Reverb ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8081 > storage/logs/reverb.log 2>&1 &")

client.close()
print("Done! WebSockets should now work.")
