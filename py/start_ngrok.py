import paramiko
import time
import re
import json

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

# ดึง Authtoken จากไฟล์ .env เครื่อง Local
local_env_path = r"d:\projects\uni-activity\.env"
ngrok_token = None
with open(local_env_path, "r", encoding="utf-8") as f:
    for line in f:
        if line.startswith("NGROK_AUTHTOKEN="):
            ngrok_token = line.split("=", 1)[1].strip()
            break

if not ngrok_token:
    print("Error: NGROK_AUTHTOKEN not found in local .env!")
    exit(1)

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace")

print("--- 1. Installing Ngrok on Termux ---")
# Check if ngrok is already installed
ngrok_check = run_cmd("which ngrok")
if not ngrok_check.strip():
    run_cmd("wget -q https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm64.tgz -O ngrok.tgz")
    run_cmd("tar -xvzf ngrok.tgz -C /data/data/com.termux/files/usr/bin")
    run_cmd("rm ngrok.tgz")
    run_cmd("chmod +x /data/data/com.termux/files/usr/bin/ngrok")

print("--- 2. Configuring Ngrok ---")
run_cmd(f"ngrok config add-authtoken {ngrok_token}")

print("--- 3. Starting Ngrok ---")
run_cmd("pkill ngrok")
run_cmd("nohup ngrok http 8080 > /dev/null 2>&1 &")

# Wait a few seconds for ngrok to start and connect
time.sleep(5)

print("--- 4. Getting Ngrok URL ---")
out = run_cmd("curl -s http://127.0.0.1:4040/api/tunnels")
try:
    data = json.loads(out)
    public_url = data['tunnels'][0]['public_url']
    print(f"Ngrok URL: {public_url}")
    
    print("--- 5. Updating APP_URL on Termux ---")
    sftp = client.open_sftp()
    env_path = f"{APP_DIR}/.env"
    with sftp.file(env_path, 'r') as f:
        env_content = f.read().decode('utf-8')
    
    env_content = re.sub(r'APP_URL=.*', f'APP_URL={public_url}', env_content)
    # Also update ASSET_URL just in case for mixed content
    if "ASSET_URL=" in env_content:
        env_content = re.sub(r'ASSET_URL=.*', f'ASSET_URL={public_url}', env_content)
    else:
        env_content += f"\nASSET_URL={public_url}\n"

    with sftp.file(env_path, 'w') as f:
        f.write(env_content)
    sftp.close()
    
    print("--- 6. Clearing Cache ---")
    run_cmd(f"cd {APP_DIR} && php artisan config:clear")
    
except Exception as e:
    print("Failed to parse Ngrok URL:", e)
    print("Curl output:", out)

client.close()
