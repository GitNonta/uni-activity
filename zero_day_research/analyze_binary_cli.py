#!/usr/bin/env python3
"""
Binary Analysis Tool - Command Line
Analyze native libraries for vulnerabilities without Ghidra
"""

import subprocess
import re
import os
from pathlib import Path

class BinaryAnalyzer:
    def __init__(self, binary_path):
        self.binary_path = binary_path
        self.binary_name = os.path.basename(binary_path)
        self.findings = []
    
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
    
    def check_file_info(self):
        """Get basic file information"""
        print("=" * 70)
        print(f"  FILE INFORMATION: {self.binary_name}")
        print("=" * 70 + "\n")
        
        # File size
        size = os.path.getsize(self.binary_path)
        print(f"Size: {size:,} bytes ({size/1024:.1f} KB)\n")
        
        # File type
        output = self.run_cmd(f'file "{self.binary_path}"')
        print(f"Type: {output}\n")
        
        # Check if stripped
        if 'stripped' in output.lower():
            print("⚠️  Binary is STRIPPED (no debug symbols)")
            print("   This makes analysis harder\n")
        else:
            print("✅ Binary has symbols (easier to analyze)\n")
    
    def search_dangerous_functions(self):
        """Search for dangerous C functions"""
        print("=" * 70)
        print("  SEARCHING FOR DANGEROUS FUNCTIONS")
        print("=" * 70 + "\n")
        
        dangerous_funcs = {
            # Buffer overflow prone
            'strcpy': 'CRITICAL - Buffer overflow (no bounds check)',
            'strcat': 'CRITICAL - Buffer overflow (no bounds check)',
            'sprintf': 'CRITICAL - Buffer overflow (no bounds check)',
            'gets': 'CRITICAL - Buffer overflow (always unsafe)',
            'scanf': 'HIGH - Buffer overflow if misused',
            
            # Memory issues
            'malloc': 'INFO - Check for integer overflow before malloc',
            'free': 'INFO - Check for use-after-free',
            'memcpy': 'MEDIUM - Buffer overflow if size wrong',
            'memmove': 'MEDIUM - Buffer overflow if size wrong',
            
            # Format string
            'printf': 'MEDIUM - Format string vulnerability if user input',
            'fprintf': 'MEDIUM - Format string vulnerability if user input',
            'snprintf': 'LOW - Safer alternative (but check usage)',
            
            # Command execution
            'system': 'CRITICAL - Command injection possible',
            'exec': 'CRITICAL - Command injection possible',
            'popen': 'CRITICAL - Command injection possible',
        }
        
        # Use strings command to extract readable strings
        output = self.run_cmd(f'strings "{self.binary_path}"')
        
        found = {}
        for func, risk in dangerous_funcs.items():
            if func in output:
                found[func] = risk
        
        if found:
            print(f"🔴 Found {len(found)} dangerous functions:\n")
            
            # Sort by severity
            critical = {k: v for k, v in found.items() if 'CRITICAL' in v}
            high = {k: v for k, v in found.items() if 'HIGH' in v}
            medium = {k: v for k, v in found.items() if 'MEDIUM' in v}
            
            if critical:
                print("🔴 CRITICAL:")
                for func, risk in critical.items():
                    print(f"   • {func:15s} - {risk}")
                    self.findings.append({
                        "type": "Dangerous Function",
                        "function": func,
                        "severity": "CRITICAL",
                        "risk": risk
                    })
            
            if high:
                print("\n🟠 HIGH:")
                for func, risk in high.items():
                    print(f"   • {func:15s} - {risk}")
            
            if medium:
                print("\n🟡 MEDIUM:")
                for func, risk in medium.items():
                    print(f"   • {func:15s} - {risk}")
            
            print()
        else:
            print("✅ No obviously dangerous functions found\n")
    
    def search_suspicious_strings(self):
        """Search for suspicious strings"""
        print("=" * 70)
        print("  SEARCHING FOR SUSPICIOUS STRINGS")
        print("=" * 70 + "\n")
        
        output = self.run_cmd(f'strings "{self.binary_path}"')
        
        patterns = {
            'Passwords/Keys': [
                r'password', r'passwd', r'pwd',
                r'secret', r'key', r'token',
                r'api[_-]?key', r'access[_-]?token'
            ],
            'File Paths': [
                r'/data/local/tmp', r'/sdcard',
                r'/system/bin', r'/proc/',
                r'\.\./', r'\.\.\\'
            ],
            'Debug/Test': [
                r'debug', r'test', r'demo',
                r'backdoor', r'root'
            ],
            'URLs': [
                r'https?://', r'ftp://',
                r'ws://', r'wss://'
            ],
            'SQL/Commands': [
                r'SELECT.*FROM', r'INSERT.*INTO',
                r'DROP TABLE', r'DELETE FROM',
                r'sh -c', r'cmd /c'
            ]
        }
        
        for category, pattern_list in patterns.items():
            matches = []
            for pattern in pattern_list:
                found = re.findall(pattern, output, re.IGNORECASE)
                matches.extend(found)
            
            if matches:
                unique_matches = list(set(matches))[:10]  # First 10 unique
                print(f"🔍 {category}: {len(unique_matches)} found")
                for match in unique_matches[:5]:  # Show 5
                    print(f"   • {match}")
                if len(unique_matches) > 5:
                    print(f"   ... and {len(unique_matches)-5} more")
                print()
                
                if category in ['Passwords/Keys', 'Debug/Test']:
                    self.findings.append({
                        "type": "Suspicious Strings",
                        "category": category,
                        "count": len(unique_matches),
                        "severity": "HIGH"
                    })
    
    def check_security_features(self):
        """Check binary security features"""
        print("=" * 70)
        print("  SECURITY FEATURES CHECK")
        print("=" * 70 + "\n")
        
        # Try readelf (if available on Windows via WSL or Git Bash)
        output = self.run_cmd(f'strings "{self.binary_path}" | grep -i "stack"')
        
        features = {
            'Stack Canary': ('__stack_chk_fail', '✅ ENABLED', '❌ DISABLED'),
            'FORTIFY': ('_chk', '✅ ENABLED', '❌ DISABLED'),
        }
        
        # Check for each feature
        all_strings = self.run_cmd(f'strings "{self.binary_path}"')
        
        for feature, (marker, enabled, disabled) in features.items():
            if marker in all_strings:
                print(f"{enabled:15s} - {feature}")
            else:
                print(f"{disabled:15s} - {feature}")
                self.findings.append({
                    "type": "Missing Security Feature",
                    "feature": feature,
                    "severity": "MEDIUM",
                    "impact": "Easier to exploit memory corruption"
                })
        
        print()
    
    def search_format_strings(self):
        """Search for potential format string vulnerabilities"""
        print("=" * 70)
        print("  FORMAT STRING ANALYSIS")
        print("=" * 70 + "\n")
        
        output = self.run_cmd(f'strings "{self.binary_path}"')
        
        # Look for format strings
        format_patterns = [
            r'%s', r'%d', r'%x', r'%n',
            r'%p', r'%[0-9]+\$'
        ]
        
        format_strings = []
        for line in output.split('\n'):
            for pattern in format_patterns:
                if re.search(pattern, line):
                    format_strings.append(line.strip())
                    break
        
        if format_strings:
            print(f"Found {len(format_strings)} format strings:\n")
            
            # Show interesting ones
            for fmt in format_strings[:20]:
                if len(fmt) > 5:  # Skip very short strings
                    print(f"   {fmt}")
            
            # Check for %n (write to memory)
            dangerous = [f for f in format_strings if '%n' in f]
            if dangerous:
                print(f"\n🔴 Found {len(dangerous)} strings with %n (memory write!):")
                for d in dangerous:
                    print(f"   {d}")
                
                self.findings.append({
                    "type": "Format String with %n",
                    "count": len(dangerous),
                    "severity": "CRITICAL",
                    "impact": "Arbitrary memory write possible"
                })
            print()
        else:
            print("No format strings found\n")
    
    def search_system_calls(self):
        """Search for system/exec calls"""
        print("=" * 70)
        print("  SYSTEM CALL ANALYSIS")
        print("=" * 70 + "\n")
        
        output = self.run_cmd(f'strings "{self.binary_path}"')
        
        # Look for shell commands
        shell_patterns = [
            'sh ', 'bash ', '/bin/',
            'chmod', 'chown', 'su ',
            'mount', 'insmod', 'rmmod'
        ]
        
        found_commands = []
        for line in output.split('\n'):
            for pattern in shell_patterns:
                if pattern in line.lower():
                    found_commands.append(line.strip())
                    break
        
        if found_commands:
            print(f"🔴 Found {len(found_commands)} potential shell commands:\n")
            for cmd in found_commands[:15]:
                if len(cmd) > 3:
                    print(f"   {cmd}")
            
            # Check for privilege escalation
            priv_keywords = ['su', 'root', 'chmod 777', 'chown root']
            dangerous_cmds = [c for c in found_commands 
                            for k in priv_keywords if k in c.lower()]
            
            if dangerous_cmds:
                print(f"\n🔴 CRITICAL: Privilege escalation commands found!")
                for cmd in dangerous_cmds:
                    print(f"   {cmd}")
                
                self.findings.append({
                    "type": "Privilege Escalation Commands",
                    "count": len(dangerous_cmds),
                    "severity": "CRITICAL"
                })
            print()
        else:
            print("No shell commands found\n")
    
    def search_crypto_keys(self):
        """Search for hardcoded crypto keys"""
        print("=" * 70)
        print("  CRYPTO KEY ANALYSIS")
        print("=" * 70 + "\n")
        
        output = self.run_cmd(f'strings "{self.binary_path}"')
        
        # Look for patterns
        patterns = {
            'Hex Keys': r'[0-9a-fA-F]{32,}',
            'Base64': r'[A-Za-z0-9+/]{40,}={0,2}',
            'PEM Headers': r'-----BEGIN .* KEY-----',
        }
        
        for name, pattern in patterns.items():
            matches = re.findall(pattern, output)
            if matches:
                print(f"⚠️  {name}: {len(matches)} found")
                for match in matches[:3]:
                    print(f"   {match[:60]}...")
                print()
                
                self.findings.append({
                    "type": "Hardcoded Crypto Material",
                    "pattern": name,
                    "count": len(matches),
                    "severity": "HIGH"
                })
    
    def check_jni_exports(self):
        """Check JNI exported functions"""
        print("=" * 70)
        print("  JNI EXPORTS ANALYSIS")
        print("=" * 70 + "\n")
        
        output = self.run_cmd(f'strings "{self.binary_path}"')
        
        # Look for JNI functions
        jni_funcs = []
        for line in output.split('\n'):
            if 'Java_' in line and '(' in line:
                jni_funcs.append(line.strip())
        
        if jni_funcs:
            print(f"Found {len(jni_funcs)} JNI functions:\n")
            for func in jni_funcs[:15]:
                print(f"   {func}")
            
            if len(jni_funcs) > 15:
                print(f"   ... and {len(jni_funcs)-15} more")
            
            print("\n✅ These are entry points from Java code")
            print("   Fuzz these functions for vulnerabilities\n")
        else:
            print("No JNI exports found (not a JNI library)\n")
    
    def generate_report(self):
        """Generate analysis report"""
        print("=" * 70)
        print("  ANALYSIS SUMMARY")
        print("=" * 70 + "\n")
        
        if not self.findings:
            print("✅ No critical issues found in static analysis")
            print("\nNote: Static analysis has limitations!")
            print("      - Need dynamic analysis (debugging, fuzzing)")
            print("      - Need reverse engineering for logic flaws")
            return
        
        # Count by severity
        critical = [f for f in self.findings if f.get('severity') == 'CRITICAL']
        high = [f for f in self.findings if f.get('severity') == 'HIGH']
        medium = [f for f in self.findings if f.get('severity') == 'MEDIUM']
        
        print(f"Total Findings: {len(self.findings)}")
        print(f"  🔴 Critical: {len(critical)}")
        print(f"  🟠 High: {len(high)}")
        print(f"  🟡 Medium: {len(medium)}")
        print()
        
        if critical:
            print("🔴 CRITICAL FINDINGS:")
            for finding in critical:
                print(f"\n   Type: {finding['type']}")
                for key, value in finding.items():
                    if key not in ['type', 'severity']:
                        print(f"   {key}: {value}")
        
        # Save report
        import json
        from datetime import datetime
        
        report_file = f"binary_analysis_{self.binary_name}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(report_file, 'w') as f:
            json.dump({
                "binary": self.binary_name,
                "path": self.binary_path,
                "findings": self.findings,
                "analysis_date": datetime.now().isoformat()
            }, f, indent=2)
        
        print(f"\n📄 Report saved: {report_file}")
    
    def analyze(self):
        """Run all analysis"""
        print(f"""
╔════════════════════════════════════════════════════════════════╗
║            BINARY STATIC ANALYSIS TOOL                         ║
║          Find vulnerabilities without disassembly              ║
╚════════════════════════════════════════════════════════════════╝
""")
        
        if not os.path.exists(self.binary_path):
            print(f"❌ File not found: {self.binary_path}")
            return
        
        self.check_file_info()
        self.search_dangerous_functions()
        self.search_suspicious_strings()
        self.check_security_features()
        self.search_format_strings()
        self.search_system_calls()
        self.search_crypto_keys()
        self.check_jni_exports()
        self.generate_report()
        
        print(f"""
╔════════════════════════════════════════════════════════════════╗
║                   ANALYSIS COMPLETE                            ║
╚════════════════════════════════════════════════════════════════╝

Next steps:
1. Review critical findings above
2. Use Ghidra/IDA for deeper analysis of suspicious functions
3. Set up dynamic analysis with Frida
4. Fuzz the identified entry points

Priority files to analyze:
  1. libbinder.so (Binder IPC - most exploited)
  2. libstagefright.so (Media - history of vulns)
  3. libcamera_client.so (Camera - attack surface)
""")

def main():
    import sys
    
    if len(sys.argv) < 2:
        print("Usage: python analyze_binary_cli.py <binary_path>")
        print("\nOr analyze all binaries in firmware directory:")
        print("python analyze_binary_cli.py --all")
        return
    
    if sys.argv[1] == "--all":
        # Analyze all binaries
        firmware_dir = Path("zero_day_research/firmware")
        if not firmware_dir.exists():
            print(f"❌ Directory not found: {firmware_dir}")
            return
        
        binaries = list(firmware_dir.glob("*.so")) + list(firmware_dir.glob("app_process*"))
        
        if not binaries:
            print(f"❌ No binaries found in {firmware_dir}")
            return
        
        print(f"\n📦 Found {len(binaries)} binaries to analyze\n")
        
        for i, binary in enumerate(binaries, 1):
            print(f"\n{'#' * 70}")
            print(f"  ANALYZING {i}/{len(binaries)}: {binary.name}")
            print(f"{'#' * 70}\n")
            
            analyzer = BinaryAnalyzer(str(binary))
            analyzer.analyze()
            
            input("\nPress Enter to continue to next binary...")
    else:
        # Analyze single binary
        analyzer = BinaryAnalyzer(sys.argv[1])
        analyzer.analyze()

if __name__ == "__main__":
    main()
