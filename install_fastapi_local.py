import paramiko, os

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

sftp = client.open_sftp()

# Upload only pure-python wheels (no platform-specific)
wheel_dir = r'd:\projects\uni-activity\pip_wheels'
for f in sorted(os.listdir(wheel_dir)):
    if f.endswith('.whl') and 'win_amd64' not in f and 'win32' not in f and 'manylinux' not in f:
        local_path = os.path.join(wheel_dir, f)
        remote_path = '/data/data/com.termux/files/home/pip_wheels/' + f
        sftp.put(local_path, remote_path)
        print(f'Uploaded: {f}')

sftp.close()

# Install all
stdin, stdout, stderr = client.exec_command(
    'pip install --no-index --find-links=/data/data/com.termux/files/home/pip_wheels '
    '"fastapi==0.103.2" "uvicorn==0.23.2" websockets anyio sniffio h11 click starlette typing-extensions pydantic annotated-types 2>&1'
)
print(stdout.read().decode('utf-8'))
client.close()
