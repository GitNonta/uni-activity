import os
import paramiko
import subprocess

host = '192.168.1.222'
port = 8022
user = 'u0_a175'
password = '2345678A'
remote_base = '/data/data/com.termux/files/home/uni-activity'

def get_changed_files():
    result = subprocess.run(['git', 'diff', 'HEAD~1', '--name-only'], capture_output=True, text=True)
    files = []
    for line in result.stdout.split('\n'):
        if line.strip():
            files.append(line.strip())
    return files

files_to_sync = get_changed_files()
# Also add composer.json and composer.lock just in case
if 'composer.json' not in files_to_sync:
    files_to_sync.append('composer.json')
if 'composer.lock' not in files_to_sync:
    files_to_sync.append('composer.lock')

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
print(f"Connecting to {host}...")
client.connect(host, port, user, password, timeout=10)
sftp = client.open_sftp()

# Allow deploying any file that has been modified
files_to_sync.extend([
    'app/Events/MessageSent.php',
    'app/Events/MessageDeleted.php',
    'app/Events/MessageEdited.php',
    'app/Events/ChatDeleted.php',
    'app/Http/Controllers/ChatController.php',
    'app/Http/Controllers/Admin/AdminInboxController.php',
    'app/Repositories/ChatRepository.php',
    'app/Jobs/SyncToCassandra.php',
    'routes/channels.php'
])
# Remove duplicates
files_to_sync = list(set(files_to_sync))

for file_path in files_to_sync:
    local_path = file_path
    if not os.path.exists(local_path):
        continue
    remote_path = f"{remote_base}/{file_path}"
    
    # Ensure remote directory exists
    remote_dir = os.path.dirname(remote_path)
    if remote_dir and remote_dir != remote_base:
        stdin, stdout, stderr = client.exec_command(f'mkdir -p {remote_dir}')
        stdout.channel.recv_exit_status()
    
    print(f"Uploading {local_path} to {remote_path}")
    sftp.put(local_path, remote_path)

sftp.close()

commands = [
    f'cd {remote_base} && php -d memory_limit=-1 $(which composer) update --no-dev --optimize-autoloader --ignore-platform-reqs',
    f'cd {remote_base} && php artisan migrate --force',
    f'cd {remote_base} && php artisan config:clear',
    f'cd {remote_base} && php artisan cache:clear',
    f'cd {remote_base} && php artisan config:cache',
    f'cd {remote_base} && php artisan view:clear',
    'killall php-fpm 2>/dev/null',
    'php-fpm',
    "pkill -f 'artisan queue:work' || true",
    "pkill -f 'artisan reverb' || true",
    f"cd {remote_base} && nohup php artisan reverb:start --host=0.0.0.0 --port=8082 </dev/null > storage/logs/reverb.log 2>&1 &"
]

for cmd in commands:
    print(f'Running: {cmd}')
    stdin, stdout, stderr = client.exec_command(cmd)
    
    # Wait for the command to finish
    exit_status = stdout.channel.recv_exit_status()
    
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out:
        print(out)
    if err:
        print(err)
        
client.close()
print("Deployment and upgrade complete!")
