import paramiko

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    def run_cmd(cmd):
        stdin, stdout, stderr = client.exec_command(cmd)
        return stdout.read().decode(errors="replace") + stderr.read().decode(errors="replace")

    print("--- Check Nginx ---")
    print(run_cmd("ps aux | grep nginx"))
    print(run_cmd("netstat -tuln | grep 8080"))

    print("--- Check PHP ---")
    print(run_cmd("ps aux | grep php"))

    print("--- Try starting Nginx manually ---")
    print(run_cmd("nginx"))
    
    print("--- Check Nginx error log ---")
    print(run_cmd("cat /data/data/com.termux/files/usr/var/log/nginx/error.log | tail -n 10"))

    client.close()
except Exception as e:
    print("SSH Connection Failed:", e)
