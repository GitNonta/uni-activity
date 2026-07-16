import os
import time
import re
import subprocess
import urllib.request
import json
import base64

LOG_FILE = "/data/data/com.termux/files/home/uni-activity/cloudflared.log"
ENV_FILE = "/data/data/com.termux/files/home/uni-activity/.env"

def update_github_active_url(new_url):
    pat = None
    if os.path.exists(ENV_FILE):
        with open(ENV_FILE, "r") as f:
            for line in f:
                if line.startswith("GITHUB_PAT="):
                    pat = line.split("=", 1)[1].strip()
                    if (pat.startswith('"') and pat.endswith('"')) or (pat.startswith("'") and pat.endswith("'")):
                        pat = pat[1:-1]
                    break
    
    if not pat:
        print("GITHUB_PAT not found in .env, skipping GitHub Pages redirect update.")
        return
        
    owner = "GitNonta"
    repo = "uni-activity"
    path = "docs/active_url.json"
    api_url = f"https://api.github.com/repos/{owner}/{repo}/contents/{path}"
    
    # 1. Get existing file sha
    sha = None
    try:
        req = urllib.request.Request(api_url)
        req.add_header("Authorization", f"token {pat}")
        req.add_header("Accept", "application/vnd.github.v3+json")
        req.add_header("User-Agent", "Termux-Monitor-Server")
        with urllib.request.urlopen(req, timeout=5) as response:
            res_data = json.loads(response.read().decode("utf-8"))
            sha = res_data.get("sha")
    except Exception as e:
        print(f"Failed to get existing file SHA from GitHub: {e}")
        
    # 2. Update/Create file
    try:
        content_dict = {"url": new_url}
        content_str = json.dumps(content_dict, indent=2)
        content_b64 = base64.b64encode(content_str.encode("utf-8")).decode("utf-8")
        
        payload = {
            "message": "chore: update active tunnel URL",
            "content": content_b64
        }
        if sha:
            payload["sha"] = sha
            
        req = urllib.request.Request(api_url, method="PUT")
        req.add_header("Authorization", f"token {pat}")
        req.add_header("Accept", "application/vnd.github.v3+json")
        req.add_header("Content-Type", "application/json")
        req.add_header("User-Agent", "Termux-Monitor-Server")
        
        data_bytes = json.dumps(payload).encode("utf-8")
        with urllib.request.urlopen(req, data=data_bytes, timeout=5) as response:
            if response.status == 200 or response.status == 201:
                print("Successfully updated active_url.json on GitHub Pages.")
            else:
                print(f"Failed to update file on GitHub: HTTP {response.status}")
    except Exception as e:
        print(f"Failed to update active_url.json on GitHub: {e}")

# Wait for URL in log
url = None
print("Waiting for Cloudflare Tunnel URL...")
for i in range(30):
    if os.path.exists(LOG_FILE):
        with open(LOG_FILE, "r") as f:
            content = f.read()
            matches = re.findall(r'(https://[a-zA-Z0-9-]+\.trycloudflare\.com)', content)
            if matches:
                url = matches[-1]
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
    
    # Update local active_url.json
    local_json_path = "/data/data/com.termux/files/home/uni-activity/docs/active_url.json"
    try:
        with open(local_json_path, "w") as f:
            json.dump({"url": url}, f, indent=2)
        print("Updated local active_url.json")
    except Exception as e:
        print(f"Failed to update local active_url.json: {e}")

    # Update GitHub Pages active_url.json
    update_github_active_url(url)
    
    # Restart Reverb and Vite (if any background scripts depend on APP_URL)
    # usually start_server.sh handles queue and reverb, but let's clear config cache
    subprocess.run(["php", "/data/data/com.termux/files/home/uni-activity/artisan", "config:cache"])
    subprocess.run(["php", "/data/data/com.termux/files/home/uni-activity/artisan", "route:cache"])
    subprocess.run(["php", "/data/data/com.termux/files/home/uni-activity/artisan", "view:cache"])
    
else:
    print("Failed to get Cloudflare Tunnel URL within 30 seconds.")
