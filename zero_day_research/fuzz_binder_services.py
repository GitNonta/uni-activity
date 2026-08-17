#!/usr/bin/env python3
"""
Binder IPC Fuzzer - Command Line
Find zero-day in Binder services by fuzzing
"""

import subprocess
import time
import random
import string
import sys

class BinderFuzzer:
    def __init__(self):
        self.crashes = []
        self.services = []
        
    def run_cmd(self, cmd):
        """Run ADB command"""
        try:
            result = subprocess.run(
                f'adb shell "{cmd}"',
                shell=True,
                capture_output=True,
                text=True,
                timeout=10
            )
            return result.stdout, result.stderr, result.returncode
        except subprocess.TimeoutExpired:
            return "", "TIMEOUT", -1
        except Exception as e:
            return "", str(e), -1
    
    def list_services(self):
        """List all Binder services"""
        print("=" * 70)
        print("  SCANNING BINDER SERVICES")
        print("=" * 70)
        
        stdout, stderr, code = self.run_cmd("service list")
        
        if stdout:
            lines = stdout.strip().split('\n')
            for line in lines:
                if ':' in line:
                    # Format: "123    service_name: [package.name]"
                    parts = line.split(':', 1)
                    service_name = parts[0].split()[-1].strip()
                    self.services.append(service_name)
            
            print(f"\n✅ Found {len(self.services)} services\n")
            
            # Show interesting services
            interesting = ['media', 'camera', 'audio', 'phone', 'bluetooth', 'nfc', 'location']
            print("Interesting services:")
            for srv in self.services:
                for keyword in interesting:
                    if keyword in srv.lower():
                        print(f"  • {srv}")
                        break
            
            return True
        else:
            print("❌ Cannot list services")
            return False
    
    def check_service(self, service_name):
        """Check if service exists and get info"""
        stdout, stderr, code = self.run_cmd(f"service check {service_name}")
        
        if "not found" in stdout.lower() or "not found" in stderr.lower():
            return False
        return True
    
    def fuzz_service_call(self, service_name, transaction_code, data):
        """Fuzz a specific service transaction"""
        cmd = f"service call {service_name} {transaction_code} {data}"
        stdout, stderr, code = self.run_cmd(cmd)
        
        return stdout, stderr, code
    
    def generate_fuzz_data(self, fuzz_type):
        """Generate fuzzing payloads"""
        if fuzz_type == "int_overflow":
            # Integer overflow
            return [
                "i32 2147483647",  # MAX_INT
                "i32 -2147483648", # MIN_INT
                "i32 0",
                "i32 -1",
                "i64 9223372036854775807",  # MAX_LONG
            ]
        
        elif fuzz_type == "string_overflow":
            # String buffer overflow
            a_1000 = 'A' * 1000
            a_10000 = 'A' * 10000
            return [
                f's16 "{a_1000}"',
                f's16 "{a_10000}"',
                's16 "%s%s%s%s%s%s"',  # Format string
                's16 "../../../../../etc/passwd"',  # Path traversal
                's16 "\\x00\\x00\\x00\\x00"',  # Null bytes
            ]
        
        elif fuzz_type == "null":
            # Null pointer
            return [
                "",
                "i32 0",
                "s16 \"\"",
            ]
        
        elif fuzz_type == "special":
            # Special characters
            return [
                's16 "\\n\\r\\t"',
                's16 "<script>alert(1)</script>"',
                's16 "\'; DROP TABLE--"',
                's16 "$(id)"',
                's16 "`id`"',
            ]
        
        return []
    
    def fuzz_service(self, service_name, max_transactions=100):
        """Fuzz a single service"""
        print(f"\n{'=' * 70}")
        print(f"  FUZZING: {service_name}")
        print(f"{'=' * 70}\n")
        
        if not self.check_service(service_name):
            print(f"❌ Service {service_name} not found or not accessible")
            return
        
        fuzz_types = ["int_overflow", "string_overflow", "null", "special"]
        
        crashes = 0
        tested = 0
        
        # Try different transaction codes
        for transaction in range(1, max_transactions):
            for fuzz_type in fuzz_types:
                payloads = self.generate_fuzz_data(fuzz_type)
                
                for payload in payloads:
                    tested += 1
                    
                    # Show progress
                    print(f"\r[{tested}] Transaction {transaction}, Type: {fuzz_type[:10]:10s}", end='', flush=True)
                    
                    stdout, stderr, code = self.fuzz_service_call(service_name, transaction, payload)
                    
                    # Check for crashes/errors
                    if code == -1:
                        print(f"\n🔴 TIMEOUT/CRASH!")
                        print(f"   Service: {service_name}")
                        print(f"   Transaction: {transaction}")
                        print(f"   Payload: {payload[:50]}")
                        
                        self.crashes.append({
                            "service": service_name,
                            "transaction": transaction,
                            "payload": payload,
                            "type": fuzz_type
                        })
                        crashes += 1
                    
                    elif "exception" in stderr.lower() or "error" in stderr.lower():
                        if "Permission" not in stderr and "Security" not in stderr:
                            print(f"\n⚠️  ERROR: {stderr[:100]}")
                            print(f"   Transaction: {transaction}, Payload: {payload[:50]}")
                    
                    elif "segmentation fault" in stdout.lower() or "segfault" in stdout.lower():
                        print(f"\n🔴 SEGFAULT DETECTED!")
                        print(f"   Service: {service_name}")
                        print(f"   Transaction: {transaction}")
                        
                        self.crashes.append({
                            "service": service_name,
                            "transaction": transaction,
                            "payload": payload,
                            "type": "SEGFAULT"
                        })
                        crashes += 1
                    
                    # Small delay
                    time.sleep(0.01)
        
        print(f"\n\n✅ Completed: {tested} tests, {crashes} crashes")
    
    def fuzz_media_player(self):
        """Fuzz media player service"""
        print(f"\n{'=' * 70}")
        print(f"  FUZZING: Media Player")
        print(f"{'=' * 70}\n")
        
        # Create malformed media files
        print("📁 Creating malformed media files...")
        
        malformed_files = []
        
        # 1. Oversized header
        print("\n1. Testing oversized MP4 header...")
        self.run_cmd("mkdir -p /sdcard/fuzz_test")
        
        # Create file with massive header
        header = "A" * 100000
        self.run_cmd(f'echo "{header}" > /sdcard/fuzz_test/overflow.mp4')
        
        stdout, stderr, code = self.run_cmd(
            'am start -a android.intent.action.VIEW -d file:///sdcard/fuzz_test/overflow.mp4 -t video/mp4'
        )
        
        if code == -1 or "crash" in stderr.lower():
            print("   🔴 CRASH detected!")
            self.crashes.append({
                "type": "Media Player Crash",
                "file": "oversized_header.mp4"
            })
        else:
            print("   ✅ No crash")
        
        time.sleep(2)
        
        # 2. Null bytes
        print("\n2. Testing null byte injection...")
        self.run_cmd('echo -e "\\x00\\x00\\x00\\x00" > /sdcard/fuzz_test/null.mp4')
        
        stdout, stderr, code = self.run_cmd(
            'am start -a android.intent.action.VIEW -d file:///sdcard/fuzz_test/null.mp4 -t video/mp4'
        )
        
        if code == -1:
            print("   🔴 CRASH detected!")
        else:
            print("   ✅ No crash")
        
        # Cleanup
        self.run_cmd("rm -rf /sdcard/fuzz_test")
    
    def fuzz_camera_service(self):
        """Fuzz camera service"""
        print(f"\n{'=' * 70}")
        print(f"  FUZZING: Camera Service")
        print(f"{'=' * 70}\n")
        
        camera_services = [s for s in self.services if 'camera' in s.lower()]
        
        if not camera_services:
            print("❌ No camera services found")
            return
        
        for cam_srv in camera_services:
            print(f"\n📷 Testing: {cam_srv}")
            
            # Test with invalid camera IDs
            for cam_id in [-1, 999, 0x7FFFFFFF, -0x80000000]:
                print(f"   Testing camera ID: {cam_id}")
                
                stdout, stderr, code = self.fuzz_service_call(
                    cam_srv, 
                    1,  # Common transaction: connect
                    f"i32 {cam_id}"
                )
                
                if code == -1:
                    print(f"      🔴 CRASH with camera ID: {cam_id}")
                    self.crashes.append({
                        "service": cam_srv,
                        "camera_id": cam_id,
                        "type": "Invalid Camera ID"
                    })
    
    def check_dmesg_for_crashes(self):
        """Check kernel log for crashes"""
        print(f"\n{'=' * 70}")
        print(f"  CHECKING KERNEL LOG (dmesg)")
        print(f"{'=' * 70}\n")
        
        stdout, stderr, code = self.run_cmd("dmesg | tail -n 100")
        
        if stdout:
            # Look for crash indicators
            crash_keywords = [
                'segfault', 'oops', 'panic', 'bug', 'crash',
                'kernel bug', 'unable to handle', 'invalid opcode'
            ]
            
            found_crashes = []
            
            for line in stdout.split('\n'):
                for keyword in crash_keywords:
                    if keyword in line.lower():
                        found_crashes.append(line)
                        break
            
            if found_crashes:
                print(f"🔴 Found {len(found_crashes)} potential crashes in kernel log:\n")
                for crash in found_crashes[-10:]:  # Show last 10
                    print(f"  {crash}")
            else:
                print("✅ No crashes found in recent kernel log")
        else:
            print("❌ Cannot read kernel log (permission denied)")
    
    def scan_procfs(self):
        """Scan /proc for world-readable sensitive files"""
        print(f"\n{'=' * 70}")
        print(f"  SCANNING /proc FOR INFO LEAKS")
        print(f"{'=' * 70}\n")
        
        # Sensitive proc files
        sensitive_files = [
            '/proc/version',
            '/proc/cpuinfo',
            '/proc/meminfo',
            '/proc/net/tcp',
            '/proc/net/udp',
            '/proc/net/route',
            '/proc/net/arp',
            '/proc/self/maps',
            '/proc/self/status',
            '/proc/self/cmdline',
        ]
        
        leaks = []
        
        for path in sensitive_files:
            stdout, stderr, code = self.run_cmd(f"cat {path}")
            
            if code == 0 and stdout:
                print(f"✅ {path} - READABLE")
                
                # Check for sensitive info
                if 'root' in stdout or '0.0.0.0' in stdout:
                    print(f"   ⚠️  May contain sensitive information")
                    leaks.append(path)
            else:
                print(f"❌ {path} - Not accessible")
        
        if leaks:
            print(f"\n🔴 Found {len(leaks)} files with potential info leaks")
    
    def fuzz_system_properties(self):
        """Try to set dangerous system properties"""
        print(f"\n{'=' * 70}")
        print(f"  FUZZING SYSTEM PROPERTIES")
        print(f"{'=' * 70}\n")
        
        dangerous_props = [
            'ro.debuggable=1',
            'ro.secure=0',
            'ro.adb.secure=0',
            'persist.sys.usb.config=adb',
            'persist.service.adb.enable=1',
            'persist.service.debuggable=1',
            'ro.build.selinux=0',
        ]
        
        print("Testing if we can modify security-critical properties...\n")
        
        for prop in dangerous_props:
            key, value = prop.split('=')
            
            stdout, stderr, code = self.run_cmd(f"setprop {key} {value}")
            
            # Check if it was set
            stdout2, _, _ = self.run_cmd(f"getprop {key}")
            
            if value in stdout2:
                print(f"🔴 CRITICAL: Successfully set {key} to {value}!")
                self.crashes.append({
                    "type": "Property Injection",
                    "property": key,
                    "value": value,
                    "severity": "CRITICAL"
                })
            else:
                print(f"✅ {key} - Protected")
    
    def generate_report(self):
        """Generate final report"""
        print(f"\n{'=' * 70}")
        print(f"  FUZZING REPORT")
        print(f"{'=' * 70}\n")
        
        if not self.crashes:
            print("✅ No crashes or vulnerabilities found")
            print("\nNote: This doesn't mean the device is secure!")
            print("      - Some vulnerabilities require deeper analysis")
            print("      - Try reverse engineering the binaries next")
            return
        
        print(f"🔴 Total Issues Found: {len(self.crashes)}\n")
        
        # Group by type
        by_type = {}
        for crash in self.crashes:
            crash_type = crash.get('type', 'Unknown')
            if crash_type not in by_type:
                by_type[crash_type] = []
            by_type[crash_type].append(crash)
        
        for crash_type, items in by_type.items():
            print(f"\n📋 {crash_type}: {len(items)} instances")
            for item in items[:5]:  # Show first 5
                print(f"   {item}")
        
        # Save to file
        import json
        from datetime import datetime
        
        report_file = f"fuzzing_results_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(report_file, 'w') as f:
            json.dump(self.crashes, f, indent=2)
        
        print(f"\n📄 Full report saved: {report_file}")
    
    def run_all(self):
        """Run all fuzzing tests"""
        print("""
╔════════════════════════════════════════════════════════════════╗
║               BINDER SERVICE FUZZER                            ║
║           Zero-Day Discovery via Fuzzing                       ║
╚════════════════════════════════════════════════════════════════╝
""")
        
        # List all services
        if not self.list_services():
            print("Cannot continue without service list")
            return
        
        print("\nSelect fuzzing mode:")
        print("1. Quick scan (high-risk services only)")
        print("2. Full scan (all services, takes long time)")
        print("3. Media player fuzzing")
        print("4. Camera service fuzzing")
        print("5. System properties fuzzing")
        print("6. Kernel log check")
        print("7. /proc scanning")
        print("8. Run ALL tests")
        
        choice = input("\nChoice [1-8]: ").strip()
        
        if choice == "1":
            # Quick scan
            high_risk = ['media', 'camera', 'audio', 'phone', 'bluetooth']
            for srv in self.services:
                for keyword in high_risk:
                    if keyword in srv.lower():
                        self.fuzz_service(srv, max_transactions=20)
                        break
        
        elif choice == "2":
            # Full scan
            for srv in self.services[:50]:  # First 50 services
                self.fuzz_service(srv, max_transactions=50)
        
        elif choice == "3":
            self.fuzz_media_player()
        
        elif choice == "4":
            self.fuzz_camera_service()
        
        elif choice == "5":
            self.fuzz_system_properties()
        
        elif choice == "6":
            self.check_dmesg_for_crashes()
        
        elif choice == "7":
            self.scan_procfs()
        
        elif choice == "8":
            # Run everything
            print("\n🚀 Running ALL fuzzing tests...\n")
            
            # Fuzz high-risk services
            high_risk = ['media', 'camera', 'audio']
            for srv in self.services:
                for keyword in high_risk:
                    if keyword in srv.lower():
                        self.fuzz_service(srv, max_transactions=30)
                        break
            
            self.fuzz_media_player()
            self.fuzz_camera_service()
            self.fuzz_system_properties()
            self.check_dmesg_for_crashes()
            self.scan_procfs()
        
        else:
            print("Invalid choice")
            return
        
        # Generate report
        self.generate_report()
        
        print("""
╔════════════════════════════════════════════════════════════════╗
║                    FUZZING COMPLETE                            ║
╚════════════════════════════════════════════════════════════════╝

Next steps if crashes found:
1. Reproduce the crash consistently
2. Analyze crash with debugger
3. Determine if exploitable (control PC/memory)
4. Develop proof-of-concept exploit

⚠️  If you found a real vulnerability:
   - Do NOT share publicly yet
   - Contact vendor for responsible disclosure
   - Wait for patch before public disclosure
""")

def main():
    fuzzer = BinderFuzzer()
    fuzzer.run_all()

if __name__ == "__main__":
    main()
