import paramiko
import os
import builtins
import io

log_buffer = []

def print(*args, **kwargs):
    f = io.StringIO()
    builtins.print(*args, file=f, **kwargs)
    text = f.getvalue()
    log_buffer.append(text)
    builtins.print(text, end='')


HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

sftp = client.open_sftp()

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    stdout.channel.recv_exit_status() # WAIT for command to finish!
    return stdout.read().decode()

def upload_file(local_path, remote_path):
    print(f"Uploading {local_path} to {remote_path}...")
    sftp.put(local_path, remote_path)

def upload_dir(local_dir, remote_dir):
    run_cmd(f"mkdir -p {remote_dir}")
    for item in os.listdir(local_dir):
        local_path = os.path.join(local_dir, item)
        remote_path = f"{remote_dir}/{item}"
        if os.path.isfile(local_path):
            upload_file(local_path, remote_path)
        elif os.path.isdir(local_path):
            upload_dir(local_path, remote_path)

print("--- Uploading modified Blade files ---")
upload_file(
    r"d:\projects\uni-activity\resources\views\auth\verify-login-otp.blade.php",
    f"{APP_DIR}/resources/views/auth/verify-login-otp.blade.php"
)
upload_file(
    r"d:\projects\uni-activity\resources\views\auth\verify-otp.blade.php",
    f"{APP_DIR}/resources/views/auth/verify-otp.blade.php"
)

print("--- Uploading public/build ---")
run_cmd(f"rm -rf {APP_DIR}/public/build")
upload_dir(r"d:\projects\uni-activity\public\build", f"{APP_DIR}/public/build")

print("--- Clearing Cache on Server ---")
print(run_cmd(f"cd {APP_DIR} && php artisan view:clear && php artisan config:clear"))

try:
    try:
        sftp.mkdir(f"{APP_DIR}/storage")
    except:
        pass
    try:
        sftp.mkdir(f"{APP_DIR}/storage/logs")
    except:
        pass
    with sftp.file(f"{APP_DIR}/storage/logs/deploy.log", "w") as f:
        f.write("".join(log_buffer))
except Exception as e:
    builtins.print(f"Failed to write deployment log: {e}")

sftp.close()
client.close()
print("Deployment successful!")
