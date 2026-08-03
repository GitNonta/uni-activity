#!/usr/bin/env python3
"""
Fix LINE OA Network Connectivity Issue - UNI ACTIVITY
Diagnose and fix "Network is unreachable" error
"""

import paramiko
import time
import sys
import json

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"
PROJECT_PATH = "/data/data/com.termux/files/home/uni-activity"

class Colors:
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'

def print_header(text):
    print(f"\n{Colors.BOLD}{'='*70}{Colors.ENDC}")
    print(f"{Colors.BOLD}{text.center(70)}{Colors.ENDC}")
    print(f"{Colors.BOLD}{'='*70}{Colors.ENDC}\n")

def print_success(text):
    print(f"{Colors.GREEN}✓ {text}{Colors.ENDC}")

def print_error(text):
    print(f"{Colors.RED}✗ {text}{Colors.ENDC}")

def print_warning(text):
    print(f"{Colors.YELLOW}⚠ {text}{Colors.ENDC}")

def print_info(text):
    print(f"{Colors.BLUE}ℹ {text}{Colors.ENDC}")

class LineOAFixer:
    def __init__(self):
        self.client = None
        self.connected = False

    def connect(self):
        """Connect to server"""
        print_header("CONNECTING TO SERVER")
        try:
            self.client = paramiko.SSHClient()
            self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            self.client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
            self.connected = True
            print_success(f"Connected to {HOST}:{PORT}")
            return True
        except Exception as e:
            print_error(f"Connection failed: {e}")
            return False

    def run_cmd(self, cmd, timeout=30):
        """Execute command"""
        if not self.connected:
            return "", "Not connected", 1
        
        try:
            stdin, stdout, stderr = self.client.exec_command(cmd, timeout=timeout)
            exit_code = stdout.channel.recv_exit_status()
            return (
                stdout.read().decode(errors="replace"),
                stderr.read().decode(errors="replace"),
                exit_code
            )
        except Exception as e:
            return "", str(e), 1

    def diagnose_network(self):
        """Diagnose network connectivity"""
        print_header("NETWORK DIAGNOSTICS")

        # Check internet connectivity
        print_info("Checking internet connectivity...")
        
        # Try ping Google DNS
        out, err, code = self.run_cmd("ping -c 2 8.8.8.8", timeout=10)
        if code == 0:
            print_success("Internet (ICMP) is reachable")
        else:
            print_error("Cannot ping 8.8.8.8 - Internet may be down")
            print(f"  {err}")

        # Try DNS resolution
        print_info("Checking DNS resolution...")
        out, err, code = self.run_cmd("nslookup api.line.me")
        if code == 0:
            print_success("DNS resolution works")
            print(f"  {out[:200]}")
        else:
            print_error("DNS resolution failed")
            print(f"  {err}")

        # Try curl to LINE API
        print_info("Testing LINE API connectivity...")
        out, err, code = self.run_cmd("curl -I https://api.line.me/v2/bot/message/push --connect-timeout 10", timeout=15)
        if code == 0 and "HTTP" in out:
            print_success("LINE API is reachable")
            print(f"  {out.split(chr(10))[0]}")
        else:
            print_error("Cannot reach LINE API")
            print(f"  Error: {err}")

        # Check network interfaces
        print_info("Checking network interfaces...")
        out, err, code = self.run_cmd("ip addr show | grep 'inet ' | grep -v '127.0.0.1'")
        if out:
            print_success("Network interfaces:")
            print(f"  {out}")
        else:
            print_warning("No network interface found (except localhost)")

        # Check default route
        print_info("Checking default gateway...")
        out, err, code = self.run_cmd("ip route | grep default")
        if out:
            print_success(f"Default route: {out.strip()}")
        else:
            print_error("No default gateway configured!")

    def check_env_config(self):
        """Check .env LINE configuration"""
        print_header("CHECKING LINE CONFIGURATION")

        print_info("Reading .env file...")
        out, err, code = self.run_cmd(f"grep -E '^LINE_' {PROJECT_PATH}/.env")
        
        if code == 0 and out:
            print_success("LINE configuration found:")
            lines = out.strip().split('\n')
            
            has_token = False
            has_secret = False
            
            for line in lines:
                if 'TOKEN' in line and len(line) > 50:
                    has_token = True
                    print(f"  {line[:50]}... [TRUNCATED]")
                elif 'SECRET' in line and '=' in line:
                    has_secret = True
                    secret_val = line.split('=')[1][:10]
                    print(f"  LINE_CHANNEL_SECRET={secret_val}... [TRUNCATED]")
                else:
                    print(f"  {line}")
            
            if not has_token:
                print_error("  LINE_CHANNEL_ACCESS_TOKEN is missing or too short!")
            if not has_secret:
                print_error("  LINE_CHANNEL_SECRET is missing!")
        else:
            print_error("Cannot read LINE configuration from .env")

    def test_php_curl(self):
        """Test PHP curl functionality"""
        print_header("TESTING PHP CURL")

        print_info("Creating test PHP script...")
        
        test_script = """<?php
// Test network connectivity from PHP
echo "Testing PHP curl to LINE API...\\n\\n";

$ch = curl_init('https://api.line.me/v2/bot/info');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer DUMMY_TOKEN_FOR_TEST'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\\n";
if ($error) {
    echo "cURL Error: " . $error . "\\n";
    exit(1);
} else {
    echo "Connection successful!\\n";
    echo "Response headers:\\n" . substr($response, 0, 500) . "\\n";
    exit(0);
}
"""

        # Write test script
        self.run_cmd(f"cat > {PROJECT_PATH}/test_line_curl.php << 'EOFPHP'\n{test_script}\nEOFPHP")
        
        print_info("Running PHP curl test...")
        out, err, code = self.run_cmd(f"cd {PROJECT_PATH} && php test_line_curl.php", timeout=15)
        
        print(out)
        if err:
            print_error(f"PHP Error: {err}")
        
        # Cleanup
        self.run_cmd(f"rm {PROJECT_PATH}/test_line_curl.php")
        
        if code == 0:
            print_success("PHP curl is working!")
            return True
        else:
            print_error("PHP curl test failed!")
            return False

    def check_certificates(self):
        """Check SSL certificates"""
        print_header("CHECKING SSL CERTIFICATES")

        print_info("Checking CA certificates bundle...")
        out, err, code = self.run_cmd("php -r 'echo openssl_get_cert_locations()[\"default_cert_file\"];'")
        
        if code == 0 and out:
            cert_path = out.strip()
            print_info(f"Default cert file: {cert_path}")
            
            # Check if file exists
            out2, err2, code2 = self.run_cmd(f"ls -lh {cert_path}")
            if code2 == 0:
                print_success(f"Certificate file exists: {out2.strip()}")
            else:
                print_error(f"Certificate file NOT found: {cert_path}")
                print_warning("This could cause SSL connection issues")
        
        # Check openssl version
        print_info("Checking OpenSSL version...")
        out, err, code = self.run_cmd("openssl version")
        if code == 0:
            print_success(f"OpenSSL: {out.strip()}")

    def fix_network_issues(self):
        """Try to fix common network issues"""
        print_header("APPLYING FIXES")

        # Fix 1: Update CA certificates
        print_info("Updating CA certificates...")
        out, err, code = self.run_cmd("pkg install ca-certificates -y", timeout=60)
        if code == 0:
            print_success("CA certificates updated")
        else:
            print_warning(f"Could not update certificates: {err[:200]}")

        # Fix 2: Clear Laravel cache
        print_info("Clearing Laravel caches...")
        self.run_cmd(f"cd {PROJECT_PATH} && php artisan cache:clear")
        self.run_cmd(f"cd {PROJECT_PATH} && php artisan config:clear")
        print_success("Laravel caches cleared")

        # Fix 3: Check if queue worker needs restart
        print_info("Checking queue workers...")
        out, err, code = self.run_cmd("pgrep -af 'queue:work'")
        if out:
            print_warning("Queue workers found - restarting them...")
            self.run_cmd("pkill -f 'queue:work'")
            time.sleep(2)
            self.run_cmd(f"cd {PROJECT_PATH} && nohup php artisan queue:work --daemon > storage/logs/queue.log 2>&1 &")
            print_success("Queue workers restarted")
        else:
            print_info("No queue workers running")

        # Fix 4: Test with artisan tinker
        print_info("Testing LINE API with artisan...")
        tinker_test = """
use Illuminate\\Support\\Facades\\Http;
try {
    \\$response = Http::timeout(10)->get('https://api.line.me/v2/bot/info', [
        'headers' => ['Authorization' => 'Bearer DUMMY_TOKEN']
    ]);
    echo "HTTP Code: " . \\$response->status() . "\\n";
    echo "SUCCESS: Can reach LINE API\\n";
} catch (\\Exception \\$e) {
    echo "ERROR: " . \\$e->getMessage() . "\\n";
}
exit;
"""
        out, err, code = self.run_cmd(
            f"cd {PROJECT_PATH} && echo '{tinker_test}' | php artisan tinker",
            timeout=20
        )
        print(out)

    def check_logs(self):
        """Check Laravel logs for LINE errors"""
        print_header("CHECKING LARAVEL LOGS")

        print_info("Searching for LINE-related errors...")
        out, err, code = self.run_cmd(
            f"tail -n 100 {PROJECT_PATH}/storage/logs/laravel.log | grep -i 'line' | tail -20"
        )
        
        if out:
            print_warning("Recent LINE-related log entries:")
            print(out)
        else:
            print_info("No recent LINE errors in logs")

    def verify_line_webhook(self):
        """Verify LINE webhook configuration"""
        print_header("LINE WEBHOOK CONFIGURATION")

        print_info("Checking webhook route...")
        out, err, code = self.run_cmd(f"cd {PROJECT_PATH} && php artisan route:list | grep line")
        
        if out:
            print_success("LINE routes registered:")
            print(out)
        else:
            print_warning("No LINE routes found")

    def show_recommendations(self):
        """Show recommendations"""
        print_header("RECOMMENDATIONS")

        print_info("1. Verify LINE Developer Console settings:")
        print("   - Channel Access Token is valid")
        print("   - Webhook URL is correctly set")
        print("   - Bot is not suspended")
        
        print_info("\n2. Check network connectivity:")
        print("   - Internet connection is stable")
        print("   - Firewall allows outbound HTTPS")
        print("   - DNS is working properly")
        
        print_info("\n3. Laravel configuration:")
        print("   - .env has correct LINE credentials")
        print("   - config:cache has been cleared")
        print("   - Queue workers are running")

        print_info("\n4. Test manually:")
        print(f"   ssh {USER}@{HOST} -p {PORT}")
        print(f"   cd {PROJECT_PATH}")
        print("   php artisan tinker")
        print("   >>> use Illuminate\\Support\\Facades\\Http;")
        print("   >>> Http::get('https://api.line.me');")

    def close(self):
        """Close connection"""
        if self.client:
            self.client.close()
            print_info("\nConnection closed")

def main():
    print_header("LINE OA NETWORK FIX - UNI ACTIVITY")
    print_info(f"Target: {HOST}:{PORT}")
    
    fixer = LineOAFixer()
    
    try:
        # Step 1: Connect
        if not fixer.connect():
            sys.exit(1)

        # Step 2: Diagnose network
        fixer.diagnose_network()

        # Step 3: Check configuration
        fixer.check_env_config()

        # Step 4: Check certificates
        fixer.check_certificates()

        # Step 5: Test PHP curl
        php_ok = fixer.test_php_curl()

        # Step 6: Check logs
        fixer.check_logs()

        # Step 7: Verify webhook
        fixer.verify_line_webhook()

        # Step 8: Ask if want to apply fixes
        print("\n" + "="*70)
        response = input(f"{Colors.YELLOW}Apply fixes? (yes/no): {Colors.ENDC}")
        
        if response.lower() in ['yes', 'y']:
            fixer.fix_network_issues()

        # Step 9: Show recommendations
        fixer.show_recommendations()

        print_header("DIAGNOSTIC COMPLETE")
        
        if php_ok:
            print_success("PHP can reach external APIs")
            print_info("The issue might be with LINE credentials or API limits")
        else:
            print_error("Network connectivity issues detected")
            print_info("Check internet connection and firewall settings")

    except KeyboardInterrupt:
        print_warning("\n\nOperation cancelled by user")
    except Exception as e:
        print_error(f"Unexpected error: {e}")
        import traceback
        traceback.print_exc()
    finally:
        fixer.close()

if __name__ == "__main__":
    main()
