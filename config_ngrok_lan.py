import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

local_env_path = r"d:\projects\uni-activity\.env"
ngrok_token = None
with open(local_env_path, "r", encoding="utf-8") as f:
    for line in f:
        if line.startswith("NGROK_AUTHTOKEN="):
            ngrok_token = line.split("=", 1)[1].strip()
            break

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace")

print("--- 1. Creating Ngrok config for LAN access ---")
config = f"""version: "2"
authtoken: {ngrok_token}
web_addr: 0.0.0.0:4040
"""
# Write config inside Ubuntu
run_cmd(f"proot-distro login ubuntu -- bash -c 'cat << EOF > /root/ngrok.yml\n{config}\nEOF'")

print("--- 2. Restarting Ngrok ---")
run_cmd("pkill ngrok")
run_cmd("nohup proot-distro login ubuntu -- ngrok http 8080 --config=/root/ngrok.yml > ngrok_ubuntu.log 2>&1 &")

client.close()
print("Done! Dashboard available at 192.168.1.222:4040")
