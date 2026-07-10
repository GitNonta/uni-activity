import paramiko
import time

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode() + stderr.read().decode()

print(run_cmd("pkg install redis -y"))
print(run_cmd("redis-server --daemonize yes"))

time.sleep(2)
print(run_cmd("python -c \"import socket; s=socket.socket(); s.settimeout(2); s.connect(('127.0.0.1', 6379)); print('Redis is UP')\""))

client.close()
