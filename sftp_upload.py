import paramiko
import os

host = '192.168.1.222'
port = 8022
user = 'u0_a175'
password = '2345678A'
remote_base = '/data/data/com.termux/files/home/uni-activity'
local_base = 'd:/projects/uni-activity'

files_to_sync = [
    'public/css/app.css',
    'resources/views/layouts/app.blade.php',
    'resources/views/activities/index.blade.php'
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(host, port, user, password, timeout=10)

sftp = client.open_sftp()

for file_path in files_to_sync:
    local_path = os.path.join(local_base, file_path)
    remote_path = f"{remote_base}/{file_path}"
    print(f"Uploading {local_path} to {remote_path}")
    sftp.put(local_path, remote_path)

sftp.close()

commands = [
    f'cd {remote_base} && php artisan config:clear',
    f'cd {remote_base} && php artisan config:cache',
    'killall php-fpm 2>/dev/null',
    'php-fpm'
]

for cmd in commands:
    print(f'Running: {cmd}')
    stdin, stdout, stderr = client.exec_command(cmd)
    print(stdout.read().decode())
    print(stderr.read().decode())

client.close()
