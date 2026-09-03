#!/usr/bin/env python3
"""Authorize S1's adb key on S2 by triggering + accepting the RSA dialog."""

import re
import time

import paramiko

# Step 1: trigger connection attempt from S1 (pops dialog on S2)
s1 = paramiko.SSHClient()
s1.set_missing_host_key_policy(paramiko.AutoAddPolicy())
s1.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)
s1.exec_command("adb connect 192.168.1.140:5555", timeout=15)[1].read()
print("[1] Connection attempt made from S1 - dialog should be on S2 screen")
s1.close()

time.sleep(4)

# Step 2: find and press OK on the dialog via PC's authorized adb
import subprocess


def adb(cmd):
    r = subprocess.run(
        ["adb", "-s", "192.168.1.140:5555", "shell", cmd],
        capture_output=True, text=True, timeout=30,
    )
    return (r.stdout or "") + (r.stderr or "")


xml = adb("uiautomator dump /dev/tty 2>&1")
print("[2] UI dump snippet:")
m = re.search(r'<node[^>]*text="[^"]*(?:OK|Allow|always)[^"]*"[^>]*bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"', xml, re.I)
if not m:
    # try any button node
    m = re.search(r'<node[^>]*resource-id="android:id/button1"[^>]*bounds="\[(\d+),(\d+)\]\[(\d+),(\d+)\]"', xml)

if m:
    x = (int(m.group(1)) + int(m.group(3))) // 2
    y = (int(m.group(2)) + int(m.group(4))) // 2
    print("   Found button at (%d,%d) - tapping" % (x, y))
    print(adb("input tap %d %d" % (x, y)))
else:
    print("   No button found in dump; raw:")
    print(xml[:800])

time.sleep(3)

# Step 3: verify from S1
s1 = paramiko.SSHClient()
s1.set_missing_host_key_policy(paramiko.AutoAddPolicy())
s1.connect("192.168.1.222", 8022, "u0_a175", "A2345678", timeout=20)
_, o, _ = s1.exec_command("adb connect 192.168.1.140:5555; adb devices", timeout=20)
print("[3] From S1 after auth:")
print(o.read().decode(errors="ignore").strip())
s1.close()