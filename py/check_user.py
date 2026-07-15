import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

cmd = "psql -U postgres -d uni_activity -c \"SELECT id, email, full_name, role, is_active FROM users WHERE email='nontawat2546.2546@gmail.com';\""
stdin, stdout, stderr = client.exec_command(cmd)

print("--- DB Output ---")
print(stdout.read().decode())
print("--- DB Errors ---")
print(stderr.read().decode())

client.close()
