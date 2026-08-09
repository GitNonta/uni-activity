import os
import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        ssh.connect(os.environ.get('TERMUX_HOST', '192.168.1.222'), port=8022, username=os.environ.get('TERMUX_USER', 'u0_a175'), password=os.environ.get('TERMUX_PASS', ''), timeout=10)
        print("\n=== Auto Git Sync Status ===")
        _, stdout, _ = ssh.exec_command('ps aux | grep "[a]uto-git-sync.sh"')
        out = stdout.read().decode().strip()
        print(out if out else "NOT RUNNING")
        
        print("\n=== Recent Git Sync Logs ===")
        _, stdout, _ = ssh.exec_command('cat ~/uni-activity/storage/logs/git-sync.log | tail -n 10 || echo "No log found"')
        print(stdout.read().decode().strip())
    except Exception as e:
        print("❌ SSH Error:", e)
    finally:
        ssh.close()

if __name__ == "__main__":
    main()
