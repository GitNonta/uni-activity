import paramiko
import time
import sys

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
APP_DIR = "/data/data/com.termux/files/home/uni-activity"

def drain(shell, wait=2.0):
    time.sleep(wait)
    out = ""
    for _ in range(30):
        if shell.recv_ready():
            out += shell.recv(65536).decode("utf-8", errors="replace")
            time.sleep(0.5)
        else:
            break
    return out

def send_and_wait(shell, cmd, wait=3.0, wait_for_prompt=True):
    print(f">>> {cmd}")
    shell.send(cmd + "\n")
    
    out = ""
    if wait_for_prompt:
        start_time = time.time()
        while time.time() - start_time < wait:
            if shell.recv_ready():
                chunk = shell.recv(65536).decode("utf-8", errors="replace")
                out += chunk
                sys.stdout.write(chunk)
                sys.stdout.flush()
                if out.strip().endswith("$") or out.strip().endswith("#") or (out.strip().endswith("~") and not "cd" in cmd) or out.strip().endswith("="):
                    time.sleep(1)
                    break
            else:
                time.sleep(0.5)
    else:
        out = drain(shell, wait)
        print(out)
    return out

def main():
    print("Connecting to SSH...")
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(hostname=HOST, port=PORT, username=USER, password=PASSWORD, timeout=30)
    
    shell = client.invoke_shell(width=200, height=50)
    drain(shell, 2)
    
    print("\n--- 1. Fixing .env ---")
    send_and_wait(shell, f"echo 'DB_REPLICA_HOST=127.0.0.1' >> {APP_DIR}/.env", 5, False)
    
    print("\n--- 2. Clearing Config Cache ---")
    send_and_wait(shell, f"cd {APP_DIR} && php artisan config:clear", 10, False)
    
    print("\n--- 3. Running Migration ---")
    send_and_wait(shell, f"cd {APP_DIR} && php artisan migrate --force", 20, False)
    
    print("\n--- 4. Testing URL Again ---")
    out = send_and_wait(shell, "curl -sI http://localhost:8080/", 10, False)
    
    print("\nDeployment Final Fix Completed.")
    
    shell.close()
    client.close()

if __name__ == "__main__":
    main()
