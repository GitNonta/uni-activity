import paramiko
import json
import re
import urllib.request

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

print("--- 1. Fetching current Ngrok URL ---")
public_url = None
try:
    req = urllib.request.Request("http://192.168.1.222:4040/api/tunnels")
    with urllib.request.urlopen(req) as response:
        data = json.loads(response.read().decode())
        if len(data['tunnels']) > 0:
            public_url = data['tunnels'][0]['public_url']
except Exception as e:
    print("Error fetching URL:", e)

if not public_url:
    print("Could not get Ngrok URL.")
    exit(1)

print(f"Current Ngrok URL: {public_url}")

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace")

print("--- 2. Updating .env on Termux ---")
sftp = client.open_sftp()
env_path = f"{APP_DIR}/.env"
with sftp.file(env_path, 'r') as f:
    env_content = f.read().decode('utf-8')

# Update APP_URL
env_content = re.sub(r'APP_URL=.*', f'APP_URL={public_url}', env_content)

# Remove ASSET_URL so it uses relative paths
env_content = re.sub(r'ASSET_URL=.*\n?', '', env_content)

with sftp.file(env_path, 'w') as f:
    f.write(env_content)
sftp.close()

print("--- 3. Clearing Cache ---")
print(run_cmd(f"cd {APP_DIR} && php artisan config:clear"))

client.close()
print("Done! CORS issue fixed.")
