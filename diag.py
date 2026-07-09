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
    return out.strip() + "\n" + err.strip()

print("--- 1. Check storage symlink ---")
print(run_cmd(f"ls -la {APP_DIR}/public/storage"))

print("--- 2. Check Reverb Port ---")
print(run_cmd("netstat -tuln | grep 8081"))

print("--- 3. Check Nginx Config ---")
print(run_cmd("cat /data/data/com.termux/files/usr/etc/nginx/nginx.conf"))

print("--- 4. Check Reverb Log ---")
print(run_cmd(f"tail -n 20 {APP_DIR}/storage/logs/reverb.log"))

client.close()
