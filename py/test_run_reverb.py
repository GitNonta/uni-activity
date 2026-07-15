import paramiko

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

def run_cmd(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    return stdout.read().decode() + stderr.read().decode()

run_cmd("pkill -f 'artisan reverb'")
print(run_cmd("cd /data/data/com.termux/files/home/uni-activity && php artisan reverb:start --host=0.0.0.0 --port=8082"))

client.close()
