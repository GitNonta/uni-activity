#!/usr/bin/env python3
"""
Deploy Face Scan Updates to Server
Upload modified files via SCP/SFTP
"""

import paramiko
import os
import time
from pathlib import Path

# Server Configuration
HOST = os.environ.get('TERMUX_HOST', '192.168.1.222')
PORT = 8022
USER = os.environ.get('TERMUX_USER', 'u0_a175')
PASSWORD = os.getenv("SSH_PASS", "<YOUR_SSH_PASSWORD>")
REMOTE_PATH = "/data/data/com.termux/files/home/uni-activity"

class Colors:
    GREEN = '\033[92m'
    RED = '\033[91m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'

def print_header(text):
    print(f"\n{Colors.BOLD}{Colors.BLUE}{'='*70}{Colors.ENDC}")
    print(f"{Colors.BOLD}{Colors.BLUE}{text.center(70)}{Colors.ENDC}")
    print(f"{Colors.BOLD}{Colors.BLUE}{'='*70}{Colors.ENDC}\n")

def print_success(text):
    print(f"{Colors.GREEN}✓ {text}{Colors.ENDC}")

def print_error(text):
    print(f"{Colors.RED}✗ {text}{Colors.ENDC}")

def print_info(text):
    print(f"{Colors.BLUE}ℹ {text}{Colors.ENDC}")

def print_warning(text):
    print(f"{Colors.YELLOW}⚠ {text}{Colors.ENDC}")

class Deployer:
    def __init__(self):
        self.client = None
        self.sftp = None
        
    def connect(self):
        """Connect to server"""
        print_header("CONNECTING TO SERVER")
        try:
            self.client = paramiko.SSHClient()
            self.client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
            self.client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=15)
            self.sftp = self.client.open_sftp()
            print_success(f"Connected to {HOST}:{PORT}")
            return True
        except Exception as e:
            print_error(f"Connection failed: {e}")
            return False
    
    def backup_file(self, remote_file):
        """Backup existing file on server"""
        try:
            timestamp = time.strftime("%Y%m%d_%H%M%S")
            backup_path = f"{remote_file}.backup.{timestamp}"
            
            stdin, stdout, stderr = self.client.exec_command(f"cp {remote_file} {backup_path}")
            stdout.channel.recv_exit_status()
            
            print_success(f"Backed up: {os.path.basename(remote_file)}")
            return True
        except Exception as e:
            print_warning(f"Could not backup {remote_file}: {e}")
            return False
    
    def upload_file(self, local_path, remote_path):
        """Upload a single file"""
        try:
            if not os.path.exists(local_path):
                print_error(f"Local file not found: {local_path}")
                return False
            
            # Create remote directory if needed
            remote_dir = os.path.dirname(remote_path)
            try:
                self.sftp.stat(remote_dir)
            except:
                # Directory doesn't exist, create it
                stdin, stdout, stderr = self.client.exec_command(f"mkdir -p {remote_dir}")
                stdout.channel.recv_exit_status()
            
            # Backup existing file
            try:
                self.sftp.stat(remote_path)
                self.backup_file(remote_path)
            except:
                pass  # File doesn't exist, no need to backup
            
            # Upload file
            self.sftp.put(local_path, remote_path)
            
            # Get file size
            file_size = os.path.getsize(local_path)
            size_kb = file_size / 1024
            
            print_success(f"Uploaded: {os.path.basename(local_path)} ({size_kb:.2f} KB)")
            return True
            
        except Exception as e:
            print_error(f"Upload failed for {local_path}: {e}")
            return False
    
    def run_command(self, cmd):
        """Execute command on server"""
        try:
            stdin, stdout, stderr = self.client.exec_command(cmd, timeout=30)
            exit_code = stdout.channel.recv_exit_status()
            output = stdout.read().decode()
            error = stderr.read().decode()
            return output, error, exit_code
        except Exception as e:
            return "", str(e), 1
    
    def clear_cache(self):
        """Clear Laravel cache"""
        print_header("CLEARING CACHE")
        
        commands = [
            ("php artisan cache:clear", "Application cache"),
            ("php artisan config:clear", "Config cache"),
            ("php artisan view:clear", "View cache"),
        ]
        
        for cmd, desc in commands:
            print_info(f"Clearing {desc}...")
            out, err, code = self.run_command(f"cd {REMOTE_PATH} && {cmd}")
            if code == 0:
                print_success(f"{desc} cleared")
            else:
                print_warning(f"Could not clear {desc}: {err}")
    
    def verify_files(self, files):
        """Verify uploaded files exist"""
        print_header("VERIFYING FILES")
        
        all_ok = True
        for remote_file in files.values():
            try:
                stat = self.sftp.stat(remote_file)
                size_kb = stat.st_size / 1024
                print_success(f"{os.path.basename(remote_file)} ({size_kb:.2f} KB)")
            except:
                print_error(f"{os.path.basename(remote_file)} - NOT FOUND")
                all_ok = False
        
        return all_ok
    
    def close(self):
        """Close connections"""
        if self.sftp:
            self.sftp.close()
        if self.client:
            self.client.close()
        print_info("Connection closed")

def main():
    print_header("DEPLOY FACE SCAN UPDATES")
    print_info(f"Target: {HOST}:{PORT}")
    print_info(f"Remote: {REMOTE_PATH}\n")
    
    # Files to upload
    files_to_upload = {
        "resources/views/checkin/selfie.blade.php": f"{REMOTE_PATH}/resources/views/checkin/selfie.blade.php",
        "public/css/face-scan-animation.css": f"{REMOTE_PATH}/public/css/face-scan-animation.css",
    }
    
    # Check local files
    print_header("CHECKING LOCAL FILES")
    missing_files = []
    for local_file in files_to_upload.keys():
        if os.path.exists(local_file):
            size_kb = os.path.getsize(local_file) / 1024
            print_success(f"{local_file} ({size_kb:.2f} KB)")
        else:
            print_error(f"{local_file} - NOT FOUND")
            missing_files.append(local_file)
    
    if missing_files:
        print_error("\nMissing files. Cannot proceed.")
        return 1
    
    # Confirm deployment
    print("\n" + "="*70)
    response = input(f"{Colors.YELLOW}Deploy these files to server? (yes/no): {Colors.ENDC}")
    if response.lower() not in ['yes', 'y']:
        print_warning("Deployment cancelled")
        return 0
    
    # Deploy
    deployer = Deployer()
    
    try:
        # Connect
        if not deployer.connect():
            return 1
        
        # Upload files
        print_header("UPLOADING FILES")
        success_count = 0
        for local_file, remote_file in files_to_upload.items():
            if deployer.upload_file(local_file, remote_file):
                success_count += 1
            time.sleep(0.5)
        
        print(f"\n{Colors.GREEN}Uploaded {success_count}/{len(files_to_upload)} files{Colors.ENDC}")
        
        # Verify
        if deployer.verify_files(files_to_upload):
            print_success("\nAll files verified!")
        else:
            print_warning("\nSome files could not be verified")
        
        # Clear cache
        deployer.clear_cache()
        
        # Success
        print_header("DEPLOYMENT COMPLETE")
        print_success("Face scan updates deployed successfully!")
        print_info("\nUpdates:")
        print_info("  • Real-time face detection with face-api.js")
        print_info("  • 68 facial landmarks visualization")
        print_info("  • InsightFace 512D verification")
        print_info("  • Improved animations and UI")
        
        print_info("\nTest the updates:")
        print_info(f"  http://{HOST}:8080/activities")
        
        return 0
        
    except KeyboardInterrupt:
        print_warning("\n\nDeployment cancelled by user")
        return 1
    except Exception as e:
        print_error(f"\nDeployment error: {e}")
        import traceback
        traceback.print_exc()
        return 1
    finally:
        deployer.close()

if __name__ == "__main__":
    exit(main())
