import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

print("--- NGINX CONFIG ---")
print(run_cmd("cat /data/data/com.termux/files/usr/etc/nginx/nginx.conf"))
print("--- PHP-FPM CONFIG ---")
print(run_cmd("cat /data/data/com.termux/files/usr/etc/php-fpm.d/www.conf | grep -v '^;' | grep -v '^$'"))
print("--- OPCACHE CONFIG ---")
print(run_cmd("php -i | grep opcache.enable"))
print("--- BOOT SCRIPT ---")
print(run_cmd("cat ~/.termux/boot/start_server.sh"))

client.close()
