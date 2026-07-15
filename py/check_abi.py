import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

stdin, stdout, stderr = client.exec_command("python -c \"import sys; print(sys.implementation.cache_tag)\"")
print('ABI:', stdout.read().decode('utf-8').strip())

stdin, stdout, stderr = client.exec_command("pip debug --verbose 2>&1 | head -n 20")
print('Pip debug:', stdout.read().decode('utf-8'))

client.close()
