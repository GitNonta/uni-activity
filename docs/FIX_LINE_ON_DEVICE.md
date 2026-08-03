# 📱 FIX LINE OA - On Android Device

## Problem
```
❌ LINE OA Offline
❌ Error: No route to host / Network is unreachable
❌ Root Cause: Missing default gateway in routing table
```

## ⚠️ THIS REQUIRES PHYSICAL ACCESS TO THE ANDROID DEVICE

The server cannot reach LINE API because there's no default gateway configured. This CANNOT be fixed remotely without root access.

---

## Solution: Fix on Android Device

### Method 1: Restart WiFi Connection (Easiest)

1. **Open Android WiFi Settings**
2. **Disconnect from current WiFi**
3. **Reconnect to WiFi**
4. Make sure "Obtain IP address automatically (DHCP)" is enabled
5. Check if it assigns a gateway

---

### Method 2: Fix in Termux App (If Rooted)

1. **Open Termux app** on the Android device
2. Type these commands:

```bash
# Check if device is rooted
su

# If su works, add the gateway:
ip route add default via 192.168.1.1 dev wlan0

# Verify:
ip route show

# You should see:
# default via 192.168.1.1 dev wlan0
```

---

### Method 3: Forget and Re-add WiFi Network

1. **Go to Android Settings** → **WiFi**
2. **Tap and hold** on your connected WiFi
3. Select **"Forget network"**
4. **Reconnect** to the WiFi:
   - Enter password
   - Make sure **"Advanced options"** → **"IP settings"** is set to **"DHCP"**
5. Connect and wait for IP assignment

---

### Method 4: Check Router DHCP Settings

Sometimes the router isn't assigning gateway properly:

1. **Access router admin** (usually http://192.168.1.1)
2. Check **DHCP settings**
3. Make sure **"DHCP Server" is enabled**
4. Check if there's a gateway assigned
5. Restart router if needed

---

## Verification After Fix

After applying one of the methods above:

### On Android Device (in Termux):

```bash
# Check routing table
ip route show
# Should show: default via 192.168.1.1 dev wlan0

# Test external connectivity
ping -c 2 8.8.8.8
# Should work

# Test HTTPS
curl -I https://www.google.com
# Should return HTTP response

# Test LINE API
curl -I https://api.line.me
# Should return HTTP response (not timeout)
```

---

## Why This Happened

Possible causes:
1. **Device was rebooted** - routing table was cleared
2. **WiFi reconnected** - DHCP didn't assign gateway
3. **Manual network config** - gateway was never set
4. **Android power saving** - network was reset
5. **Termux-specific** - routing table not properly maintained

---

## Permanent Fix

### Option 1: Use Termux Boot (If Rooted)

Create a script that runs on boot to add gateway:

```bash
# Install termux-boot
pkg install termux-boot

# Create boot directory
mkdir -p ~/.termux/boot

# Create gateway script
cat > ~/.termux/boot/01-gateway.sh << 'EOF'
#!/data/data/com.termux/files/usr/bin/bash
sleep 10
su -c "ip route add default via 192.168.1.1 dev wlan0"
EOF

# Make executable
chmod +x ~/.termux/boot/01-gateway.sh
```

### Option 2: Keep Device WiFi Always On

In Android Settings:
1. **Developer Options** → **Stay awake** (ON)
2. **WiFi** → **Advanced** → **Keep Wi-Fi on during sleep** (Always)

---

## Alternative Workaround (No Root Needed)

If you CANNOT fix the gateway, use Cloudflare Tunnel:

### On Android Device (in Termux):

```bash
# Start cloudflare tunnel
cloudflared tunnel --url http://localhost:8080

# This will give you a public URL like:
# https://random-name.trycloudflare.com
```

### Update Laravel .env:

```env
# Change these URLs to the tunnel URL:
LINE_CALLBACK_URL=https://random-name.trycloudflare.com/line/callback
APP_URL=https://random-name.trycloudflare.com
```

**Note:** This allows LINE to reach your webhook, BUT you still WON'T be able to send LINE push notifications (outbound connections).

---

## What Works vs What Doesn't

### ✅ Currently Working:
- Local network communication
- Can ping local devices (192.168.1.x)
- Can ping external IPs (8.8.8.8) - but...
- DNS resolution works

### ❌ NOT Working:
- **Cannot reach external web servers (HTTP/HTTPS)**
- Cannot connect to LINE API
- Cannot send LINE push notifications
- Cannot make outbound HTTPS requests

### Why?
Even though ICMP (ping) to 8.8.8.8 works, **TCP connections require a proper default route**. The routing table shows:

```bash
192.168.1.0/24 dev wlan0 proto kernel scope link src 192.168.1.222
# ← MISSING: default via 192.168.1.1 dev wlan0
```

---

## Quick Diagnostic Script

Run this in Termux on the device:

```bash
#!/bin/bash
echo "=== IP Address ==="
ip addr show wlan0 | grep 'inet '

echo -e "\n=== Routing Table ==="
ip route show

echo -e "\n=== Gateway Ping ==="
ping -c 2 192.168.1.1

echo -e "\n=== External IP Ping ==="
ping -c 2 8.8.8.8

echo -e "\n=== DNS Test ==="
nslookup api.line.me

echo -e "\n=== HTTPS Test ==="
curl -I https://api.line.me --connect-timeout 5
```

---

## Summary

| Check | Status | Notes |
|-------|--------|-------|
| Network Interface | ✅ UP | 192.168.1.222 |
| Local Network | ✅ OK | Can reach 192.168.1.1 |
| ICMP to Internet | ✅ OK | Can ping 8.8.8.8 |
| **Default Gateway** | ❌ **MISSING** | **MAIN ISSUE** |
| TCP to Internet | ❌ FAIL | No route to host |
| HTTPS | ❌ FAIL | Cannot connect |
| LINE API | ❌ FAIL | Unreachable |

**Action Required:**  
**Fix WiFi connection or add default gateway manually on the Android device**

---

## Need Help?

If you've tried all methods and it still doesn't work:

1. Check if other devices on the same WiFi can access internet
2. Try a different WiFi network
3. Check if VPN or firewall apps are blocking connections
4. Restart the Android device completely
5. Check router logs for any blocked devices

---

**Last Updated:** 2026-07-18  
**Server:** 192.168.1.222 (Termux/Android)  
**Issue:** Missing default gateway - requires device-side fix
