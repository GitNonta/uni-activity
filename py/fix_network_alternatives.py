#!/usr/bin/env python3
"""
Alternative network fixes without root access
"""
import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

print("🔧 ALTERNATIVE NETWORK FIX (No Root Required)\n")

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    print("✓ Connected\n")
    
    def run(cmd):
        stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
        stdout.channel.recv_exit_status()
        return stdout.read().decode(errors="replace"), stderr.read().decode(errors="replace")
    
    # Method 1: Check if termux-wifi-connectioninfo exists
    print("Method 1: Checking Termux WiFi API...")
    out, err = run("which termux-wifi-connectioninfo")
    if out.strip():
        print("   ✓ Termux API available")
        out, err = run("termux-wifi-connectioninfo")
        print(f"   WiFi Info: {out[:200]}")
    else:
        print("   ℹ Termux API not installed (pkg install termux-api)\n")
    
    # Method 2: Try to trigger DHCP renewal
    print("Method 2: Attempting DHCP renewal...")
    commands = [
        "dhcpcd -n wlan0",
        "dhcpcd wlan0", 
        "dhclient wlan0",
        "udhcpc -i wlan0"
    ]
    
    for cmd in commands:
        print(f"   Trying: {cmd}")
        out, err = run(f"which {cmd.split()[0]}")
        if out.strip():
            out, err = run(cmd + " 2>&1")
            if "permission denied" not in err.lower() and "not found" not in err.lower():
                print(f"   Result: {out[:100]}{err[:100]}")
                time.sleep(2)
                break
    
    # Check routing after DHCP
    print("\nChecking routing after DHCP attempt...")
    out, err = run("ip route show")
    print(out)
    
    if "default" in out:
        print("   ✓ Gateway now configured!\n")
    else:
        print("   ✗ Still no gateway\n")
    
    # Method 3: Check Android network settings
    print("Method 3: Checking Android connectivity...")
    out, err = run("getprop | grep -i 'net\\.dns'")
    if out:
        print(f"   DNS settings: {out[:200]}")
    
    # Method 4: Try using Cloudflare tunnel as workaround
    print("\nMethod 4: Checking if cloudflared is available...")
    out, err = run("which cloudflared")
    if out.strip():
        print("   ✓ cloudflared is installed")
        print("   ℹ Can use tunnel as workaround for LINE webhook")
    else:
        print("   ℹ cloudflared not installed")
        print("   Install: pkg install cloudflared\n")
    
    # Method 5: Test if we can reach local services
    print("Method 5: Testing local network connectivity...")
    out, err = run("curl -I http://192.168.1.1 --connect-timeout 3 2>&1 | head -1")
    print(f"   Router response: {out.strip()}")
    
    # Final test: Can we reach anything?
    print("\nFinal connectivity test:")
    
    # Test 1: Local IP (should work)
    out, err = run("ping -c 1 -W 1 192.168.1.1")
    local_ok = "1 received" in out or "1 packets received" in out
    print(f"   Local network (192.168.1.1): {'✓ OK' if local_ok else '✗ FAIL'}")
    
    # Test 2: External IP (needs gateway)
    out, err = run("ping -c 1 -W 2 8.8.8.8")
    internet_ok = "1 received" in out or "1 packets received" in out
    print(f"   Internet (8.8.8.8): {'✓ OK' if internet_ok else '✗ FAIL (no gateway)'}")
    
    # Test 3: LINE API (needs gateway + HTTPS)
    out, err = run("curl -I https://api.line.me --connect-timeout 5 2>&1")
    line_ok = "HTTP" in out
    print(f"   LINE API: {'✓ REACHABLE' if line_ok else '✗ UNREACHABLE'}")
    
    print("\n" + "="*70)
    
    if line_ok:
        print("✅ LINE API IS WORKING!")
        print("The network issue has been resolved.")
    elif internet_ok:
        print("⚠️  Internet works but SSL/HTTPS might have issues")
        print("Check certificates: pkg install ca-certificates")
    else:
        print("❌ NO INTERNET ACCESS - Gateway required")
        print("\nThis device needs ROOT ACCESS to fix the network.")
        print("\n📱 DEVICE-SIDE FIX REQUIRED:")
        print("\nOption 1: Root the Android device")
        print("   Then: su -c 'ip route add default via 192.168.1.1 dev wlan0'")
        print("\nOption 2: Fix WiFi connection on Android")
        print("   - Disconnect and reconnect WiFi")
        print("   - Forget network and reconnect")
        print("   - Check if 'Use DHCP' is enabled in WiFi settings")
        print("\nOption 3: Use Cloudflare Tunnel (workaround)")
        print("   - Webhooks can work via tunnel")
        print("   - Push notifications will not work without internet")
    
    client.close()
    
except Exception as e:
    print(f"Error: {e}")
    import traceback
    traceback.print_exc()
