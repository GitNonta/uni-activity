import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

print(run_cmd("curl -s -I http://127.0.0.1:8080/app/uni-chat-key"))
print(run_cmd("curl -s -I -H 'Upgrade: websocket' -H 'Connection: Upgrade' http://127.0.0.1:8080/app/uni-chat-key"))

client.close()
