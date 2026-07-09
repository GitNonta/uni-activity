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

print("--- Optimizing Nginx Config ---")
nginx_config = f"""
worker_processes  auto;
events {{
    worker_connections  1024;
}}
http {{
    include       mime.types;
    default_type  application/octet-stream;
    sendfile        on;
    tcp_nopush      on;
    tcp_nodelay     on;
    keepalive_timeout  65;
    
    gzip on;
    gzip_vary on;
    gzip_min_length 10240;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript;
    gzip_disable "MSIE [1-6]\\.";

    map $http_upgrade $connection_upgrade {{
        default upgrade;
        ''      close;
    }}

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

        # Cache static assets
        location ~* \\.(?:css|js|map|jpe?g|gif|png|webp|svg|woff2?|eot|ttf|otf|ico)$ {{
            expires 6M;
            access_log off;
            add_header Cache-Control "public";
        }}

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

sftp = client.open_sftp()
with sftp.file("/data/data/com.termux/files/usr/etc/nginx/nginx.conf", 'w') as f:
    f.write(nginx_config)

print("--- Optimizing PHP-FPM ---")
run_cmd("sed -i 's/pm.max_children = .*/pm.max_children = 10/' /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf")
run_cmd("sed -i 's/pm.start_servers = .*/pm.start_servers = 2/' /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf")
run_cmd("sed -i 's/pm.min_spare_servers = .*/pm.min_spare_servers = 1/' /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf")
run_cmd("sed -i 's/pm.max_spare_servers = .*/pm.max_spare_servers = 4/' /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf")

print("--- Refactoring boot script ---")
boot_script = f"""#!/data/data/com.termux/files/usr/bin/bash
termux-wake-lock

APP_DIR="{APP_DIR}"

pg_ctl -D /data/data/com.termux/files/usr/var/lib/postgresql start
pkill php-fpm
php-fpm
pkill nginx
nginx

pkill ngrok
nohup proot-distro login ubuntu -- ngrok http 8080 --config=/root/ngrok.yml > ${{APP_DIR}}/ngrok_ubuntu.log 2>&1 &

sleep 10
URL=$(proot-distro login ubuntu -- curl -s http://127.0.0.1:4040/api/tunnels | grep -o '"public_url":"[^"]*"' | head -1 | cut -d '"' -f 4)

if [ -n "$URL" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=${{URL}}|g" ${{APP_DIR}}/.env
    cd ${{APP_DIR}}
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

pkill -f 'artisan queue'
nohup php ${{APP_DIR}}/artisan queue:work > ${{APP_DIR}}/storage/logs/queue.log 2>&1 &

pkill -f 'artisan reverb'
nohup php ${{APP_DIR}}/artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > ${{APP_DIR}}/storage/logs/reverb.log 2>&1 &
"""

with sftp.file("/data/data/com.termux/files/home/.termux/boot/start_server.sh", 'w') as f:
    f.write(boot_script)
run_cmd("chmod +x /data/data/com.termux/files/home/.termux/boot/start_server.sh")
sftp.close()

print("--- Restarting Services to Apply Optimizations ---")
run_cmd("pkill php-fpm && php-fpm")
run_cmd("nginx -s reload")
run_cmd(f"cd {APP_DIR} && php artisan config:cache && php artisan route:cache && php artisan view:cache")
run_cmd(f"cd {APP_DIR} && php artisan optimize")
print("Done!")
client.close()
