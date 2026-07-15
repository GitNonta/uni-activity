import paramiko
import time
import json
import re

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

local_env_path = r"d:\projects\uni-activity\.env"
ngrok_token = None
with open(local_env_path, "r", encoding="utf-8") as f:
    for line in f:
        if line.startswith("NGROK_AUTHTOKEN="):
            ngrok_token = line.split("=", 1)[1].strip()
            break

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace")

print("--- 1. Installing Ngrok in Ubuntu ---")
ubuntu_cmd = f"""proot-distro login ubuntu -- bash -c '
apt-get update && apt-get install -y wget curl unzip && \\
if [ ! -f /usr/local/bin/ngrok ]; then \\
    wget -q https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm64.tgz -O ngrok.tgz && \\
    tar -xvzf ngrok.tgz -C /usr/local/bin && \\
    rm ngrok.tgz; \\
fi && \\
ngrok config add-authtoken {ngrok_token} && \\
pkill ngrok || true && \\
nohup ngrok http 8080 > /dev/null 2>&1 &
'"""
run_cmd(ubuntu_cmd)

time.sleep(6)

print("--- 2. Fetching Ngrok URL from Ubuntu ---")
out = run_cmd("proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels")
public_url = None
try:
    data = json.loads(out)
    public_url = data['tunnels'][0]['public_url']
except Exception as e:
    print("Error parsing Ngrok JSON:", e)
    print("Curl Output:", out)

if public_url:
    print(f"Success! Ngrok URL: {public_url}")
    
    print("--- 3. Updating .env on Termux ---")
    sftp = client.open_sftp()
    env_path = f"{APP_DIR}/.env"
    with sftp.file(env_path, 'r') as f:
        env_content = f.read().decode('utf-8')
    
    env_content = re.sub(r'APP_URL=.*', f'APP_URL={public_url}', env_content)
    if "ASSET_URL=" in env_content:
        env_content = re.sub(r'ASSET_URL=.*', f'ASSET_URL={public_url}', env_content)
    else:
        env_content += f"\nASSET_URL={public_url}\n"

    with sftp.file(env_path, 'w') as f:
        f.write(env_content)
    sftp.close()
    
    run_cmd(f"cd {APP_DIR} && php artisan config:clear")
else:
    print("Failed to get Ngrok URL.")

client.close()
