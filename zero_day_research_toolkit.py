#!/usr/bin/env python3
"""
Zero-Day Research Toolkit
Advanced tools for discovering new vulnerabilities
"""

import subprocess
import os
import sys
import json
import struct
from pathlib import Path
from datetime import datetime

class ZeroDayResearchToolkit:
    def __init__(self):
        self.device_connected = False
        self.extracted_files = []
        self.findings = []
        self.workspace = Path("d:/projects/uni-activity/zero_day_research")
        self.workspace.mkdir(exist_ok=True)
        
    def print_header(self, title, style="info"):
        styles = {
            "info": "96",
            "success": "92",
            "warning": "93",
            "danger": "91"
        }
        color = styles.get(style, "96")
        print(f"\n\033[{color}m{'='*70}")
        print(f"  {title}")
        print(f"{'='*70}\033[0m\n")
    
    def check_device(self):
        """Check ADB connection"""
        result = subprocess.run("adb devices", shell=True, capture_output=True, text=True)
        self.device_connected = "device" in result.stdout and "offline" not in result.stdout
        return self.device_connected
    
    def setup_research_environment(self):
        """Setup research environment"""
        self.print_header("Setting Up Research Environment", "info")
        
        # Create directory structure
        dirs = [
            "firmware",
            "extracted_apps",
            "decompiled",
            "fuzzing_corpus",
            "fuzzing_findings",
            "reverse_engineering",
            "source_analysis",
            "reports"
        ]
        
        for d in dirs:
            path = self.workspace / d
            path.mkdir(exist_ok=True)
            print(f"✅ Created: {path}")
        
        print(f"\n📂 Workspace: {self.workspace}")
        
        # Check required tools
        print("\n🔧 Checking required tools...")
        tools = {
            "adb": "adb version",
            "python": "python --version",
            "7zip": "7z",
        }
        
        for tool, cmd in tools.items():
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
            if result.returncode == 0:
                print(f"  ✅ {tool}: Available")
            else:
                print(f"  ❌ {tool}: Not found")
        
        # Recommended tools
        print("\n📦 Recommended tools to install:")
        print("  • Ghidra - Reverse engineering (https://ghidra-sre.org/)")
        print("  • IDA Pro - Disassembler")
        print("  • Frida - Dynamic instrumentation (pip install frida-tools)")
        print("  • radare2 - Binary analysis")
        print("  • AFL - Fuzzer (American Fuzzy Lop)")
        print("  • jadx - Android decompiler (https://github.com/skylot/jadx)")
        print("  • apktool - APK reverse engineering")
    
    def extract_device_firmware(self):
        """Extract system components from device"""
        self.print_header("Extracting Device Firmware", "warning")
        
        if not self.check_device():
            print("❌ Device not connected")
            print("\nConnect device via:")
            print("  adb connect <IP>:5555")
            return
        
        print("✅ Device connected")
        print("\n📥 Extracting system files for analysis...\n")
        
        # Critical files to extract
        targets = {
            "/system/bin/app_process32": "App process (Zygote)",
            "/system/bin/app_process64": "App process 64-bit",
            "/system/lib/libbinder.so": "Binder library",
            "/system/lib64/libbinder.so": "Binder library 64-bit",
            "/system/lib/libstagefright.so": "Media framework",
            "/system/lib/libcamera_client.so": "Camera client",
            "/system/lib/libmediaplayerservice.so": "Media player service",
            "/vendor/lib/libwifidriver.so": "WiFi driver (if available)",
            "/system/bin/surfaceflinger": "Surface flinger",
            "/system/bin/mediaserver": "Media server",
        }
        
        extracted_count = 0
        fw_dir = self.workspace / "firmware"
        
        for remote_path, description in targets.items():
            local_name = remote_path.replace("/", "_").lstrip("_")
            local_path = fw_dir / local_name
            
            print(f"Extracting: {remote_path}")
            print(f"  Description: {description}")
            
            result = subprocess.run(
                f'adb pull {remote_path} "{local_path}"',
                shell=True,
                capture_output=True,
                text=True
            )
            
            if result.returncode == 0 and local_path.exists():
                size = local_path.stat().st_size
                print(f"  ✅ Extracted: {size:,} bytes")
                extracted_count += 1
                self.extracted_files.append({
                    "path": str(local_path),
                    "original": remote_path,
                    "description": description,
                    "size": size
                })
            else:
                print(f"  ❌ Failed: {result.stderr[:100]}")
            print()
        
        print(f"📊 Extracted {extracted_count}/{len(targets)} files")
        
        # Save manifest
        manifest_path = fw_dir / "manifest.json"
        with open(manifest_path, 'w') as f:
            json.dump(self.extracted_files, f, indent=2)
        print(f"📄 Manifest saved: {manifest_path}")
    
    def extract_system_apps(self):
        """Extract system apps for analysis"""
        self.print_header("Extracting System Apps", "warning")
        
        if not self.check_device():
            print("❌ Device not connected")
            return
        
        print("Extracting Huawei system apps...\n")
        
        # Target apps
        apps = [
            "com.huawei.systemmanager",
            "com.huawei.hwid",
            "com.android.bluetooth",
            "com.android.nfc",
            "com.android.phone",
            "com.huawei.camera",
        ]
        
        apps_dir = self.workspace / "extracted_apps"
        extracted = 0
        
        for package in apps:
            print(f"📱 Extracting: {package}")
            
            # Get APK path
            result = subprocess.run(
                f'adb shell pm path {package}',
                shell=True,
                capture_output=True,
                text=True
            )
            
            if "package:" in result.stdout:
                apk_path = result.stdout.strip().replace("package:", "")
                local_path = apps_dir / f"{package}.apk"
                
                # Pull APK
                pull_result = subprocess.run(
                    f'adb pull {apk_path} "{local_path}"',
                    shell=True,
                    capture_output=True,
                    text=True
                )
                
                if pull_result.returncode == 0:
                    print(f"  ✅ Saved: {local_path.name}")
                    extracted += 1
                else:
                    print(f"  ❌ Failed")
            else:
                print(f"  ⚠️  Not found on device")
            print()
        
        print(f"📊 Extracted {extracted}/{len(apps)} apps")
    
    def decompile_apps(self):
        """Decompile APKs for source analysis"""
        self.print_header("Decompiling APKs", "info")
        
        apps_dir = self.workspace / "extracted_apps"
        decompiled_dir = self.workspace / "decompiled"
        
        apks = list(apps_dir.glob("*.apk"))
        
        if not apks:
            print("❌ No APKs found to decompile")
            print(f"   Run extract_system_apps() first")
            return
        
        print(f"Found {len(apks)} APKs to decompile\n")
        
        # Check for jadx
        jadx_check = subprocess.run("jadx --version", shell=True, capture_output=True)
        
        if jadx_check.returncode != 0:
            print("⚠️  jadx not found!")
            print("\nInstall jadx:")
            print("  1. Download from: https://github.com/skylot/jadx/releases")
            print("  2. Extract and add to PATH")
            print("  3. Or use online decompiler: http://www.javadecompilers.com/")
            print("\nManual decompilation:")
            for apk in apks:
                print(f"  jadx -d {decompiled_dir / apk.stem} {apk}")
            return
        
        # Decompile each APK
        for apk in apks:
            output_dir = decompiled_dir / apk.stem
            print(f"Decompiling: {apk.name}")
            
            result = subprocess.run(
                f'jadx -d "{output_dir}" "{apk}"',
                shell=True,
                capture_output=True,
                text=True
            )
            
            if result.returncode == 0:
                print(f"  ✅ Decompiled to: {output_dir}")
            else:
                print(f"  ❌ Failed: {result.stderr[:100]}")
            print()
        
        print(f"📂 Decompiled files in: {decompiled_dir}")
    
    def analyze_native_libraries(self):
        """Analyze native libraries for vulnerabilities"""
        self.print_header("Native Library Analysis", "danger")
        
        fw_dir = self.workspace / "firmware"
        re_dir = self.workspace / "reverse_engineering"
        
        libs = list(fw_dir.glob("*.so")) + list(fw_dir.glob("*_process*"))
        
        if not libs:
            print("❌ No libraries found")
            print("   Run extract_device_firmware() first")
            return
        
        print(f"Found {len(libs)} native binaries to analyze\n")
        
        print("🔍 Static Analysis Tasks:\n")
        
        for lib in libs:
            print(f"📚 {lib.name}")
            print(f"   Path: {lib}")
            
            # Get file info
            size = lib.stat().st_size
            print(f"   Size: {size:,} bytes")
            
            # Check if ELF
            with open(lib, 'rb') as f:
                magic = f.read(4)
                if magic == b'\x7fELF':
                    print(f"   Type: ELF binary")
                else:
                    print(f"   Type: Unknown ({magic.hex()})")
            
            print(f"\n   🔧 Analysis with Ghidra:")
            print(f"      1. Open Ghidra")
            print(f"      2. Import: {lib}")
            print(f"      3. Analyze → Auto Analyze")
            print(f"      4. Look for:")
            print(f"         • Unchecked buffer operations (strcpy, sprintf)")
            print(f"         • Integer overflows")
            print(f"         • Use-after-free patterns")
            print(f"         • Format string bugs")
            print(f"         • Race conditions")
            
            print(f"\n   🔧 Analysis with radare2:")
            print(f"      r2 {lib}")
            print(f"      aaa    # Analyze all")
            print(f"      afl    # List functions")
            print(f"      pdf @ main  # Disassemble function")
            print(f"      /R strcpy   # Find strcpy calls")
            
            # Create analysis script
            script_path = re_dir / f"analyze_{lib.name}.sh"
            with open(script_path, 'w') as f:
                f.write(f"""#!/bin/bash
# Analysis script for {lib.name}

echo "=== Analyzing {lib.name} ==="

# Strings analysis
echo "\\n[+] Extracting strings..."
strings "{lib}" > "{re_dir / f'{lib.name}_strings.txt'}"

# Check for dangerous functions
echo "\\n[+] Checking for dangerous functions..."
strings "{lib}" | grep -E "strcpy|sprintf|gets|system|exec"

# Check for format strings
echo "\\n[+] Checking for format string patterns..."
strings "{lib}" | grep "%"

echo "\\nDone. Results saved to {re_dir}"
""")
            script_path.chmod(0o755)
            print(f"   📄 Analysis script: {script_path}\n")
        
        print("\n💡 Recommended Tools:")
        print("   • Ghidra - Full RE suite (https://ghidra-sre.org/)")
        print("   • IDA Pro - Industry standard")
        print("   • radare2 - CLI-based (apt install radare2)")
        print("   • Binary Ninja - Modern RE tool")
    
    def source_code_analysis(self):
        """Analyze decompiled source code for vulnerabilities"""
        self.print_header("Source Code Analysis", "danger")
        
        decompiled_dir = self.workspace / "decompiled"
        source_analysis_dir = self.workspace / "source_analysis"
        
        if not decompiled_dir.exists() or not list(decompiled_dir.iterdir()):
            print("❌ No decompiled code found")
            print("   Run decompile_apps() first")
            return
        
        print("🔍 Searching for vulnerability patterns...\n")
        
        # Vulnerability patterns to search for
        patterns = {
            "Intent Vulnerabilities": [
                "startActivity\\(",
                "startService\\(",
                "sendBroadcast\\(",
                "setResult\\(",
            ],
            "SQL Injection": [
                "execSQL\\(",
                "rawQuery\\(",
                "\\.query\\(",
                "SQLiteDatabase",
            ],
            "Command Injection": [
                "Runtime\\.getRuntime\\(\\)\\.exec",
                "ProcessBuilder",
                "system\\(",
            ],
            "Path Traversal": [
                "getExternalStorage",
                "openFileOutput",
                "FileInputStream",
                "\\.\\./",
            ],
            "Crypto Weaknesses": [
                "DES",
                "MD5",
                "SHA1",
                "ECB",
                "SecureRandom",
            ],
            "Hardcoded Secrets": [
                "password\\s*=",
                "api_key\\s*=",
                "secret\\s*=",
                "token\\s*=",
            ],
            "WebView Issues": [
                "setJavaScriptEnabled\\(true\\)",
                "addJavascriptInterface",
                "loadUrl",
                "setAllowFileAccess",
            ],
            "Privilege Escalation": [
                "android:sharedUserId",
                "SYSTEM_ALERT_WINDOW",
                "WRITE_SECURE_SETTINGS",
                "android:protectionLevel=\"signature\"",
            ]
        }
        
        findings = []
        
        for app_dir in decompiled_dir.iterdir():
            if not app_dir.is_dir():
                continue
            
            print(f"📱 Analyzing: {app_dir.name}\n")
            
            # Search for patterns
            java_files = list(app_dir.rglob("*.java"))
            xml_files = list(app_dir.rglob("*.xml"))
            
            print(f"   Found {len(java_files)} Java files, {len(xml_files)} XML files")
            
            for category, pattern_list in patterns.items():
                print(f"\n   🔍 {category}:")
                
                for pattern in pattern_list:
                    # Search in Java files
                    for java_file in java_files:
                        try:
                            content = java_file.read_text(encoding='utf-8', errors='ignore')
                            if pattern.lower() in content.lower():
                                rel_path = java_file.relative_to(app_dir)
                                print(f"      🔴 Found '{pattern}' in {rel_path}")
                                
                                findings.append({
                                    "app": app_dir.name,
                                    "file": str(rel_path),
                                    "pattern": pattern,
                                    "category": category
                                })
                        except:
                            pass
            
            print()
        
        # Save findings
        report_path = source_analysis_dir / f"findings_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json"
        with open(report_path, 'w') as f:
            json.dump(findings, f, indent=2)
        
        print(f"\n📊 Total findings: {len(findings)}")
        print(f"📄 Report saved: {report_path}")
        
        # Generate actionable report
        print("\n" + "="*70)
        print("  ACTIONABLE FINDINGS")
        print("="*70 + "\n")
        
        by_category = {}
        for finding in findings:
            cat = finding['category']
            if cat not in by_category:
                by_category[cat] = []
            by_category[cat].append(finding)
        
        for category, items in sorted(by_category.items(), key=lambda x: -len(x[1])):
            print(f"🔴 {category}: {len(items)} findings")
            
            # Show sample
            for item in items[:3]:
                print(f"   • {item['app']}: {item['file']}")
            
            if len(items) > 3:
                print(f"   ... and {len(items)-3} more")
            print()
    
    def setup_fuzzing(self):
        """Setup fuzzing environment"""
        self.print_header("Fuzzing Setup", "warning")
        
        fuzzing_dir = self.workspace / "fuzzing_corpus"
        findings_dir = self.workspace / "fuzzing_findings"
        
        print("🔨 Fuzzing Targets:\n")
        
        targets = {
            "Binder IPC": {
                "description": "Test binder transactions",
                "corpus": "Various parcel formats",
                "fuzzer": "Custom Python fuzzer or AFL",
                "priority": "CRITICAL"
            },
            "Media Codecs": {
                "description": "Test media file parsing",
                "corpus": "MP4, MP3, JPEG, PNG files",
                "fuzzer": "AFL with libstagefright",
                "priority": "HIGH"
            },
            "Bluetooth Stack": {
                "description": "Test Bluetooth packet handling",
                "corpus": "L2CAP, RFCOMM packets",
                "fuzzer": "Custom fuzzer + Scapy",
                "priority": "HIGH"
            },
            "WiFi Driver": {
                "description": "Test 802.11 packet handling",
                "corpus": "Malformed WiFi packets",
                "fuzzer": "Scapy + AFL",
                "priority": "HIGH"
            },
            "Intent Handlers": {
                "description": "Test intent parsing",
                "corpus": "Various intent formats",
                "fuzzer": "Drozer + custom scripts",
                "priority": "MEDIUM"
            }
        }
        
        for target, details in targets.items():
            print(f"🎯 {target} ({details['priority']} priority)")
            print(f"   Description: {details['description']}")
            print(f"   Corpus: {details['corpus']}")
            print(f"   Fuzzer: {details['fuzzer']}")
            
            # Create corpus directory
            target_dir = fuzzing_dir / target.lower().replace(" ", "_")
            target_dir.mkdir(exist_ok=True)
            print(f"   📂 Corpus dir: {target_dir}")
            print()
        
        # Create fuzzing scripts
        print("\n📝 Creating fuzzing scripts...\n")
        
        # Binder fuzzer example
        binder_fuzzer = self.workspace / "fuzz_binder.py"
        binder_fuzzer.write_text("""#!/usr/bin/env python3
\"\"\"
Binder IPC Fuzzer
Tests binder transactions for memory corruption bugs
\"\"\"

import subprocess
import random
import struct

def fuzz_binder():
    \"\"\"Generate and test random binder transactions\"\"\"
    
    for i in range(10000):
        # Generate random parcel data
        parcel_size = random.randint(0, 4096)
        parcel_data = bytes(random.randint(0, 255) for _ in range(parcel_size))
        
        # Try to send via service manager
        # (This is simplified - real fuzzer would use native code)
        print(f"Iteration {i}: Testing {parcel_size} byte parcel")
        
        # TODO: Send to actual binder service
        # Check for crashes in logcat
        
if __name__ == "__main__":
    print("Starting Binder IPC fuzzer...")
    fuzz_binder()
""")
        print(f"✅ Created: {binder_fuzzer}")
        
        # Media fuzzer example
        media_fuzzer = self.workspace / "fuzz_media.py"
        media_fuzzer.write_text("""#!/usr/bin/env python3
\"\"\"
Media Codec Fuzzer
Tests media file parsing for vulnerabilities
\"\"\"

import subprocess
import os
from pathlib import Path

def fuzz_media():
    \"\"\"Fuzz media codecs with malformed files\"\"\"
    
    corpus_dir = Path("zero_day_research/fuzzing_corpus/media_codecs")
    
    # Generate malformed media files
    for i in range(100):
        # Create malformed MP4
        mp4_file = corpus_dir / f"fuzz_{i}.mp4"
        
        # Generate random bytes with MP4 header
        with open(mp4_file, 'wb') as f:
            f.write(b'\\x00\\x00\\x00\\x20ftypisom')  # MP4 header
            f.write(os.urandom(1024))  # Random data
        
        # Try to play on device
        subprocess.run(f"adb push {mp4_file} /sdcard/", shell=True)
        subprocess.run("adb shell am start -a android.intent.action.VIEW -d file:///sdcard/fuzz_{i}.mp4", shell=True)
        
        # Check for crashes
        result = subprocess.run("adb logcat -d | grep -i 'fatal\\|crash'", shell=True, capture_output=True, text=True)
        if "FATAL" in result.stdout or "crash" in result.stdout:
            print(f"🔴 CRASH FOUND with {mp4_file}!")
            
if __name__ == "__main__":
    print("Starting Media Codec fuzzer...")
    fuzz_media()
""")
        print(f"✅ Created: {media_fuzzer}")
        
        print("\n💡 Fuzzing Tips:")
        print("   1. Start with known-good corpus (valid files)")
        print("   2. Mutate bytes randomly")
        print("   3. Monitor logcat for crashes")
        print("   4. Save crashers for analysis")
        print("   5. Reproduce bugs in controlled environment")
        
        print("\n📦 Install AFL (Advanced Fuzzer):")
        print("   # On Linux:")
        print("   sudo apt install afl++")
        print("   afl-fuzz -i corpus/ -o findings/ -- ./target @@")
    
    def generate_report(self):
        """Generate final research report"""
        self.print_header("Generating Research Report", "success")
        
        report_dir = self.workspace / "reports"
        report_path = report_dir / f"research_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.md"
        
        report = f"""# Zero-Day Research Report
## Huawei DUB-LX3 (Y7 2019)

**Date:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}  
**Researcher:** [Your Name]  
**Device:** Huawei DUB-LX3  
**Android:** 8.1.0 (EMUI 8.2.0)  
**Kernel:** 4.9.82  

---

## Research Summary

### Files Extracted:
- Firmware binaries: {len(self.extracted_files)}
- System apps: [Count from workspace]
- Decompiled sources: [Check decompiled folder]

### Analysis Completed:
- ✅ Static analysis of native libraries
- ✅ Source code vulnerability scanning
- ✅ Fuzzing setup prepared

### Workspace:
`{self.workspace}`

---

## Next Steps:

### 1. Reverse Engineering
- Open extracted libraries in Ghidra/IDA Pro
- Focus on:
  - Binder implementation (`libbinder.so`)
  - Media codecs (`libstagefright.so`)
  - Camera stack (`libcamera_client.so`)
  
### 2. Source Code Analysis
- Review decompiled apps for:
  - Intent vulnerabilities
  - SQL injection
  - Command injection
  - Hardcoded secrets
  
### 3. Fuzzing
- Run fuzzing scripts on:
  - Media parsers
  - Binder IPC
  - Network stacks
  
### 4. Dynamic Analysis
- Use Frida to:
  - Hook functions
  - Monitor API calls
  - Test exploit ideas
  
---

## Findings

[Document your findings here]

### Potential Vulnerabilities:
1. [Description]
2. [Description]
3. [Description]

### Confirmed Exploits:
1. [Description]
2. [Description]

---

## Recommendations

### For Security:
- Update device to latest firmware
- Apply custom ROM with latest security patches
- Monitor for unusual activity

### For Research:
- Continue fuzzing critical components
- Deep-dive into promising areas
- Coordinate disclosure with vendors

---

**Status:** In Progress  
**Risk Level:** [Assessment]  
**Next Review:** [Date]
"""
        
        report_path.write_text(report)
        print(f"📄 Report saved: {report_path}")
        print(f"\n✅ Research workspace ready at: {self.workspace}")
    
    def run(self):
        """Run complete research workflow"""
        print("""
╔════════════════════════════════════════════════════════════════╗
║          ZERO-DAY RESEARCH TOOLKIT                             ║
║          Advanced Vulnerability Discovery                      ║
╚════════════════════════════════════════════════════════════════╝

This toolkit will help you discover new (zero-day) vulnerabilities through:
  1. Reverse Engineering (binary analysis)
  2. Fuzzing (automated testing)
  3. Source Code Analysis (pattern matching)

⚠️  WARNING: This is for AUTHORIZED research only!
""")
        
        choice = input("Continue? (y/n): ").lower()
        if choice != 'y':
            print("Aborted.")
            return
        
        print("\n" + "="*70)
        print("  RESEARCH WORKFLOW")
        print("="*70 + "\n")
        
        print("Select research tasks:")
        print("  1. Setup environment")
        print("  2. Extract firmware")
        print("  3. Extract apps")
        print("  4. Decompile apps")
        print("  5. Analyze native libraries")
        print("  6. Source code analysis")
        print("  7. Setup fuzzing")
        print("  8. Generate report")
        print("  9. Run all")
        print("  0. Exit")
        
        choice = input("\nYour choice: ").strip()
        
        if choice == '1':
            self.setup_research_environment()
        elif choice == '2':
            self.extract_device_firmware()
        elif choice == '3':
            self.extract_system_apps()
        elif choice == '4':
            self.decompile_apps()
        elif choice == '5':
            self.analyze_native_libraries()
        elif choice == '6':
            self.source_code_analysis()
        elif choice == '7':
            self.setup_fuzzing()
        elif choice == '8':
            self.generate_report()
        elif choice == '9':
            print("\n🚀 Running complete workflow...\n")
            self.setup_research_environment()
            self.extract_device_firmware()
            self.extract_system_apps()
            self.decompile_apps()
            self.analyze_native_libraries()
            self.source_code_analysis()
            self.setup_fuzzing()
            self.generate_report()
        else:
            print("Exited.")

def main():
    toolkit = ZeroDayResearchToolkit()
    toolkit.run()

if __name__ == "__main__":
    main()
