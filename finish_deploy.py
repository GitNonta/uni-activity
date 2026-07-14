import paramiko
import time
import sys

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"
NGINX_CONF = "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"

def drain(shell, wait=2.0):
    time.sleep(wait)
    out = ""
    for _ in range(30):
        if shell.recv_ready():
            out += shell.recv(65536).decode("utf-8", errors="replace")
            time.sleep(0.5)
        else:
            break
    return out

def send_and_wait(shell, cmd, wait=3.0, wait_for_prompt=True):
    print(f">>> {cmd}")
    shell.send(cmd + "\n")
    
    out = ""
    if wait_for_prompt:
        # Wait until prompt returns (checking for prompt character like $ or ~)
        start_time = time.time()
        while time.time() - start_time < wait:
            if shell.recv_ready():
                chunk = shell.recv(65536).decode("utf-8", errors="replace")
                out += chunk
                sys.stdout.write(chunk)
                sys.stdout.flush()
                # If prompt is found in the last part of output
                if out.strip().endswith("$") or out.strip().endswith("#") or (out.strip().endswith("~") and not "cd" in cmd) or out.strip().endswith("="):
                    time.sleep(1) # wait a little more just in case
                    break
            else:
                time.sleep(0.5)
    else:
        out = drain(shell, wait)
        print(out)
    return out

def main():
    print("Connecting to SSH...")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)
    
    print("Uploading shell_logger.sh via SFTP...")
    sftp = client.open_sftp()
    try:
        sftp.put(r'd:\projects\uni-activity\scripts\shell_logger.sh', f"{APP_DIR}/scripts/shell_logger.sh")
        print("Uploaded shell_logger.sh successfully.")
    except Exception as e:
        print(f"Error uploading shell_logger.sh: {e}")
    finally:
        sftp.close()
    
    shell = client.invoke_shell(width=200, height=50)
    drain(shell, 2)
    
    print("--- 1. Running Composer Install ---")
    # Increase memory limit and run composer with --ignore-platform-reqs
    cmd = f"cd {APP_DIR} && php -d memory_limit=-1 $(which composer) install --no-dev --optimize-autoloader --ignore-platform-reqs"
    shell.send(cmd + "\n")
    
    # Wait for composer install (might take up to 10 mins on phone)
    start_time = time.time()
    out = ""
    while time.time() - start_time < 600:
        if shell.recv_ready():
            chunk = shell.recv(65536).decode("utf-8", errors="replace")
            out += chunk
            sys.stdout.write(chunk)
            sys.stdout.flush()
            if "Generating optimized autoload files" in chunk or "Nothing to install" in chunk or out.strip().endswith("~") or out.strip().endswith("$") or out.strip().endswith("="):
                break
        else:
            time.sleep(1)
            
    print("\n--- 2. Setting up Laravel ---")
    send_and_wait(shell, f"cd {APP_DIR} && php artisan key:generate", 15, False)
    send_and_wait(shell, f"cd {APP_DIR} && php artisan migrate --force", 20, False)
    send_and_wait(shell, f"cd {APP_DIR} && php artisan config:clear", 10, False)
    
    print("\n--- 2b. Registering Shell Logger ---")
    send_and_wait(shell, f"chmod +x {APP_DIR}/scripts/shell_logger.sh", 5, False)
    send_and_wait(shell, f"grep -q 'shell_logger.sh' ~/.bashrc || echo 'source {APP_DIR}/scripts/shell_logger.sh' >> ~/.bashrc", 5, False)
    send_and_wait(shell, f"touch ~/.zshrc && (grep -q 'shell_logger.sh' ~/.zshrc || (echo '' >> ~/.zshrc && echo 'source {APP_DIR}/scripts/shell_logger.sh' >> ~/.zshrc))", 5, False)
    
    print("\n--- 3. Fixing NGINX Config ---")
    # Backup existing
    send_and_wait(shell, f"cp {NGINX_CONF} {NGINX_CONF}.bak3", 5, False)
    
    # Write new nginx config
    nginx_config = f"""
worker_processes  1;
events {{
    worker_connections  1024;
}}
http {{
    include       mime.types;
    default_type  application/octet-stream;
    sendfile        on;
    keepalive_timeout  65;

    server {{
        listen       8080;
        server_name  localhost 192.168.1.222;
        root         {APP_DIR}/public;
        index        index.php index.html index.htm;
        charset      utf-8;

        location / {{
            try_files $uri $uri/ /index.php?$query_string;
        }}

        location /app/ {{
            proxy_pass http://127.0.0.1:8082;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }}

        location = /favicon.ico {{ access_log off; log_not_found off; }}
        location = /robots.txt  {{ access_log off; log_not_found off; }}

        error_page 404 /index.php;

        location ~ \\.php$ {{
            fastcgi_pass   unix:/data/data/com.termux/files/usr/var/run/php-fpm.sock;
            fastcgi_param  SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
            include        fastcgi_params;
        }}

        location ~ /\\.(?!well-known).* {{
            deny all;
        }}
    }}
}}
"""
    sftp = client.open_sftp()
    try:
        with sftp.file(NGINX_CONF, 'w') as f:
            f.write(nginx_config)
        print("Nginx config written successfully via SFTP.")
    except Exception as e:
        print(f"Error writing Nginx config: {e}")
    finally:
        sftp.close()
    
    print("\n--- 4. Restarting NGINX ---")
    send_and_wait(shell, "nginx -t", 5, False)
    send_and_wait(shell, "nginx -s reload || (nginx -s stop && sleep 2 && nginx)", 10, False)
    
    print("\n--- 5. Testing URL ---")
    out = send_and_wait(shell, "curl -sI http://localhost:8080/", 10, False)
    out2 = send_and_wait(shell, "curl -s http://localhost:8080/ | grep -i title | head -n 1", 10, False)
    
    print("\nDeployment Finish Script Completed.")
    
    shell.close()
    client.close()

if __name__ == "__main__":
    main()
