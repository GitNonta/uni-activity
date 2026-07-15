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

print("--- 1. Starting Ngrok using proot-distro ---")
# Kill existing ngrok processes
run_cmd("pkill ngrok")
# Run proot-distro in background WITH authtoken
run_cmd(f"nohup proot-distro login ubuntu -- ngrok http 8080 --authtoken {ngrok_token} > ngrok_ubuntu.log 2>&1 &")

time.sleep(8)

print("--- 2. Fetching Ngrok URL ---")
out = run_cmd("proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels")
public_url = None
try:
    data = json.loads(out)
    public_url = data['tunnels'][0]['public_url']
except Exception as e:
    print("Error parsing Ngrok JSON:", e)
    print("Curl Output:", out)
    print("Log Output:", run_cmd("cat ngrok_ubuntu.log"))

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
    
    # Kill the windows one if it exists
    import subprocess
    subprocess.run("taskkill /F /IM ngrok.exe", shell=True, capture_output=True)
    print("Killed Windows Ngrok. The mobile one is active.")
else:
    print("Failed to get Ngrok URL.")

client.close()
