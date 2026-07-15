import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)
stdin, stdout, stderr = client.exec_command('python -c "import socket; s=socket.socket(); s.settimeout(2); s.connect((\'127.0.0.1\', 6379)); print(\'OK\')"')
print('Out:', stdout.read().decode())
print('Err:', stderr.read().decode())
client.close()
