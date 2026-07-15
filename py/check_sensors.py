import paramiko
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)

def run(cmd):
    stdin, stdout, stderr = client.exec_command(cmd)
    print(f'$ {cmd}\n{stdout.read().decode("utf-8").strip()}')

run('ls /sys/class/power_supply/')
run('cat /sys/class/power_supply/battery/capacity 2>/dev/null || echo no-cap')
run('cat /sys/class/power_supply/battery/temp 2>/dev/null || echo no-temp')
run('cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null || echo no-thermal')
run('df -m /data')

client.close()
