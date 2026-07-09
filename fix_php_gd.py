import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)

def run_cmd(cmd):
    print(f">>> {cmd}")
    stdin, stdout, stderr = client.exec_command(cmd)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    if err:
        print(f"Error: {err}")
    return out

print("--- Installing php-gd ---")
run_cmd("pkg install -y php-gd")

print("--- Restarting php-fpm ---")
run_cmd("pkill php-fpm && php-fpm")

print("--- Verifying GD extension ---")
print(run_cmd("php -m | grep gd"))

client.close()
