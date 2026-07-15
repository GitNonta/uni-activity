import paramiko, os

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)
sftp = client.open_sftp()

APP_DIR = '/data/data/com.termux/files/home/uni-activity'

print("Deploying Python Server...")
sftp.put(r'd:\projects\uni-activity\py\monitor_server.py', f'{APP_DIR}/py/monitor_server.py')

print("Deploying Laravel Middleware...")
sftp.put(r'd:\projects\uni-activity\app\Http\Middleware\RequestInspectorMiddleware.php', f'{APP_DIR}/app/Http/Middleware/RequestInspectorMiddleware.php')
sftp.put(r'd:\projects\uni-activity\bootstrap\app.php', f'{APP_DIR}/bootstrap/app.php')

print("Deploying React Frontend...")
REMOTE_BASE = f'{APP_DIR}/monitor-ui/dist'
LOCAL_BASE = r'd:\projects\uni-activity\monitor-ui\dist'

def upload_dir(local_dir, remote_dir):
    try:
        sftp.stat(remote_dir)
    except FileNotFoundError:
        sftp.mkdir(remote_dir)
    for item in os.listdir(local_dir):
        local_path = os.path.join(local_dir, item)
        remote_path = remote_dir + '/' + item
        if os.path.isdir(local_path):
            try:
                sftp.stat(remote_path)
            except FileNotFoundError:
                sftp.mkdir(remote_path)
            upload_dir(local_path, remote_path)
        else:
            sftp.put(local_path, remote_path)

upload_dir(LOCAL_BASE, REMOTE_BASE)

sftp.close()

print("Restarting monitor_server...")
client.exec_command('pkill -9 -f monitor_server.py')
import time; time.sleep(1)
client.exec_command(f'nohup python {APP_DIR}/py/monitor_server.py </dev/null > {APP_DIR}/monitor.log 2>&1 &')

client.close()
print('Deployment Complete!')
