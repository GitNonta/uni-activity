import os
import time
import re
import subprocess

LOG_FILE = "/data/data/com.termux/files/home/uni-activity/cloudflared.log"
ENV_FILE = "/data/data/com.termux/files/home/uni-activity/.env"

# Wait for URL in log
url = None
print("Waiting for Cloudflare Tunnel URL...")
for i in range(30):
    if os.path.exists(LOG_FILE):
        with open(LOG_FILE, "r") as f:
            content = f.read()
            match = re.search(r'(https://[a-zA-Z0-9-]+\.trycloudflare\.com)', content)
            if match:
                url = match.group(1)
                break
    time.sleep(1)

if url:
    print(f"Found URL: {url}")
    # Update .env
    with open(ENV_FILE, "r") as f:
        env_lines = f.readlines()
    
    with open(ENV_FILE, "w") as f:
        for line in env_lines:
            if line.startswith("APP_URL="):
                f.write(f"APP_URL={url}\n")
            elif line.startswith("LINE_CALLBACK_URL="):
                f.write(f"LINE_CALLBACK_URL={url}/line/callback\n")
            else:
                f.write(line)
    
    print("Updated .env with Cloudflare URL")
    
    # Restart Reverb and Vite (if any background scripts depend on APP_URL)
    # usually start_server.sh handles queue and reverb, but let's clear config cache
    subprocess.run(["php", "/data/data/com.termux/files/home/uni-activity/artisan", "config:cache"])
    subprocess.run(["php", "/data/data/com.termux/files/home/uni-activity/artisan", "route:cache"])
    subprocess.run(["php", "/data/data/com.termux/files/home/uni-activity/artisan", "view:cache"])
    
else:
    print("Failed to get Cloudflare Tunnel URL within 30 seconds.")
