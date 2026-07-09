import paramiko
import re

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

# ดึงข้อมูลจากไฟล์ .env ในเครื่อง Local
local_env_path = r"d:\projects\uni-activity\.env"
with open(local_env_path, "r", encoding="utf-8") as f:
    local_env_content = f.read()

# ดึงค่าที่ต้องการ (Mail และ Line)
keys_to_sync = [
    "MAIL_MAILER", "MAIL_HOST", "MAIL_PORT", "MAIL_USERNAME", "MAIL_PASSWORD", 
    "MAIL_ENCRYPTION", "MAIL_FROM_ADDRESS", "MAIL_FROM_NAME",
    "LINE_CHANNEL_ACCESS_TOKEN", "LINE_CHANNEL_SECRET", 
    "LINE_LOGIN_CHANNEL_ID", "LINE_LOGIN_CHANNEL_SECRET", "LINE_CALLBACK_URL"
]

sync_values = {}
for line in local_env_content.splitlines():
    line = line.strip()
    if not line or line.startswith("#"):
        continue
    if "=" in line:
        key, val = line.split("=", 1)
        if key in keys_to_sync:
            sync_values[key] = val

print("Connecting to SSH...")
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

sftp = client.open_sftp()
remote_env_path = f"{APP_DIR}/.env"

# โหลด .env จาก Server
try:
    with sftp.file(remote_env_path, 'r') as f:
        remote_env_content = f.read().decode('utf-8')
except Exception as e:
    print(f"Error reading remote .env: {e}")
    remote_env_content = ""

# อัพเดทค่าลงไป
remote_lines = remote_env_content.splitlines()
new_remote_lines = []
updated_keys = set()

for line in remote_lines:
    if "=" in line and not line.startswith("#"):
        key, _ = line.split("=", 1)
        if key in sync_values:
            new_remote_lines.append(f"{key}={sync_values[key]}")
            updated_keys.add(key)
        else:
            new_remote_lines.append(line)
    else:
        new_remote_lines.append(line)

# เพิ่มค่าที่ยังไม่มีใน Server
for key, val in sync_values.items():
    if key not in updated_keys:
        new_remote_lines.append(f"{key}={val}")

new_env_content = "\n".join(new_remote_lines) + "\n"

# บันทึกทับไฟล์เดิม
with sftp.file(remote_env_path, 'w') as f:
    f.write(new_env_content)

print("--- Updated .env on Server ---")
print(f"Synced {len(sync_values)} configurations to the server.")

sftp.close()

# รีสตาร์ท Cache
cmd = f"cd {APP_DIR} && php artisan config:clear && php artisan cache:clear"
stdin, stdout, stderr = client.exec_command(cmd)
print(stdout.read().decode())

client.close()
