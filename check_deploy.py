import os
import paramiko

def main():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        ssh.connect(os.environ.get('TERMUX_HOST', '192.168.1.222'), port=8022, username=os.environ.get('TERMUX_USER', 'u0_a175'), password=os.environ.get('TERMUX_PASS', ''), timeout=10)
        _, stdout, _ = ssh.exec_command('cat ~/uni-activity/py/monitor_server.py')
        content = stdout.read().decode()
        # find github sync loop
        idx = content.find('Poll GitHub every 60 seconds')
        if idx != -1:
            print(content[max(0, idx-200):idx+2000])
        else:
            print("not found")
    except Exception as e:
        print("❌ SSH Error:", e)
    finally:
        ssh.close()

if __name__ == "__main__":
    main()
