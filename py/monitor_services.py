#!/usr/bin/env python3
"""
Service Health Monitor - UNI ACTIVITY
Continuously monitor Reverb, Nginx, PHP-FPM, Redis
"""

import paramiko
import time
import datetime
from typing import Dict, Tuple

HOST = "192.168.1.222"
PORT = 8022
USER = "u0_a175"
PASSWORD = "2345678A"

SERVICES = {
    "Redis": "redis-server",
    "PHP-FPM": "php-fpm",
    "Nginx": "nginx",
    "Reverb": "artisan reverb"
}

def get_status_emoji(is_running: bool) -> str:
    return "🟢" if is_running else "🔴"

def check_service(client, process_name: str) -> Tuple[bool, str]:
    """Check if service is running and return status"""
    try:
        stdin, stdout, stderr = client.exec_command(f"pgrep -f '{process_name}' | head -1", timeout=5)
        pid = stdout.read().decode().strip()
        if pid:
            # Get process info
            stdin, stdout, stderr = client.exec_command(f"ps -p {pid} -o %cpu,%mem,etime", timeout=5)
            info = stdout.read().decode().strip().split('\n')[-1]
            return True, info
        return False, "Not running"
    except:
        return False, "Check failed"

def check_port(client, port: int) -> bool:
    """Check if port is listening"""
    try:
        stdin, stdout, stderr = client.exec_command(f"netstat -tuln | grep ':{port}' | grep LISTEN", timeout=5)
        result = stdout.read().decode().strip()
        return bool(result)
    except:
        return False

def monitor_loop(interval: int = 5):
    """Main monitoring loop"""
    print("🔍 Starting Service Monitor for UNI ACTIVITY")
    print(f"📡 Target: {HOST}:{PORT}")
    print(f"🔄 Check interval: {interval}s")
    print("Press Ctrl+C to stop\n")
    
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
        print("✅ Connected to server\n")
        
        iteration = 0
        while True:
            iteration += 1
            now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            
            # Clear screen (optional - comment out if not desired)
            if iteration > 1:
                print("\n" + "="*70)
            
            print(f"⏰ Check #{iteration} - {now}")
            print("-"*70)
            
            # Check each service
            all_healthy = True
            for service_name, process_name in SERVICES.items():
                is_running, info = check_service(client, process_name)
                emoji = get_status_emoji(is_running)
                status_text = f"{emoji} {service_name:12} "
                
                if is_running:
                    print(f"{status_text}RUNNING  │ {info}")
                else:
                    print(f"{status_text}OFFLINE  │ {info}")
                    all_healthy = False
            
            # Check ports
            print("-"*70)
            ports = {
                "HTTP": 8080,
                "Reverb": 8082,
                "Redis": 6379,
                "PHP-FPM": 9000
            }
            
            for name, port in ports.items():
                is_listening = check_port(client, port)
                emoji = get_status_emoji(is_listening)
                status = "LISTENING" if is_listening else "CLOSED"
                print(f"{emoji} Port {port:5} ({name:8}) │ {status}")
            
            # Overall status
            print("-"*70)
            if all_healthy:
                print("✅ ALL SERVICES HEALTHY")
            else:
                print("⚠️  ALERT: SOME SERVICES ARE DOWN!")
                print("   Run: python py/emergency_reverb_fix.py")
            
            # Wait for next check
            time.sleep(interval)
            
    except KeyboardInterrupt:
        print("\n\n⏹️  Monitoring stopped by user")
    except Exception as e:
        print(f"\n❌ Error: {e}")
    finally:
        client.close()
        print("👋 Disconnected")

def quick_check():
    """Quick one-time check"""
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    try:
        client.connect(HOST, port=PORT, username=USER, password=PASSWORD, timeout=10)
        
        now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        print(f"🔍 Quick Service Check - {now}")
        print("="*60)
        
        for service_name, process_name in SERVICES.items():
            is_running, info = check_service(client, process_name)
            emoji = get_status_emoji(is_running)
            print(f"{emoji} {service_name:12} {info}")
        
        client.close()
        
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    import sys
    
    if len(sys.argv) > 1 and sys.argv[1] == "quick":
        quick_check()
    else:
        # Default: continuous monitoring every 10 seconds
        monitor_loop(interval=10)
