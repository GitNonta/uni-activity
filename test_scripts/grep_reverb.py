import paramiko

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect('192.168.1.222', 8022, 'u0_a175', '2345678A')

cmd = 'grep "MessageSent" /data/data/com.termux/files/home/uni-activity/storage/logs/reverb.log | tail -n 20'
_, out, err = c.exec_command(cmd)

print("=== OUT ===")
print(out.read().decode())
print("=== ERR ===")
print(err.read().decode())

c.close()
