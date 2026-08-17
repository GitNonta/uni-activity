#!/usr/bin/env python3
"""Check what's REALLY running on port 8000"""

import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('192.168.1.222', 8022, 'u0_a175', '2345678A')

def run(cmd):
    _, o, _ = ssh.exec_command(cmd)
    return o.read().decode('utf-8', errors='ignore').strip()

print("\n" + "="*70)
print("🔍 CHECKING REAL PORT 8000 STATUS")
print("="*70 + "\n")

# Port 8000 listener
print("[1] Port 8000 listener:")
port8000 = run('netstat -tlpn 2>/dev/null | grep ":8000"')
print(port8000)

# Extract PID
if "27489" in port8000:
    pid = "27489"
    print(f"\nPID: {pid}")
    
    # Get process details
    print("\n[2] Process details:")
    ps = run(f'ps aux | grep {pid} | grep -v grep')
    print(ps)
    
    # Get command line
    print("\n[3] Full command:")
    cmdline = run(f'cat /proc/{pid}/cmdline | tr "\\0" " "')
    print(cmdline)
    
    # Get exe path
    print("\n[4] Executable:")
    exe = run(f'ls -la /proc/{pid}/exe 2>/dev/null')
    print(exe)
    
    # Test HTTP response
    print("\n[5] Testing HTTP response:")
    http_test = run('curl -s http://localhost:8000/ | head -10')
    print(http_test[:200] if http_test else "No response")

# Check all ports
print("\n" + "="*70)
print("📊 ALL LISTENING PORTS")
print("="*70 + "\n")
all_ports = run('netstat -tlpn 2>/dev/null | grep LISTEN | grep -E "8000|8080|8082"')
print(all_ports)

ssh.close()

print("\n" + "="*70)
print("✅ CHECK COMPLETE")
print("="*70 + "\n")
