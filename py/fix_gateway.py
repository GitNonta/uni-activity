#!/usr/bin/env python3
"""
Fix Network Gateway Issue - UNI ACTIVITY
Add default gateway to enable external connectivity
"""

import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

print("🌐 FIXING NETWORK GATEWAY\n")

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    
    def run(cmd):
        stdin, stdout, stderr = client.exec_command(cmd, timeout=15)
        stdout.channel.recv_exit_status()
        return stdout.read().decode(errors="replace"), stderr.read().decode(errors="replace")
    
    # Step 1: Check current gateway
    print("1️⃣  Checking current gateway...")
    out, err = run("ip route show")
    print(f"Current routes:\n{out}")
    
    if "default" not in out:
        print("   ✗ No default gateway found!\n")
        
        # Step 2: Find gateway from network
        print("2️⃣  Finding gateway from network interface...")
        # Get network info
        out, err = run("ip addr show wlan0 | grep 'inet ' | awk '{print $2}'")
        if out:
            ip_with_mask = out.strip()  # e.g., 192.168.1.222/24
            ip_parts = ip_with_mask.split('/')[0].split('.')
            # Assume gateway is .1 of the network
            gateway = f"{ip_parts[0]}.{ip_parts[1]}.{ip_parts[2]}.1"
            print(f"   ℹ Detected gateway: {gateway}\n")
            
            # Step 3: Add default gateway
            print("3️⃣  Adding default gateway...")
            out, err = run(f"su -c 'ip route add default via {gateway} dev wlan0'")
            
            if err and "File exists" in err:
                print("   ℹ Gateway already exists (different method)\n")
            elif err:
                print(f"   ⚠ Error: {err}\n")
                # Try alternative method
                print("   🔄 Trying alternative method...")
                out, err = run(f"su -c 'route add default gw {gateway} wlan0'")
                if err:
                    print(f"   ✗ Failed: {err}\n")
                else:
                    print("   ✓ Gateway added!\n")
            else:
                print("   ✓ Default gateway added!\n")
            
            # Step 4: Verify
            print("4️⃣  Verifying gateway...")
            time.sleep(1)
            out, err = run("ip route show")
            print(f"Updated routes:\n{out}")
            
            if "default" in out:
                print("\n✅ DEFAULT GATEWAY IS NOW CONFIGURED!\n")
                
                # Step 5: Test connectivity
                print("5️⃣  Testing external connectivity...")
                out, err = run("curl -I https://api.line.me/v2/bot/info --connect-timeout 10")
                if "HTTP" in out:
                    print("   ✅ Can reach LINE API!")
                    print(f"   {out.split(chr(10))[0]}")
                else:
                    print(f"   ⚠ Still cannot reach LINE API")
                    print(f"   Error: {err[:200]}")
            else:
                print("\n❌ Failed to add default gateway")
                print("This requires root/su access on the device")
        else:
            print("   ✗ Could not detect network interface\n")
    else:
        print("   ✓ Default gateway already configured\n")
        gateway_line = [line for line in out.split('\n') if 'default' in line][0]
        print(f"   Current: {gateway_line}\n")
        
        # Test connectivity
        print("2️⃣  Testing external connectivity...")
        out, err = run("curl -I https://api.line.me/v2/bot/info --connect-timeout 10")
        if "HTTP" in out:
            print("   ✅ Can reach LINE API!")
            print(f"   {out.split(chr(10))[0]}")
            print("\n✅ NETWORK IS WORKING - Issue may be elsewhere")
        else:
            print(f"   ⚠ Cannot reach LINE API")
            print(f"   Error: {err[:200]}")
    
    client.close()
    
    print("\n" + "="*70)
    print("TROUBLESHOOTING NOTES:")
    print("="*70)
    print("If gateway cannot be added automatically:")
    print("1. Connect to device physically or via SSH")
    print("2. Run: su")
    print("3. Run: ip route add default via 192.168.1.1 dev wlan0")
    print("4. Or check router IP and use that as gateway")
    print("\nAlternatively, check if:")
    print("- WiFi is connected properly")
    print("- Router is working")
    print("- Firewall is not blocking outbound connections")
    
except Exception as e:
    print(f"\n❌ ERROR: {e}")
    import traceback
    traceback.print_exc()
