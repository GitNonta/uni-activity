import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('192.168.1.222', 8022, 'u0_a175', '2345678A')

print("Reverting region in start_server.sh...")
i, o, e = c.exec_command("sed -i 's/cloudflared tunnel --region ap --url/cloudflared tunnel --url/g' ~/.termux/boot/start_server.sh")
print(o.read().decode())
print(e.read().decode())

print("Restarting start_server.sh...")
i, o, e = c.exec_command("~/.termux/boot/start_server.sh", get_pty=True)
print(o.read().decode())
print(e.read().decode())

print("Restarted.")
