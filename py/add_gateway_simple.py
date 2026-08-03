#!/usr/bin/env python3
import paramiko
import sys

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    print("Connected to server\n")
    
    def run(cmd):
        stdin, stdout, stderr = client.exec_command(cmd, timeout=20)
        stdout.channel.recv_exit_status()
        out = stdout.read().decode(errors="replace")
        err = stderr.read().decode(errors="replace")
        return out, err
    
    # Check current route
    print("Current routing table:")
    out, err = run("ip route show")
    print(out)
    
    if "default" not in out:
        print("\nAttempting to add gateway with su...")
        
        # Try with su
        out, err = run("echo '2345678A' | su -c 'ip route add default via 192.168.1.1 dev wlan0' 2>&1")
        print(f"Result: {out}{err}")
        
        # Verify
        print("\nVerifying...")
        out, err = run("ip route show")
        print(out)
        
        if "default" in out:
            print("\n✅ Gateway added successfully!")
            
            # Test LINE API
            print("\nTesting LINE API...")
            out, err = run("curl -I https://api.line.me/v2/bot/info --connect-timeout 10 2>&1 | head -3")
            print(out)
            
            if "HTTP" in out:
                print("\n✅ LINE API IS NOW REACHABLE!")
            else:
                print("\n⚠️  LINE API still not reachable")
        else:
            print("\n❌ Could not add gateway - root access required")
            print("\nMANUAL FIX NEEDED:")
            print(f"1. ssh {USER}@{HOST} -p {PORT}")
            print("2. su")
            print("3. ip route add default via 192.168.1.1 dev wlan0")
    else:
        print("\n✓ Gateway already configured")
        
        # Test LINE API
        print("\nTesting LINE API...")
        out, err = run("curl -I https://api.line.me/v2/bot/info --connect-timeout 10 2>&1 | head -3")
        print(out)
        
        if "HTTP" in out:
            print("\n✅ LINE API IS REACHABLE!")
        else:
            print("\n⚠️  Gateway exists but LINE API not reachable")
            print("Check firewall or network settings")
    
    client.close()
    
except Exception as e:
    print(f"Error: {e}")
    sys.exit(1)
