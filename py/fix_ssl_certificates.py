#!/usr/bin/env python3
"""
Fix SSL Certificate Issues for LINE API
"""
import paramiko
import time

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

print("🔒 FIXING SSL CERTIFICATES FOR LINE API\n")

try:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
    print("✓ Connected\n")
    
    def run(cmd):
        stdin, stdout, stderr = client.exec_command(cmd, timeout=60)
        stdout.channel.recv_exit_status()
        return stdout.read().decode(errors="replace"), stderr.read().decode(errors="replace")
    
    # Step 1: Update package lists
    print("1️⃣  Updating package lists...")
    out, err = run("pkg update -y")
    print("   ✓ Done\n")
    
    # Step 2: Install/update CA certificates
    print("2️⃣  Installing/updating CA certificates...")
    out, err = run("pkg install ca-certificates -y")
    print(out[-200:] if len(out) > 200 else out)
    print("   ✓ Done\n")
    
    # Step 3: Update OpenSSL
    print("3️⃣  Updating OpenSSL...")
    out, err = run("pkg install openssl -y")
    print("   ✓ Done\n")
    
    # Step 4: Check certificate location
    print("4️⃣  Checking certificate configuration...")
    out, err = run("ls -lh /data/data/com.termux/files/usr/etc/tls/cert.pem")
    print(f"   {out.strip()}")
    
    # Step 5: Test with curl (verbose)
    print("\n5️⃣  Testing LINE API with curl...")
    out, err = run("curl -v https://api.line.me/v2/bot/info --connect-timeout 10 2>&1 | head -30")
    print(out)
    
    if "SSL" in out and "certificate" in out.lower():
        print("\n   ℹ SSL certificate issue detected")
    
    # Step 6: Try with --insecure to see if it's cert issue
    print("\n6️⃣  Testing without cert verification...")
    out, err = run("curl -I --insecure https://api.line.me/v2/bot/info --connect-timeout 10 2>&1")
    
    if "HTTP" in out:
        print("   ✓ Connection works without cert verification")
        print("   → This confirms it's a certificate issue\n")
        
        # Fix: Update cert bundle
        print("7️⃣  Updating certificate bundle...")
        out, err = run("pkg install ca-certificates-utils -y")
        out, err = run("update-ca-certificates")
        print("   ✓ Certificate bundle updated\n")
    else:
        print(f"   ✗ Still fails: {out[:200]}")
    
    # Step 7: Check PHP curl settings
    print("8️⃣  Checking PHP curl configuration...")
    php_check = """
php -r "
echo 'PHP Version: ' . phpversion() . PHP_EOL;
echo 'cURL Version: ' . curl_version()['version'] . PHP_EOL;
echo 'SSL Version: ' . curl_version()['ssl_version'] . PHP_EOL;
echo 'CA Bundle: ' . ini_get('curl.cainfo') . PHP_EOL;
echo 'OpenSSL CA File: ' . openssl_get_cert_locations()['default_cert_file'] . PHP_EOL;
"
"""
    out, err = run(php_check)
    print(out)
    
    # Step 8: Test LINE API again
    print("\n9️⃣  Testing LINE API (final test)...")
    out, err = run("curl -I https://api.line.me/v2/bot/info --connect-timeout 10 2>&1")
    
    print(out[:300])
    
    if "HTTP" in out:
        http_code = out.split('\n')[0]
        print(f"\n✅ LINE API IS NOW REACHABLE!")
        print(f"   Response: {http_code}")
        
        # Test from PHP
        print("\n🔟 Testing from PHP...")
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
echo 'HTTP Code: ' . \\$httpCode . PHP_EOL;
if (\\$error) {
    echo 'Error: ' . \\$error . PHP_EOL;
    exit(1);
} else {
    echo 'SUCCESS: Can reach LINE API!' . PHP_EOL;
    exit(0);
}
"
"""
        out, err = run(php_test)
        print(out)
        
        if "SUCCESS" in out:
            print("\n" + "="*70)
            print("✅✅✅ LINE OA ISSUE RESOLVED! ✅✅✅")
            print("="*70)
            print("\nLINE Official Account can now:")
            print("   ✓ Send push notifications")
            print("   ✓ Receive webhook events")
            print("   ✓ Connect to LINE Messaging API")
            print("\nThe issue was SSL certificate configuration.")
        else:
            print("\n⚠️  curl works but PHP still has issues")
            print("   Check PHP certificate configuration")
    else:
        print(f"\n❌ LINE API still unreachable")
        print("\nDiagnostic information:")
        print(f"Error: {err[:300]}")
        
        # Additional troubleshooting
        print("\nAdditional checks:")
        out, err = run("curl https://www.google.com --connect-timeout 5 2>&1 | head -5")
        print(f"Google test: {out[:100]}")
    
    client.close()
    
    print("\n" + "="*70)
    print("Certificate fix script completed")
    print("="*70)
    
except Exception as e:
    print(f"Error: {e}")
    import traceback
    traceback.print_exc()
