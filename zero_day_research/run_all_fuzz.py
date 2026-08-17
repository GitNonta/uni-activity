#!/usr/bin/env python3
"""
Auto-run all fuzzing tests without interaction
"""

import subprocess
import sys

def run_cmd(cmd):
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

print("""
╔════════════════════════════════════════════════════════════════╗
║               AUTO FUZZING ALL TESTS                           ║
║           Running without user interaction                     ║
╚════════════════════════════════════════════════════════════════╝
""")

# Import the fuzzer
sys.path.insert(0, 'd:\\projects\\uni-activity\\zero_day_research')
from fuzz_binder_services import BinderFuzzer

fuzzer = BinderFuzzer()

# Check device connection
print("\n[1/7] Checking device connection...")
if not fuzzer.list_services():
    print("❌ Device not connected")
    sys.exit(1)

print("✅ Device connected\n")

# Run all tests automatically
print("[2/7] Fuzzing high-risk services...")
high_risk = ['media', 'camera', 'audio']
for srv in fuzzer.services:
    for keyword in high_risk:
        if keyword in srv.lower():
            print(f"\n   Fuzzing: {srv}")
            fuzzer.fuzz_service(srv, max_transactions=20)
            break

print("\n[3/7] Fuzzing media player...")
fuzzer.fuzz_media_player()

print("\n[4/7] Fuzzing camera service...")
fuzzer.fuzz_camera_service()

print("\n[5/7] Testing system properties...")
fuzzer.fuzz_system_properties()

print("\n[6/7] Checking kernel log...")
fuzzer.check_dmesg_for_crashes()

print("\n[7/7] Scanning /proc...")
fuzzer.scan_procfs()

# Generate report
fuzzer.generate_report()

print("""
╔════════════════════════════════════════════════════════════════╗
║                  ALL TESTS COMPLETE                            ║
╚════════════════════════════════════════════════════════════════╝
""")
