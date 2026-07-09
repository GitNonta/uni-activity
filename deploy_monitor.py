import paramiko, os

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)
sftp = client.open_sftp()

# Push monitor_server.py
sftp.put(r'd:\projects\uni-activity\monitor_server.py', '/data/data/com.termux/files/home/uni-activity/monitor_server.py')
print('Pushed monitor_server.py')

# Push React dist
REMOTE_BASE = '/data/data/com.termux/files/home/uni-activity/monitor-ui/dist'
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
            print(f'  + {remote_path}')

upload_dir(LOCAL_BASE, REMOTE_BASE)

sftp.close()

# Kill old monitor and restart with new server
client.exec_command('pkill -9 -f monitor_server.py')
client.exec_command('pkill -9 -f monitor_fastapi')
import time; time.sleep(1)
client.exec_command('nohup python /data/data/com.termux/files/home/uni-activity/monitor_server.py </dev/null > /data/data/com.termux/files/home/uni-activity/monitor.log 2>&1 &')

time.sleep(3)
stdin, stdout, stderr = client.exec_command('curl -s http://127.0.0.1:9999/api/stats | head -c 100')
print('Test API:', stdout.read().decode('utf-8'))

client.close()
print('Done!')
