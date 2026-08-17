# Zero-Day Research Guide
## Complete Methodology for Discovering New Vulnerabilities

---

## 📊 Research Overview

You now have access to critical system files from Huawei DUB-LX3:

### ✅ Extracted Files:
1. **`libbinder.so`** (442 KB) - **HIGHEST PRIORITY**
   - Binder IPC implementation
   - Known for use-after-free bugs
   - Direct path to privilege escalation

2. **`libstagefright.so`** (1.7 MB) - **HIGH PRIORITY**
   - Media codec framework
   - History of critical vulnerabilities
   - Remote exploitation possible

3. **`libmediaplayerservice.so`** (704 KB) - **HIGH PRIORITY**
   - Media player service
   - Handles external media files
   - Attack surface for malicious files

4. **`libcamera_client.so`** (244 KB) - **MEDIUM PRIORITY**
   - Camera service interface
   - May have permission bypasses

5. **`app_process32/64`** (29/23 KB) - **MEDIUM PRIORITY**
   - Zygote process
   - Core Android runtime

---

## 🔬 Methodology

### Phase 1: Static Analysis (Reverse Engineering)

#### Tool: Ghidra (Recommended)

**Download:** https://ghidra-sre.org/

**Steps:**

1. **Launch Ghidra**
   ```bash
   # Windows
   ghidraRun.bat
   
   # Linux/Mac
   ./ghidraRun
   ```

2. **Create New Project**
   - File → New Project
   - Name: "Huawei_DUB-LX3_Research"

3. **Import Binary**
   - File → Import File
   - Select: `d:\projects\uni-activity\zero_day_research\firmware\system_lib_libbinder.so`
   - Language: ARM (or auto-detect)
   - Click OK

4. **Auto-Analyze**
   - Analysis → Auto Analyze
   - Enable all analyzers
   - Wait for completion (5-30 minutes)

5. **What to Look For:**

   **🔴 Buffer Overflows:**
   ```c
   // Search for these patterns:
   strcpy(dest, src);          // Unsafe
   strcat(dest, src);          // Unsafe
   sprintf(buf, "%s", src);    // Unsafe
   gets(buf);                  // Very unsafe
   ```
   
   **How to find:** Search → For Functions → "strcpy"

   **🔴 Use-After-Free:**
   ```c
   // Look for this pattern:
   free(ptr);
   // ... some code ...
   ptr->field = value;  // UAF!
   ```
   
   **How to find:** Look at decompiled code around `free()` calls

   **🔴 Integer Overflows:**
   ```c
   // Example:
   size_t size = user_input * sizeof(struct);
   void* buf = malloc(size);  // If overflow, small allocation!
   memcpy(buf, data, actual_size);  // Heap overflow!
   ```
   
   **How to find:** Look at arithmetic operations before `malloc()`

   **🔴 Format String Bugs:**
   ```c
   // Vulnerable:
   printf(user_string);  // Should be printf("%s", user_string)
   syslog(user_string);
   ```
   
   **How to find:** Search for `printf`, `sprintf` with single argument

   **🔴 Race Conditions:**
   ```c
   // TOCTOU (Time-of-Check Time-of-Use):
   if (access(file, R_OK) == 0) {  // Check
       fd = open(file, O_RDONLY);   // Use - file could change!
   }
   ```

#### Tool: radare2 (CLI-based)

```bash
# Install
sudo apt install radare2  # Linux
brew install radare2      # Mac

# Analyze binary
r2 d:\projects\uni-activity\zero_day_research\firmware\system_lib_libbinder.so

# Commands:
aaa                    # Analyze all
afl                    # List functions
pdf @ function_name    # Disassemble function
/R strcpy             # Find strcpy references
izz                    # List strings
pdf @ sym.vulnerable   # Disassemble specific function
VV                     # Visual graph mode
```

#### Tool: IDA Pro (Industry Standard)

If you have IDA Pro:
1. File → Open → Select binary
2. Let IDA auto-analyze
3. View → Open subviews → Functions
4. Look for suspicious functions
5. F5 to decompile (if you have Hex-Rays)

---

### Phase 2: Dynamic Analysis (Runtime Testing)

#### Tool: Frida

**Install:**
```bash
pip install frida-tools
pip install frida
```

**Download Frida Server for Android:**
```bash
# Get version matching your frida-tools
wget https://github.com/frida/frida/releases/download/16.x.x/frida-server-16.x.x-android-arm64.xz
unxz frida-server-*.xz
adb push frida-server /data/local/tmp/
adb shell "chmod 755 /data/local/tmp/frida-server"
```

**Run Frida Server:**
```bash
adb shell "/data/local/tmp/frida-server &"
```

**Hook Functions:**

Example: Monitor `strcpy` calls in Binder:
```python
# hook_binder.py
import frida
import sys

device = frida.get_usb_device()
pid = device.spawn(["com.android.systemui"])  # Or target process
session = device.attach(pid)

script = session.create_script("""
Interceptor.attach(Module.findExportByName("libbinder.so", "strcpy"), {
    onEnter: function(args) {
        console.log("[strcpy] Called");
        console.log("  dest: " + args[0]);
        console.log("  src: " + Memory.readUtf8String(args[1]));
    },
    onLeave: function(retval) {
        console.log("[strcpy] Returned");
    }
});
""")

script.on('message', lambda msg, data: print(msg))
script.load()
device.resume(pid)
sys.stdin.read()
```

Run:
```bash
python hook_binder.py
```

**Advanced Frida: Find Hidden Functions:**
```javascript
// List all exports in libbinder.so
var exports = Module.enumerateExportsSync("libbinder.so");
exports.forEach(function(exp) {
    console.log(exp.name + " @ " + exp.address);
});

// Hook all functions (dangerous!)
exports.forEach(function(exp) {
    if (exp.type === 'function') {
        Interceptor.attach(exp.address, {
            onEnter: function(args) {
                console.log("[+] " + exp.name);
            }
        });
    }
});
```

---

### Phase 3: Fuzzing (Automated Bug Discovery)

#### AFL (American Fuzzy Lop)

**Setup:**
```bash
# Install AFL
sudo apt install afl++

# For Android, cross-compile target with AFL instrumentation
# This is advanced - requires NDK and AFL Android port
```

#### Custom Python Fuzzer (Easier)

**Example: Fuzz Media Player**

```python
# fuzz_media.py
import subprocess
import os
import random

def generate_malformed_mp4():
    """Generate malformed MP4 file"""
    header = b'\x00\x00\x00\x20ftypiso5'  # MP4 header
    data = os.urandom(random.randint(100, 10000))
    
    return header + data

def test_file(filename):
    """Test file on device"""
    # Push to device
    subprocess.run(f"adb push {filename} /sdcard/fuzz.mp4", shell=True)
    
    # Try to play
    result = subprocess.run(
        "adb shell am start -a android.intent.action.VIEW -d file:///sdcard/fuzz.mp4",
        shell=True,
        capture_output=True
    )
    
    # Check for crashes in logcat
    logcat = subprocess.run(
        "adb logcat -d -s DEBUG:* AndroidRuntime:* *:F",
        shell=True,
        capture_output=True,
        text=True
    )
    
    if "FATAL" in logcat.stdout or "SIGSEGV" in logcat.stdout:
        return True  # Crash found!
    
    return False

# Main fuzzing loop
crashes = []
for i in range(1000):
    print(f"[{i}] Fuzzing...")
    
    # Generate test file
    filename = f"fuzz_{i}.mp4"
    data = generate_malformed_mp4()
    
    with open(filename, 'wb') as f:
        f.write(data)
    
    # Test
    if test_file(filename):
        print(f"🔴 CRASH FOUND: {filename}")
        crashes.append(filename)
        # Save crash file
        os.rename(filename, f"crash_{len(crashes)}.mp4")
    else:
        os.remove(filename)

print(f"\nFound {len(crashes)} crashes!")
```

**Run:**
```bash
python fuzz_media.py
```

#### Fuzzing Binder IPC

```python
# fuzz_binder.py
import subprocess
import struct
import random

def fuzz_binder_transaction():
    """Send malformed binder transaction"""
    
    # Generate random transaction data
    code = random.randint(0, 0xFFFFFFFF)
    flags = random.randint(0, 0xFFFFFFFF)
    data_size = random.randint(0, 4096)
    data = bytes(random.randint(0, 255) for _ in range(data_size))
    
    # This requires native code or service manager access
    # Simplified example - real implementation needs JNI/NDK
    
    print(f"Testing transaction: code={code:08x}, size={data_size}")
    
    # TODO: Send via service manager
    # Check for crashes
    
for i in range(10000):
    fuzz_binder_transaction()
```

---

### Phase 4: Source Code Analysis

#### Decompile APKs

**Tool: jadx**

Download: https://github.com/skylot/jadx/releases

```bash
# Decompile APK
jadx -d output_dir com.huawei.systemmanager.apk

# Open in GUI
jadx-gui com.huawei.systemmanager.apk
```

#### Vulnerability Patterns to Search

**1. SQL Injection:**
```java
// Vulnerable:
db.rawQuery("SELECT * FROM users WHERE id=" + userInput);

// Safe:
db.rawQuery("SELECT * FROM users WHERE id=?", new String[]{userInput});
```

**2. Path Traversal:**
```java
// Vulnerable:
String filename = request.getParameter("file");
FileInputStream fis = new FileInputStream("/data/files/" + filename);
// Attacker can use: ../../system/etc/passwd
```

**3. Intent Injection:**
```java
// Vulnerable exported component:
<activity android:name=".VulnActivity" android:exported="true"/>

// Attacker can call:
Intent intent = new Intent();
intent.setComponent(new ComponentName("com.huawei.app", ".VulnActivity"));
intent.putExtra("cmd", "rm -rf /");
startActivity(intent);
```

**4. Hardcoded Secrets:**
```java
// Bad:
String apiKey = "sk_live_51HabcdefG123456789";
String password = "admin123";
```

**Search in decompiled code:**
```bash
# Find SQL operations
grep -r "rawQuery" output_dir/
grep -r "execSQL" output_dir/

# Find Intent operations
grep -r "startActivity" output_dir/
grep -r "sendBroadcast" output_dir/

# Find crypto issues
grep -r "DES" output_dir/
grep -r "MD5" output_dir/

# Find hardcoded strings
grep -r "password\s*=" output_dir/
grep -r "api_key" output_dir/
```

---

## 🎯 Priority Targets for Zero-Day Research

### 🥇 #1: libbinder.so (Binder IPC)

**Why:** History of critical vulnerabilities (CVE-2019-2215, CVE-2020-0041)

**What to Look For:**
- Use-after-free in transaction handling
- Buffer overflows in parcel parsing
- Integer overflows in size calculations
- Race conditions in reference counting

**Key Functions to Analyze:**
```
IPCThreadState::transact()
Parcel::write*()
Parcel::read*()
BBinder::transact()
```

**Ghidra Workflow:**
1. Open `libbinder.so` in Ghidra
2. Search → For Functions → "transact"
3. Double-click `IPCThreadState::transact`
4. Press F5 to decompile
5. Look for:
   - Unchecked pointer dereferences
   - Size calculations that could overflow
   - `free()` followed by pointer usage

**Known Vulnerability Pattern (CVE-2019-2215):**
```c
// Simplified vulnerability:
struct binder_node *node = ...;
free(node);  // Node freed

// ... some code ...

if (node->refcount > 0) {  // Use after free!
    // ...
}
```

### 🥈 #2: libstagefright.so (Media Framework)

**Why:** Remote exploitation via malicious media files

**What to Look For:**
- Buffer overflows in codec parsers
- Integer overflows in frame size calculations
- Heap corruption in decoder state machines

**Test Cases:**
```python
# Malformed MP4 with invalid atom sizes
# Oversized JPEG with crafted EXIF data
# MP3 with corrupted ID3 tags
```

**Fuzzing Strategy:**
1. Collect valid media files (corpus)
2. Mutate bytes randomly
3. Push to device and play
4. Monitor for crashes

### 🥉 #3: Camera Stack

**Qualcomm-Specific Vulnerability:**

CVE-2019-2101 affects camera drivers on Qualcomm chipsets.

**Research:**
- Look for buffer overflows in image processing
- Check metadata parsing
- Test with malformed camera parameters

---

## 📝 Documentation

### When You Find a Bug:

1. **Reproduce Reliably**
   - Document exact steps
   - Create minimal test case
   - Verify on clean device

2. **Analyze Root Cause**
   - Use debugger (gdb via adb)
   - Examine crash dumps
   - Identify vulnerable code

3. **Develop Exploit**
   - Prove exploitability
   - Show impact (RCE, privilege escalation, etc.)
   - Create PoC (Proof of Concept)

4. **Write Report**
   ```markdown
   # Vulnerability Report
   
   ## Summary
   Brief description
   
   ## Details
   - Affected component: [name]
   - Vulnerability type: [UAF, BO, etc.]
   - Impact: [RCE, LPE, DoS]
   
   ## Reproduction
   Steps to reproduce
   
   ## Root Cause
   Technical analysis
   
   ## Proof of Concept
   Exploit code
   
   ## Remediation
   Suggested fix
   ```

5. **Responsible Disclosure**
   - Contact vendor (Huawei PSIRT: psirt@huawei.com)
   - Allow 90 days for patch
   - Coordinate public disclosure
   - Request CVE number

---

## 🛠️ Tools Reference

### Essential Tools:
| Tool | Purpose | Download |
|------|---------|----------|
| **Ghidra** | Reverse engineering | https://ghidra-sre.org/ |
| **IDA Pro** | Disassembler (paid) | https://hex-rays.com/ |
| **radare2** | CLI RE tool | https://rada.re/ |
| **Frida** | Dynamic instrumentation | https://frida.re/ |
| **jadx** | APK decompiler | https://github.com/skylot/jadx |
| **apktool** | APK analysis | https://ibotpeaches.github.io/Apktool/ |
| **AFL** | Fuzzer | https://github.com/google/AFL |
| **Drozer** | Android security | https://github.com/WithSecureLabs/drozer |

### Analysis Checklist:

- [ ] Extract system binaries
- [ ] Extract system apps
- [ ] Decompile APKs
- [ ] Static analysis with Ghidra
- [ ] Dynamic analysis with Frida
- [ ] Fuzz critical components
- [ ] Source code pattern matching
- [ ] Exploit development
- [ ] Report writing

---

## ⚖️ Legal & Ethical Guidelines

### ✅ DO:
- Test on devices you own
- Follow responsible disclosure
- Respect 90-day disclosure timeline
- Coordinate with vendors
- Help improve security

### ❌ DON'T:
- Attack devices you don't own
- Exploit in the wild
- Sell exploits to malicious actors
- Disclose before vendor can patch
- Harm users

### Consequences:
Unauthorized access carries severe penalties including imprisonment, fines, and criminal record.

---

## 📚 Learning Resources

### Books:
- "The Android Hacker's Handbook" by Joshua J. Drake
- "Fuzzing: Brute Force Vulnerability Discovery" by Michael Sutton
- "Practical Reverse Engineering" by Bruce Dang

### Courses:
- Offensive Security's Advanced Web Attacks and Exploitation (AWAE)
- SANS SEC760: Advanced Exploit Development for Penetration Testers
- Pwn.college (free) - https://pwn.college/

### Communities:
- XDA Developers - https://forum.xda-developers.com/
- Project Zero Blog - https://googleprojectzero.blogspot.com/
- r/ReverseEngineering - https://reddit.com/r/ReverseEngineering/

---

## 🎯 Next Steps

1. **Start with libbinder.so**
   - Open in Ghidra
   - Analyze `transact()` function
   - Look for UAF patterns

2. **Set up Frida**
   - Install on device
   - Hook key functions
   - Monitor runtime behavior

3. **Begin fuzzing**
   - Start with media files
   - Monitor for crashes
   - Analyze crash dumps

4. **Document everything**
   - Keep research notes
   - Save PoCs
   - Write detailed reports

**Good luck with your research! Remember: Security research is about making systems safer for everyone.** 🔒
