import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

shell = client.invoke_shell()
shell.send(f"cd {APP_DIR} && pkill -f reverb\n")
time.sleep(1)
shell.send(f"php artisan reverb:start --host=0.0.0.0 --port=8082\n")
time.sleep(2)
print("Listening...")

# Now trigger a request from inside Termux
shell.send("curl -v http://127.0.0.1:8082/app/uni-chat-key\n")
time.sleep(2)

out = ""
while shell.recv_ready():
    out += shell.recv(4096).decode("utf-8", errors="replace")

print(out)
client.close()
