import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', port=8022, username='u0_a175', password='2345678A')

cmd = """
sed -i '/gzip_disable/a \    client_max_body_size 50M;' /data/data/com.termux/files/usr/etc/nginx/nginx.conf
nginx -s reload
"""
stdin, stdout, stderr = client.exec_command(cmd)
print("OUT:", stdout.read().decode())
print("ERR:", stderr.read().decode())
client.close()
