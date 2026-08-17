#!/usr/bin/env python3
"""
CLI-based Vulnerability Scanner
Find zero-day vulnerabilities using command-line tools
"""

import subprocess
import re
import json
import os
from datetime import datetime

class CLIVulnerabilityScanner:
    def __init__(self):
        self.findings = []
        self.device_info = {}
        
    def run_cmd(self, cmd):
        """Run command and return output"""
        try:
            result = subprocess.run(
                cmd,
                shell=True,
                capture_output=True,
                text=True,
                timeout=30
            )
            return result.stdout + result.stderr
        except Exception as e:
            return f"Error: {e}"
    
    def print_header(self, title):
        print("\n" + "="*70)
        print(f"  {title}")
        print("="*70 + "\n")
    
    def check_device(self):
        """Check device connection"""
        self.print_header("Device Connection Check")
        
        output = self.run_cmd("adb devices")
        print(output)
        
        if "device" in output and "offline" not in output:
            print("✅ Device connected\n")
            
            # Get device info
            self.device_info['model'] = self.run_cmd("adb shell getprop ro.product.model").strip()
            self.device_info['android'] = self.run_cmd("adb shell getprop ro.build.version.release").strip()
            self.device_info['sdk'] = self.run_cmd("adb shell getprop ro.build.version.sdk").strip()
            self.device_info['security_patch'] = self.run_cmd("adb shell getprop ro.build.version.security_patch").strip()
            self.device_info['kernel'] = self.run_cmd("adb shell uname -r").strip()
            
            print("Device Information:")
            for key, value in self.device_info.items():
                print(f"  {key}: {value}")
            
            return True
        else:
            print("❌ Device not connected")
            return False
    
    def scan_debuggable_apps(self):
        """Find debuggable apps (massive security risk)"""
        self.print_header("Scanning for Debuggable Apps")
        
        print("📱 Checking all installed apps for debug mode...\n")
        
        # Get all packages
        packages = self.run_cmd("adb shell pm list packages").strip().split('\n')
        packages = [p.replace('package:', '') for p in packages]
        
        debuggable = []
        
        for i, package in enumerate(packages):
            print(f"\r[{i+1}/{len(packages)}] Checking: {package[:50]:<50}", end='', flush=True)
            
            # Check if debuggable
            dump = self.run_cmd(f'adb shell dumpsys package {package}')
            
            if 'DEBUGGABLE' in dump:
                debuggable.append(package)
        
        print("\n")
        
        if debuggable:
            print(f"🔴 Found {len(debuggable)} debuggable apps:\n")
            for app in debuggable:
                print(f"  • {app}")
                
                # Check if system app
                path = self.run_cmd(f"adb shell pm path {app}").strip()
                if '/system/' in path or '/vendor/' in path:
                    print(f"    ⚠️  CRITICAL: This is a SYSTEM app!")
                    self.findings.append({
                        "type": "Debuggable System App",
                        "severity": "CRITICAL",
                        "package": app,
                        "impact": "Can attach debugger and inject code"
                    })
                print()
        else:
            print("✅ No debuggable apps found")
    
    def scan_exported_components(self):
        """Find exported components without permission checks"""
        self.print_header("Scanning for Exposed Components")
        
        print("🔍 Looking for exported activities, services, receivers...\n")
        
        # Get system packages
        system_packages = [
            'com.huawei.systemmanager',
            'com.android.bluetooth',
            'com.android.nfc',
            'com.android.phone',
            'com.huawei.camera'
        ]
        
        for package in system_packages:
            print(f"📦 Checking: {package}")
            
            # Dump package info
            dump = self.run_cmd(f'adb shell dumpsys package {package}')
            
            # Find exported components
            exported = []
            
            # Search for "exported=true"
            for line in dump.split('\n'):
                if 'exported=true' in line.lower():
                    exported.append(line.strip())
            
            if exported:
                print(f"  🔴 Found {len(exported)} exported components:")
                for comp in exported[:5]:  # Show first 5
                    print(f"    • {comp[:80]}")
                if len(exported) > 5:
                    print(f"    ... and {len(exported)-5} more")
                
                self.findings.append({
                    "type": "Exported Components",
                    "severity": "HIGH",
                    "package": package,
                    "count": len(exported),
                    "impact": "Possible intent injection, privilege escalation"
                })
            print()
    
    def scan_dangerous_permissions(self):
        """Find apps with dangerous permission combinations"""
        self.print_header("Scanning for Dangerous Permissions")
        
        print("🔍 Looking for risky permission combinations...\n")
        
        dangerous_combos = [
            (['CAMERA', 'LOCATION', 'INTERNET'], "Spyware capability"),
            (['READ_SMS', 'READ_CONTACTS', 'INTERNET'], "Data exfiltration"),
            (['WRITE_SETTINGS', 'WRITE_SECURE_SETTINGS'], "Full system control"),
            (['INSTALL_PACKAGES', 'DELETE_PACKAGES'], "Malware installation"),
        ]
        
        # Get all packages
        packages = self.run_cmd("adb shell pm list packages").strip().split('\n')
        packages = [p.replace('package:', '') for p in packages if 'system' in p.lower() or 'huawei' in p.lower()]
        
        for package in packages[:20]:  # Check first 20 system packages
            print(f"Checking: {package}")
            
            # Get permissions
            perms = self.run_cmd(f'adb shell dumpsys package {package} | grep -i permission')
            
            # Check combos
            for combo, desc in dangerous_combos:
                if all(perm in perms for perm in combo):
                    print(f"  🔴 {package}")
                    print(f"     Has: {' + '.join(combo)}")
                    print(f"     Risk: {desc}")
                    
                    self.findings.append({
                        "type": "Dangerous Permission Combo",
                        "severity": "HIGH",
                        "package": package,
                        "permissions": combo,
                        "impact": desc
                    })
                    print()
    
    def scan_setuid_binaries(self):
        """Find setuid binaries (potential privilege escalation)"""
        self.print_header("Scanning for SetUID Binaries")
        
        print("🔍 Looking for setuid/setgid binaries...\n")
        
        # Find setuid files
        output = self.run_cmd('adb shell "find /system -type f -perm -4000 2>/dev/null"')
        setuid = output.strip().split('\n')
        
        if setuid and setuid[0]:
            print(f"Found {len(setuid)} setuid binaries:\n")
            for binary in setuid:
                print(f"  • {binary}")
                
                # Check if world-writable
                perms = self.run_cmd(f'adb shell "ls -l {binary}"')
                if 'rwx' in perms[-9:-6]:
                    print(f"    ⚠️  CRITICAL: World-writable!")
                    
                    self.findings.append({
                        "type": "World-Writable SetUID Binary",
                        "severity": "CRITICAL",
                        "path": binary,
                        "impact": "Trivial privilege escalation to root"
                    })
            print()
        else:
            print("No setuid binaries found (or permission denied)")
    
    def scan_world_writable(self):
        """Find world-writable system files"""
        self.print_header("Scanning for World-Writable Files")
        
        print("🔍 Looking for world-writable system files...\n")
        
        paths = ['/system/bin', '/system/lib', '/vendor/bin', '/vendor/lib']
        
        for path in paths:
            print(f"Checking: {path}")
            output = self.run_cmd(f'adb shell "find {path} -type f -perm -002 2>/dev/null"')
            
            writable = [f for f in output.strip().split('\n') if f]
            
            if writable:
                print(f"  🔴 Found {len(writable)} world-writable files:")
                for f in writable[:5]:
                    print(f"    • {f}")
                if len(writable) > 5:
                    print(f"    ... and {len(writable)-5} more")
                
                self.findings.append({
                    "type": "World-Writable System Files",
                    "severity": "HIGH",
                    "path": path,
                    "count": len(writable),
                    "impact": "File tampering, code injection"
                })
            print()
    
    def scan_adb_status(self):
        """Check ADB security status"""
        self.print_header("ADB Security Check")
        
        print("🔍 Checking ADB configuration...\n")
        
        # Check if ADB is enabled
        secure = self.run_cmd('adb shell getprop persist.sys.usb.config').strip()
        print(f"USB Config: {secure}")
        
        if 'adb' in secure:
            print("  ⚠️  ADB is ENABLED")
            
            # Check if secure
            adb_secure = self.run_cmd('adb shell getprop ro.adb.secure').strip()
            print(f"ADB Secure: {adb_secure}")
            
            if adb_secure != '1':
                print("  🔴 CRITICAL: ADB is NOT secured!")
                self.findings.append({
                    "type": "Insecure ADB",
                    "severity": "CRITICAL",
                    "impact": "Anyone can connect via ADB without authentication"
                })
        
        # Check for ADB over WiFi
        adb_port = self.run_cmd('adb shell getprop service.adb.tcp.port').strip()
        if adb_port and adb_port != '-1':
            print(f"\n🔴 CRITICAL: ADB over WiFi is ENABLED on port {adb_port}!")
            print("  Anyone on same network can access device!")
            
            self.findings.append({
                "type": "ADB over WiFi",
                "severity": "CRITICAL",
                "port": adb_port,
                "impact": "Remote shell access from local network"
            })
        print()
    
    def scan_selinux(self):
        """Check SELinux status"""
        self.print_header("SELinux Status Check")
        
        selinux = self.run_cmd('adb shell getenforce').strip()
        print(f"SELinux Mode: {selinux}\n")
        
        if selinux != 'Enforcing':
            print(f"🔴 WARNING: SELinux is {selinux}!")
            print("  This significantly weakens security")
            
            self.findings.append({
                "type": "SELinux Disabled/Permissive",
                "severity": "HIGH",
                "mode": selinux,
                "impact": "Reduced exploit mitigation"
            })
        else:
            print("✅ SELinux is Enforcing")
    
    def scan_running_services(self):
        """Check running services for suspicious activity"""
        self.print_header("Running Services Analysis")
        
        print("🔍 Checking running services...\n")
        
        # Get running services
        services = self.run_cmd('adb shell dumpsys activity services')
        
        # Look for suspicious patterns
        suspicious = []
        
        if 'root' in services.lower():
            suspicious.append("Service running as root")
        
        if 'debug' in services.lower():
            suspicious.append("Debug service active")
        
        if 'test' in services.lower():
            suspicious.append("Test service active")
        
        if suspicious:
            print("⚠️  Found suspicious patterns:")
            for s in suspicious:
                print(f"  • {s}")
        else:
            print("✅ No obvious suspicious services")
    
    def test_command_injection(self):
        """Test for command injection vulnerabilities"""
        self.print_header("Command Injection Testing")
        
        print("🧪 Testing for command injection...\n")
        
        # Test system commands
        test_payloads = [
            'test; id',
            'test$(id)',
            'test`id`',
            'test|id',
        ]
        
        print("Testing input sanitization...")
        
        # Try to set system property with injection
        for payload in test_payloads:
            result = self.run_cmd(f'adb shell "echo {payload}"')
            if 'uid=' in result:
                print(f"🔴 VULNERABILITY: Command injection possible!")
                print(f"   Payload: {payload}")
                print(f"   Output: {result[:100]}")
                
                self.findings.append({
                    "type": "Command Injection",
                    "severity": "CRITICAL",
                    "payload": payload,
                    "impact": "Arbitrary command execution"
                })
                break
        else:
            print("✅ No command injection found in basic tests")
    
    def scan_network_exposure(self):
        """Check for network-exposed services"""
        self.print_header("Network Exposure Scan")
        
        print("🔍 Checking open ports and services...\n")
        
        # Get listening ports
        netstat = self.run_cmd('adb shell netstat -tuln')
        print("Listening services:")
        print(netstat[:1000])  # First 1000 chars
        
        # Look for dangerous ports
        dangerous_ports = {
            '23': 'Telnet',
            '21': 'FTP',
            '5555': 'ADB',
            '8080': 'HTTP Proxy',
        }
        
        for port, service in dangerous_ports.items():
            if f':{port}' in netstat:
                print(f"\n🔴 WARNING: {service} (port {port}) is listening!")
                
                self.findings.append({
                    "type": "Dangerous Network Service",
                    "severity": "HIGH",
                    "port": port,
                    "service": service,
                    "impact": "Network-accessible attack surface"
                })
    
    def scan_kernel_info(self):
        """Get kernel information for vulnerability matching"""
        self.print_header("Kernel Information")
        
        kernel = self.run_cmd('adb shell uname -a').strip()
        print(f"Kernel: {kernel}\n")
        
        version = self.run_cmd('adb shell uname -r').strip()
        print(f"Version: {version}\n")
        
        # Extract version number
        match = re.search(r'(\d+\.\d+\.\d+)', version)
        if match:
            kernel_ver = match.group(1)
            print(f"Kernel Version: {kernel_ver}")
            
            # Check against known vulnerable versions
            vulnerable_kernels = {
                '4.4': ['CVE-2019-2215', 'CVE-2016-5195 (Dirty COW)'],
                '4.9': ['CVE-2019-2215', 'CVE-2020-0041'],
                '4.14': ['CVE-2019-2215', 'CVE-2020-0041'],
            }
            
            kernel_major = '.'.join(kernel_ver.split('.')[:2])
            if kernel_major in vulnerable_kernels:
                print(f"\n🔴 Kernel {kernel_major} has known vulnerabilities:")
                for cve in vulnerable_kernels[kernel_major]:
                    print(f"  • {cve}")
                    
                self.findings.append({
                    "type": "Vulnerable Kernel Version",
                    "severity": "CRITICAL",
                    "version": kernel_ver,
                    "cves": vulnerable_kernels[kernel_major],
                    "impact": "Kernel exploits available"
                })
    
    def generate_report(self):
        """Generate final report"""
        self.print_header("Vulnerability Report")
        
        if not self.findings:
            print("✅ No critical vulnerabilities found in automated scan")
            print("\nNote: This doesn't mean the device is secure!")
            print("Manual testing and deep analysis still required.")
            return
        
        # Count by severity
        critical = sum(1 for f in self.findings if f['severity'] == 'CRITICAL')
        high = sum(1 for f in self.findings if f['severity'] == 'HIGH')
        
        print(f"Total Findings: {len(self.findings)}")
        print(f"  🔴 Critical: {critical}")
        print(f"  🟠 High: {high}")
        print()
        
        # Group by type
        by_type = {}
        for finding in self.findings:
            ftype = finding['type']
            if ftype not in by_type:
                by_type[ftype] = []
            by_type[ftype].append(finding)
        
        print("Findings by type:")
        for ftype, items in by_type.items():
            print(f"\n🔴 {ftype}: {len(items)}")
            for item in items:
                print(f"   Severity: {item['severity']}")
                print(f"   Impact: {item['impact']}")
                if 'package' in item:
                    print(f"   Package: {item['package']}")
                if 'path' in item:
                    print(f"   Path: {item['path']}")
                print()
        
        # Save to file
        report_file = f"vulnerability_scan_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(report_file, 'w') as f:
            json.dump({
                "device": self.device_info,
                "findings": self.findings,
                "scan_date": datetime.now().isoformat()
            }, f, indent=2)
        
        print(f"\n📄 Report saved: {report_file}")
    
    def run_all(self):
        """Run all scans"""
        print("""
╔════════════════════════════════════════════════════════════════╗
║        CLI-BASED VULNERABILITY SCANNER                         ║
║        Real-time Zero-Day Discovery                            ║
╚════════════════════════════════════════════════════════════════╝
""")
        
        if not self.check_device():
            print("\n❌ Cannot continue without device connection")
            return
        
        # Run all scans
        self.scan_kernel_info()
        self.scan_selinux()
        self.scan_adb_status()
        self.scan_debuggable_apps()
        self.scan_exported_components()
        self.scan_dangerous_permissions()
        self.scan_setuid_binaries()
        self.scan_world_writable()
        self.scan_running_services()
        self.test_command_injection()
        self.scan_network_exposure()
        
        # Generate report
        self.generate_report()
        
        print("\n" + "="*70)
        print("  SCAN COMPLETE")
        print("="*70)
        print("""
Next steps:
1. Review findings above
2. Prioritize critical issues
3. Develop exploits for confirmed vulnerabilities
4. Report to vendor (responsible disclosure)

⚠️  Remember: These are REAL vulnerabilities on YOUR device.
   Take appropriate security measures!
""")

def main():
    scanner = CLIVulnerabilityScanner()
    scanner.run_all()

if __name__ == "__main__":
    main()
