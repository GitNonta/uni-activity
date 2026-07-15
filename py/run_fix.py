import paramiko
import sys

host = '192.168.1.222'
port = 8022
user = 'u0_a175'
password = '2345678A'
remote_base = '/data/data/com.termux/files/home/uni-activity'

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    print(f"Connecting to {host}...")
    client.connect(host, port, user, password, timeout=10)
    sftp = client.open_sftp()
    
    print("Uploading fix_urls.php...")
    sftp.put('fix_urls.php', f"{remote_base}/fix_urls.php")
    
    print("Running fix_urls.php...")
    stdin, stdout, stderr = client.exec_command(f"cd {remote_base} && php fix_urls.php")
    print(stdout.read().decode())
    err = stderr.read().decode()
    if err:
        print("ERROR:", err)
        
finally:
    client.close()
