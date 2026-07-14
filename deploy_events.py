import paramiko
import os

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A')

sftp = client.open_sftp()

files = [
    'MessageSent.php',
    'MessageEdited.php',
    'MessageDeleted.php',
    'ChatDeleted.php',
    'StudentAlertsUpdated.php',
    'ChatMessageEvent.php'
]

local_dir = 'd:/projects/uni-activity/app/Events/'
remote_dir = '/data/data/com.termux/files/home/uni-activity/app/Events/'

for f in files:
    sftp.put(os.path.join(local_dir, f), os.path.join(remote_dir, f))
    print(f"Uploaded {f}")

sftp.close()
client.close()
print("Done!")
