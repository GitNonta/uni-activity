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
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

print("--- 1. Updating Ngrok Config ---")
run_cmd("""proot-distro login ubuntu -- bash -c 'cat << EOF > /root/ngrok.yml
version: "2"
authtoken: 2uFi3a4jZKYC8KUyULMHUuYXinm_5r7ukz2z1jiQXsJxEVgxS
web_addr: 0.0.0.0:4040
region: ap
EOF'""")

print("--- 2. Restarting Ngrok ---")
run_cmd("pkill ngrok")
run_cmd(f"nohup proot-distro login ubuntu -- ngrok http 8080 --config=/root/ngrok.yml > {APP_DIR}/ngrok_ubuntu.log 2>&1 &")

import time
time.sleep(8)

print("--- 3. Fetching New URL and Updating .env ---")
out = run_cmd("proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels")
import json
public_url = None
try:
    # Filter proot warnings
    out_json = out.split("\n")[-1]
    data = json.loads(out_json)
    public_url = data['tunnels'][0]['public_url']
except Exception as e:
    print("Error parsing Ngrok JSON:", e)
    print("Curl Output:", out)

if public_url:
    print(f"Success! New Ngrok URL: {public_url}")
    
    sftp = client.open_sftp()
    env_path = f"{APP_DIR}/.env"
    with sftp.file(env_path, 'r') as f:
        env_content = f.read().decode('utf-8')
    
    import re
    env_content = re.sub(r'APP_URL=.*', f'APP_URL={public_url}', env_content)

    with sftp.file(env_path, 'w') as f:
        f.write(env_content)
    sftp.close()
    
    run_cmd(f"cd {APP_DIR} && php artisan config:clear")
else:
    print("Failed to get Ngrok URL.")

client.close()
