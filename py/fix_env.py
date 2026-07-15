import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

commands = [
    'sed -i "s/APP_DEBUG=true/APP_DEBUG=false/g" /data/data/com.termux/files/home/uni-activity/.env',
    'sed -i "s/APP_ENV=local/APP_ENV=production/g" /data/data/com.termux/files/home/uni-activity/.env',
    'cd /data/data/com.termux/files/home/uni-activity && php artisan config:clear',
    'cd /data/data/com.termux/files/home/uni-activity && php artisan config:cache',
    'killall php-fpm 2>/dev/null',
    'php-fpm'
]

for cmd in commands:
    print(f'Running: {cmd}')
    stdin, stdout, stderr = client.exec_command(cmd)
    print(stdout.read().decode())
    print(stderr.read().decode())

client.close()
