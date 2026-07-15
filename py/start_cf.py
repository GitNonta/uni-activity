import paramiko
import time
import re

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

# Kill old instances
client.exec_command('proot-distro login ubuntu -- pkill -f cloudflared')
time.sleep(2)

# Run cloudflared correctly in background
client.exec_command("proot-distro login ubuntu -- bash -c \"nohup cloudflared tunnel --url http://127.0.0.1:8080 > /root/cloudflared.log 2>&1 &\"")

# Wait for URL to appear in logs
time.sleep(5)
stdin, stdout, stderr = client.exec_command('proot-distro login ubuntu -- cat /root/cloudflared.log')
log = stdout.read().decode('utf-8')
print('LOG:')
print(log)

# Extract TryCloudflare URL
match = re.search(r'https://[a-zA-Z0-9-]+\.trycloudflare\.com', log)
if match:
    print('FOUND CF URL:', match.group(0))
else:
    print('NO CF URL FOUND')

client.close()
