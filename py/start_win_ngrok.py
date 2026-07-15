import subprocess
import time
import json
import urllib.request
import re
import paramiko

# 1. Start Ngrok on Windows pointing to 192.168.1.222:8080
print("Starting Ngrok on Windows...")
subprocess.run("taskkill /F /IM ngrok.exe", shell=True, capture_output=True)
proc = subprocess.Popen(["ngrok", "http", "192.168.1.222:8080"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

time.sleep(5)

# 2. Get the Ngrok URL
print("Fetching Ngrok URL...")
public_url = None
try:
    req = urllib.request.Request("http://127.0.0.1:4040/api/tunnels")
    with urllib.request.urlopen(req) as response:
        data = json.loads(response.read().decode())
        if len(data['tunnels']) > 0:
            public_url = data['tunnels'][0]['public_url']
except Exception as e:
    print("Error fetching URL:", e)

if not public_url:
    print("Failed to get Ngrok URL from Windows.")
    exit(1)

print(f"Success! Ngrok URL: {public_url}")

# 3. Update the Termux Server .env
HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

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

stdin, stdout, stderr = client.exec_command(f"cd {APP_DIR} && php artisan config:clear")
print(stdout.read().decode())
client.close()

print("Done! You can now access the app securely.")
