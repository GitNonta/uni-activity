#!/usr/bin/env python3
"""Authorize S1's adb key on S2 - v2 with proper UI dump."""

import re
import subprocess
import time

import paramiko


def adb(cmd):
    r = subprocess.run(
        ["adb", "-s", "192.168.1.140:5555", "shell", cmd],
        capture_output=True, text=True, timeout=40,
    )
    return (r.stdout or "") + (r.stderr or "")


# Wake screen + dismiss keyguard so the dialog can appear
print("[0] Wake + unlock S2")
print(adb("input keyevent KEYCODE_WAKEUP").strip())
time.sleep(1)
print(adb("wm dismiss-keyguard").strip())
time.sleep(2)

# Trigger connection attempt from S1 (pops RSA dialog)
s1 = paramiko.SSHClient()
s1.set_missing_host_key_policy(paramiko.AutoAddPolicy())
s1.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)
s1.exec_command("adb disconnect 192.168.1.140:5555; adb connect 192.168.1.140:5555", timeout=15)[1].read()
print("[1] Connection attempt made from S1")
s1.close()
time.sleep(5)

# Dump UI to file and read it
xml = adb("uiautomator dump /sdcard/ui.xml >/dev/null 2>&1; cat /sdcard/ui.xml")
print("[2] Looking for OK/Allow button...")
m = re.search(
    r'<node[^>]*text="(?:OK|Allow|Always allow[^"]*)"[^>]*bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"',
    xml, re.I,
)
if not m:
    m = re.search(r'resource-id="android:id/button1"[^>]*bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"', xml)

if m:
    x = (int(m.group(1)) + int(m.group(3))) // 2
    y = (int(m.group(2)) + int(m.group(4))) // 2
    print("   Found button at (%d,%d) - tapping" % (x, y))
    print(adb("input tap %d %d" % (x, y)).strip())
else:
    print("   No button found. Window focus:")
    print(adb("dumpsys window windows | grep mCurrentFocus"))
    print("   XML head:")
    print(xml[:600])

time.sleep(3)

# Verify from S1
s1 = paramiko.SSHClient()
s1.set_missing_host_key_policy(paramiko.AutoAddPolicy())
s1.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)
_, o, _ = s1.exec_command("adb devices", timeout=20)
print("[3] From S1 after auth:")
print(o.read().decode(errors="ignore").strip())
s1.close()