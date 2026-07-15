import paramiko
import sys
import json

cmd = sys.argv[1]
client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    client.connect('192.168.1.222', 8022, 'u0_a175', '2345678A', timeout=10)
    stdin, stdout, stderr = client.exec_command(cmd)
    
    out = stdout.read().decode()
    err = stderr.read().decode()
    if out:
        print("STDOUT:")
        print(out)
    if err:
        print("STDERR:")
        print(err)
        
    exit_status = stdout.channel.recv_exit_status()
    if exit_status != 0:
        sys.exit(exit_status)
finally:
    client.close()
