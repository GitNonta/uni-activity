import os
import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        host = os.environ.get('TERMUX_HOST', '192.168.1.222')
        port = int(os.environ.get('TERMUX_PORT', 8022))
        user = os.environ.get('TERMUX_USER', 'u0_a175')
        password = os.environ.get('TERMUX_PASS', '')
        
        if password:
            ssh.connect(host, port=port, username=user, password=password, timeout=10)
        else:
            ssh.connect(host, port=port, username=user, timeout=10)
        print("\n=== Running npm run build on Termux ===")
        _, stdout, stderr = ssh.exec_command('cd ~/uni-activity && npm install && npm run build')
        print("STDOUT:", stdout.read().decode().strip())
        print("STDERR:", stderr.read().decode().strip())
    except Exception as e:
        print("❌ SSH Error:", e)
    finally:
        ssh.close()

if __name__ == "__main__":
    main()
