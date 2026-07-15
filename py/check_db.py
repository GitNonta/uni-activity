import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)
stdin, stdout, stderr = client.exec_command('ps aux | grep -iE "(postgres|mariad|mysql)"')
print(stdout.read().decode())
client.close()
