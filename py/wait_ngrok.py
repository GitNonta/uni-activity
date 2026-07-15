import paramiko
import time
import json
import re

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
    return stdout.read().decode(errors="replace")

print("Restarting ngrok and logging to ngrok.log")
run_cmd("pkill ngrok")
run_cmd("nohup ngrok http 8080 --log=stdout > ngrok.log 2>&1 &")

public_url = None
for i in range(5):
    print(f"Waiting for ngrok... ({i+1})")
    time.sleep(3)
    out = run_cmd("curl -s http://127.0.0.1:4040/api/tunnels")
    try:
        data = json.loads(out)
        if len(data['tunnels']) > 0:
            public_url = data['tunnels'][0]['public_url']
            print(f"Success! Ngrok URL: {public_url}")
            break
    except:
        pass

if not public_url:
    print("Ngrok failed to connect. Check log:")
    print(run_cmd("cat ngrok.log"))
else:
    print("--- Updating APP_URL on Termux ---")
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
    
    print("--- Clearing Cache ---")
    run_cmd(f"cd {APP_DIR} && php artisan config:clear")

client.close()
