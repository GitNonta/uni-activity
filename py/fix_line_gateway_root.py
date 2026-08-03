#!/usr/bin/env python3
"""
Fix LINE OA Network Issue - Add Default Gateway with Root
"""

import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
GATEWAY = "192.168.1.1"

print("🌐 FIXING LINE OA NETWORK ISSUE\n")
print("="*70)

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
    
    def run(cmd, use_root=False):
        if use_root:
            cmd = f"su -c '{cmd}'"
        stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
        exit_code = stdout.channel.recv_exit_status()
        return stdout.read().decode(errors="replace"), stderr.read().decode(errors="replace"), exit_code
    
    print("✓ Connected to server\n")
    
    # Step 1: Check current routing
    print("1️⃣  Checking current routing table...")
    out, err, code = run("ip route show")
    print(out)
    
    has_gateway = "default" in out
    
    if not has_gateway:
        print("   ✗ No default gateway found\n")
        
        # Step 2: Try multiple methods to add gateway
        print("2️⃣  Adding default gateway (trying multiple methods)...\n")
        
        methods = [
            ("Method 1: ip route (with root)", f"ip route add default via {GATEWAY} dev wlan0", True),
            ("Method 2: route command (with root)", f"route add default gw {GATEWAY} wlan0", True),
            ("Method 3: ip route (without root)", f"ip route add default via {GATEWAY} dev wlan0", False),
        ]
        
        success = False
        for method_name, command, use_root in methods:
            print(f"   Trying {method_name}...")
            out, err, code = run(command, use_root=use_root)
            
            if code == 0:
                print(f"   ✓ {method_name} succeeded!\n")
                success = True
                break
            elif "File exists" in err or "RTNETLINK answers: File exists" in err:
                print(f"   ℹ Gateway already exists (via different method)\n")
                success = True
                break
            else:
                print(f"   ✗ Failed: {err.strip()[:100]}")
        
        if not success:
            print("\n   ⚠ All methods failed. Trying alternative approaches...\n")
            
            # Alternative: Restart DHCP
            print("   Alternative 1: Restarting DHCP client...")
            run("dhcpcd wlan0", use_root=True)
            time.sleep(3)
            
            # Alternative: Restart network interface
            print("   Alternative 2: Restarting network interface...")
            run("ip link set wlan0 down", use_root=True)
            time.sleep(2)
            run("ip link set wlan0 up", use_root=True)
            time.sleep(3)
            run("dhcpcd wlan0", use_root=True)
            time.sleep(3)
        
        # Verify gateway was added
        print("\n3️⃣  Verifying routing table...")
        out, err, code = run("ip route show")
        print(out)
        
        if "default" in out:
            print("   ✓ Default gateway is now configured!\n")
        else:
            print("   ✗ Still no default gateway (root access may be required)\n")
    else:
        print("   ✓ Default gateway already exists\n")
    
    # Step 3: Test gateway connectivity
    print("4️⃣  Testing gateway connectivity...")
    out, err, code = run(f"ping -c 2 -W 2 {GATEWAY}")
    if code == 0:
        print(f"   ✓ Can reach gateway ({GATEWAY})\n")
    else:
        print(f"   ⚠ Cannot ping gateway: {err[:100]}\n")
    
    # Step 4: Test external connectivity
    print("5️⃣  Testing external connectivity...")
    out, err, code = run("ping -c 2 -W 3 8.8.8.8")
    if code == 0:
        print("   ✓ Can reach external IP (8.8.8.8)\n")
    else:
        print(f"   ✗ Cannot reach external IP: {err[:100]}\n")
    
    # Step 5: Test LINE API
    print("6️⃣  Testing LINE API connectivity...")
    out, err, code = run("curl -I https://api.line.me/v2/bot/info --connect-timeout 10 --max-time 15")
    
    if "HTTP" in out:
        http_code = out.split('\n')[0]
        print(f"   ✓ LINE API is reachable!")
        print(f"   Response: {http_code}\n")
        
        print("="*70)
        print("✅ LINE OA NETWORK ISSUE RESOLVED!")
        print("="*70)
        print("\n🎉 LINE Official Account should now be able to:")
        print("   • Send push notifications")
        print("   • Receive webhook events")
        print("   • Connect to LINE API")
        
    else:
        print(f"   ✗ Still cannot reach LINE API")
        print(f"   Error: {err[:200]}\n")
        
        print("="*70)
        print("⚠️  ISSUE NOT FULLY RESOLVED")
        print("="*70)
        print("\nPossible causes:")
        print("1. Root/su access is required to add gateway")
        print("2. Firewall blocking outbound HTTPS")
        print("3. Router/gateway not functioning")
        print("4. Network configuration issue")
        print("\nManual fix required:")
        print(f"   ssh {USER}@{HOST} -p {PORT}")
        print("   su")
        print(f"   ip route add default via {GATEWAY} dev wlan0")
    
    # Step 6: Test from PHP
    print("\n7️⃣  Testing from PHP/Laravel...")
    php_test = """
cd /data/data/com.termux/files/home/uni-activity
php -r "
\\$ch = curl_init('https://api.line.me/v2/bot/info');
curl_setopt(\\$ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\\$ch, CURLOPT_TIMEOUT, 10);
curl_setopt(\\$ch, CURLOPT_HEADER, true);
curl_setopt(\\$ch, CURLOPT_NOBODY, true);
\\$response = curl_exec(\\$ch);
\\$httpCode = curl_getinfo(\\$ch, CURLINFO_HTTP_CODE);
\\$error = curl_error(\\$ch);
curl_close(\\$ch);
if (\\$httpCode > 0) {
    echo 'SUCCESS: HTTP ' . \\$httpCode . PHP_EOL;
    exit(0);
} else {
    echo 'ERROR: ' . \\$error . PHP_EOL;
    exit(1);
}
"
"""
    out, err, code = run(php_test)
    print(out)
    if code == 0 and "SUCCESS" in out:
        print("   ✓ PHP can reach LINE API!\n")
    else:
        print(f"   ✗ PHP still cannot reach LINE API\n")
    
    # Step 7: Show final status
    print("\n" + "="*70)
    print("FINAL STATUS")
    print("="*70)
    
    out, err, code = run("ip route show | grep default")
    if out:
        print(f"✓ Gateway: {out.strip()}")
    else:
        print("✗ Gateway: NOT CONFIGURED")
    
    out, err, code = run("curl -I https://api.line.me --connect-timeout 5 --max-time 10 2>&1 | head -1")
    if "HTTP" in out:
        print(f"✓ LINE API: REACHABLE ({out.strip()})")
    else:
        print(f"✗ LINE API: UNREACHABLE")
    
    client.close()
    
except KeyboardInterrupt:
    print("\n\n⏹️  Operation cancelled")
except Exception as e:
    print(f"\n❌ ERROR: {e}")
    import traceback
    traceback.print_exc()

print("\n" + "="*70)
print("For detailed manual instructions, see: py/LINE_OA_FIX_MANUAL.md")
print("="*70)
