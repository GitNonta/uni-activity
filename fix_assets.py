import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if err:
        print(f"Error: {err}")
    return out

# 1. Check storage link
print("--- Fixing Storage Link ---")
run_cmd(f"cd {APP_DIR} && php artisan storage:link")

# 2. Check public/hot
print("--- Checking Vite Hot File ---")
run_cmd(f"rm -f {APP_DIR}/public/hot")

# 3. Check if public/build exists
print("--- Checking public/build ---")
out = run_cmd(f"ls -la {APP_DIR}/public/build")
if "No such file" in out or "Error" in out:
    print("WARNING: public/build is missing! Frontend assets won't load.")

client.close()
