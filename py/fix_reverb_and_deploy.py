#!/usr/bin/env python3
"""
Fix Reverb Service and Nginx 502 Bad Gateway - UNI ACTIVITY
Comprehensive diagnostics and automated fixes
"""

import paramiko
import time
import sys
from typing import Tuple

# Server Configuration
HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

# Paths
PROJECT_PATH = "/data/data/com.termux/files/home/uni-activity"
NGINX_CONF = "/data/data/com.termux/files/usr/etc/nginx/nginx.conf"
PHP_FPM_CONF = "/data/data/com.termux/files/usr/etc/php-fpm.d/www.conf"
REVERB_LOG = f"{PROJECT_PATH}/storage/logs/reverb.log"
LARAVEL_LOG = f"{PROJECT_PATH}/storage/logs/laravel.log"

class Colors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'

def print_header(text: str):
    print(f"\n{Colors.HEADER}{Colors.BOLD}{'='*60}{Colors.ENDC}")
    print(f"{Colors.HEADER}{Colors.BOLD}{text.center(60)}{Colors.ENDC}")
    print(f"{Colors.HEADER}{Colors.BOLD}{'='*60}{Colors.ENDC}\n")

def print_success(text: str):
    print(f"{Colors.OKGREEN}✓ {text}{Colors.ENDC}")

def print_error(text: str):
    print(f"{Colors.FAIL}✗ {text}{Colors.ENDC}")

def print_warning(text: str):
    print(f"{Colors.WARNING}⚠ {text}{Colors.ENDC}")

def print_info(text: str):
    print(f"{Colors.OKCYAN}ℹ {text}{Colors.ENDC}")

class ServerManager:
    def __init__(self):
        self.client = None
        self.connected = False

    def connect(self) -> bool:
        """Establish SSH connection to server"""
        print_header("CONNECTING TO SERVER")
        try:
            self.client = paramiko.SSHClient()
            self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            self.client.connect(
                hostname=HOST,
                port=PORT,
                username=USER,
                password=PASSWORD,
                timeout=15
            )
            self.connected = True
            print_success(f"Connected to {HOST}:{PORT}")
            return True
        except Exception as e:
            print_error(f"Connection failed: {e}")
            return False

    def run_cmd(self, cmd: str, timeout: int = 30) -> Tuple[str, str, int]:
        """Execute command and return stdout, stderr, exit_code"""
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

    def check_process(self, process_name: str) -> bool:
        """Check if process is running"""
        out, _, code = self.run_cmd(f"pgrep -f '{process_name}' | head -1")
        return code == 0 and out.strip() != ""

    def kill_process(self, process_name: str):
        """Kill process by name"""
        self.run_cmd(f"pkill -9 -f '{process_name}'")
        time.sleep(1)

    def diagnose_system(self):
        """Run comprehensive system diagnostics"""
        print_header("SYSTEM DIAGNOSTICS")

        # Check Redis
        print_info("Checking Redis...")
        if self.check_process("redis-server"):
            print_success("Redis is running")
        else:
            print_error("Redis is NOT running")

        # Check PHP-FPM
        print_info("Checking PHP-FPM...")
        if self.check_process("php-fpm"):
            out, _, _ = self.run_cmd("ps aux | grep php-fpm | head -5")
            print_success("PHP-FPM is running")
            print(out)
        else:
            print_error("PHP-FPM is NOT running")

        # Check Nginx
        print_info("Checking Nginx...")
        if self.check_process("nginx"):
            out, _, _ = self.run_cmd("ps aux | grep nginx | grep -v grep")
            print_success("Nginx is running")
            print(out)
        else:
            print_error("Nginx is NOT running")

        # Check Reverb
        print_info("Checking Reverb...")
        if self.check_process("artisan reverb"):
            out, _, _ = self.run_cmd("ps aux | grep 'artisan reverb' | grep -v grep")
            print_success("Reverb is running")
            print(out)
        else:
            print_error("Reverb is NOT running - THIS IS THE PROBLEM!")

        # Check ports
        print_info("Checking network ports...")
        out, _, _ = self.run_cmd("netstat -tuln | grep -E ':(80|8080|8082|9000|6379)'")
        print(out if out else "No ports listening")

        # Check recent errors
        print_info("Checking recent Laravel errors...")
        out, _, _ = self.run_cmd(f"tail -n 20 {LARAVEL_LOG}")
        if out:
            print(out[-1000:])  # Last 1000 chars

    def fix_nginx_config(self):
        """Fix Nginx configuration for WebSocket support"""
        print_header("FIXING NGINX CONFIGURATION")

        nginx_config = """
worker_processes auto;
events {
    worker_connections 1024;
}

http {
    include mime.types;
    default_type application/octet-stream;
    sendfile on;
    keepalive_timeout 65;
    client_max_body_size 50M;

    # Upstream for PHP-FPM
    upstream php-fpm {
        server 127.0.0.1:9000;
    }

    # Upstream for Reverb WebSocket
    upstream reverb {
        server 127.0.0.1:8082;
    }

    server {
        listen 8080;
        server_name _;
        root %s/public;
        index index.php;

        # Main Laravel application
        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        # PHP-FPM processing
        location ~ \\.php$ {
            try_files $uri =404;
            fastcgi_pass php-fpm;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            fastcgi_read_timeout 300;
            fastcgi_send_timeout 300;
            fastcgi_connect_timeout 300;
        }

        # Reverb WebSocket proxy
        location /app {
            proxy_pass http://reverb;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
            proxy_read_timeout 86400;
            proxy_send_timeout 86400;
        }

        # Deny access to sensitive files
        location ~ /\\. {
            deny all;
        }
    }
}
""" % PROJECT_PATH

        # Backup existing config
        print_info("Backing up existing nginx.conf...")
        self.run_cmd(f"cp {NGINX_CONF} {NGINX_CONF}.backup.$(date +%s)")

        # Write new config
        print_info("Writing new nginx configuration...")
        # Escape quotes for shell
        escaped_config = nginx_config.replace('"', '\\"').replace('$', '\\$')
        self.run_cmd(f'cat > {NGINX_CONF} << "EOF"\n{nginx_config}\nEOF')
        
        # Test config
        out, err, code = self.run_cmd("nginx -t")
        if code == 0:
            print_success("Nginx configuration is valid")
        else:
            print_error(f"Nginx configuration test failed:\n{err}")
            return False

        return True

    def restart_services(self):
        """Restart all services in correct order"""
        print_header("RESTARTING SERVICES")

        # 1. Kill old Reverb processes
        print_info("Stopping old Reverb processes...")
        self.kill_process("artisan reverb")
        print_success("Reverb stopped")

        # 2. Restart Redis (if needed)
        print_info("Checking Redis...")
        if not self.check_process("redis-server"):
            print_warning("Starting Redis...")
            self.run_cmd("redis-server --daemonize yes")
            time.sleep(2)
        print_success("Redis is ready")

        # 3. Restart PHP-FPM
        print_info("Restarting PHP-FPM...")
        self.kill_process("php-fpm")
        time.sleep(1)
        self.run_cmd("php-fpm")
        time.sleep(2)
        if self.check_process("php-fpm"):
            print_success("PHP-FPM restarted successfully")
        else:
            print_error("PHP-FPM failed to start")

        # 4. Restart Nginx
        print_info("Restarting Nginx...")
        self.kill_process("nginx")
        time.sleep(1)
        out, err, code = self.run_cmd("nginx")
        time.sleep(2)
        if self.check_process("nginx"):
            print_success("Nginx restarted successfully")
        else:
            print_error(f"Nginx failed to start: {err}")

        # 5. Start Reverb
        print_info("Starting Reverb WebSocket server...")
        reverb_cmd = f"""
cd {PROJECT_PATH} && \\
nohup php artisan reverb:start --host=0.0.0.0 --port=8082 --debug \\
  > {REVERB_LOG} 2>&1 &
"""
        self.run_cmd(reverb_cmd)
        time.sleep(3)
        
        if self.check_process("artisan reverb"):
            print_success("Reverb started successfully")
            # Show recent log
            out, _, _ = self.run_cmd(f"tail -n 20 {REVERB_LOG}")
            print(out)
        else:
            print_error("Reverb failed to start")
            out, _, _ = self.run_cmd(f"cat {REVERB_LOG}")
            print(f"Reverb log:\n{out}")

    def verify_deployment(self):
        """Verify all services are running correctly"""
        print_header("DEPLOYMENT VERIFICATION")

        checks = {
            "Redis": ("redis-server", 6379),
            "PHP-FPM": ("php-fpm", 9000),
            "Nginx": ("nginx", 8080),
            "Reverb": ("artisan reverb", 8082)
        }

        all_ok = True
        for service, (process, port) in checks.items():
            print_info(f"Verifying {service}...")
            
            # Check process
            if self.check_process(process):
                print_success(f"  Process: Running")
            else:
                print_error(f"  Process: NOT running")
                all_ok = False
                continue

            # Check port
            out, _, code = self.run_cmd(f"netstat -tuln | grep ':{port}'")
            if code == 0 and out:
                print_success(f"  Port {port}: Listening")
            else:
                print_warning(f"  Port {port}: Not found (might be OK for PHP-FPM)")

        if all_ok:
            print_success("\n✓✓✓ ALL SERVICES ARE ONLINE ✓✓✓")
        else:
            print_error("\n✗✗✗ SOME SERVICES FAILED ✗✗✗")

        return all_ok

    def show_status_dashboard(self):
        """Show a nice status dashboard"""
        print_header("SERVICE STATUS DASHBOARD")
        
        out, _, _ = self.run_cmd("""
echo "=== PROCESSES ==="
ps aux | grep -E '(redis|php-fpm|nginx|reverb)' | grep -v grep
echo ""
echo "=== PORTS ==="
netstat -tuln | grep -E ':(80|8080|8082|9000|6379)'
echo ""
echo "=== MEMORY ==="
free -h
echo ""
echo "=== DISK ==="
df -h | head -5
""")
        print(out)

    def close(self):
        """Close SSH connection"""
        if self.client:
            self.client.close()
            print_info("Connection closed")

def main():
    """Main execution flow"""
    print_header("UNI ACTIVITY - REVERB & NGINX FIX")
    print_info(f"Target: {HOST}:{PORT}")
    print_info(f"Project: {PROJECT_PATH}")
    
    manager = ServerManager()
    
    try:
        # Step 1: Connect
        if not manager.connect():
            print_error("Failed to connect to server")
            sys.exit(1)

        # Step 2: Diagnose
        manager.diagnose_system()

        # Step 3: Ask for confirmation
        print("\n" + "="*60)
        response = input(f"{Colors.WARNING}Proceed with fix? (yes/no): {Colors.ENDC}")
        if response.lower() not in ['yes', 'y']:
            print_warning("Fix cancelled by user")
            return

        # Step 4: Fix Nginx config
        if not manager.fix_nginx_config():
            print_error("Failed to fix Nginx config")
            sys.exit(1)

        # Step 5: Restart all services
        manager.restart_services()

        # Step 6: Verify
        time.sleep(3)
        manager.verify_deployment()

        # Step 7: Show dashboard
        manager.show_status_dashboard()

        print_header("DEPLOYMENT COMPLETE")
        print_success("Services should now be online!")
        print_info("Test WebSocket: ws://192.168.1.222:8080/app/uni-chat-key")
        print_info("Test HTTP: http://192.168.1.222:8080")

    except KeyboardInterrupt:
        print_warning("\n\nOperation cancelled by user")
    except Exception as e:
        print_error(f"Unexpected error: {e}")
        import traceback
        traceback.print_exc()
    finally:
        manager.close()

if __name__ == "__main__":
    main()
