import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

cmd = "psql -U postgres -d uni_activity -c \"SELECT email, otp, expires_at FROM password_reset_otps WHERE email='nontawat2546.2546@gmail.com';\""
stdin, stdout, stderr = client.exec_command(cmd)

print("--- OTP Output ---")
print(stdout.read().decode())

client.close()
