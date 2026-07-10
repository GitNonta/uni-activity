import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode() + stderr.read().decode()

print(run_cmd("grep -A 20 'Exception' /data/data/com.termux/files/home/uni-activity/storage/logs/laravel.log | tail -n 50"))

client.close()
