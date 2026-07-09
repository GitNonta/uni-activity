import paramiko
import time

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
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

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

        location = /favicon.ico {{ access_log off; log_not_found off; }}
        location = /robots.txt  {{ access_log off; log_not_found off; }}

        error_page 404 /index.php;

        location /app/ {{
            if ($http_upgrade !~* "websocket") {{
                return 426;
            }}
            proxy_pass http://127.0.0.1:8082;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host $host;
        }}

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

print("--- Fixing Nginx Config ---")
sftp = client.open_sftp()
with sftp.file("/data/data/com.termux/files/usr/etc/nginx/nginx.conf", 'w') as f:
    f.write(nginx_config)
sftp.close()

print("--- Restarting Nginx ---")
run_cmd("nginx -t")
run_cmd("nginx -s reload")

print("--- Restarting Reverb ---")
run_cmd("pkill -f 'artisan reverb'")
run_cmd(f"cd {APP_DIR} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > storage/logs/reverb.log 2>&1 &")

time.sleep(2)
print("Done!")
client.close()
