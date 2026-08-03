# 🔴 LINE OA Network Issue - UNI ACTIVITY

## Problem Identified

```
❌ Network Error: [Errno 101] Network is unreachable
❌ Root Cause: No default gateway configured on server
```

## Diagnosis Results

✅ **Working:**
- Internet (ICMP): Can ping external IPs
- DNS Resolution: Working correctly
- Network Interface: wlan0 (192.168.1.222/24)
- SSL Certificates: Valid and up-to-date
- LINE Configuration: Credentials present in .env

❌ **Not Working:**
- **Default Gateway: MISSING** ← This is the problem!
- Cannot connect to LINE API (timeout)
- Cannot reach any HTTPS endpoints

## The Issue

The server has no default gateway, which means:
- ✅ Can communicate within local network (192.168.1.x)
- ❌ Cannot reach Internet (LINE API, external services)

Current routing table:
```bash
192.168.1.0/24 dev wlan0 proto kernel scope link src 192.168.1.222
# ← Missing: default via 192.168.1.1 dev wlan0
```

---

## Solution 1: Add Default Gateway (REQUIRES ROOT ACCESS)

### Option A: Via Termux (SSH)

```bash
# SSH into server
ssh u0_a175@192.168.1.222 -p 8022
# Password: 2345678A

# Become root
su

# Add default gateway
ip route add default via 192.168.1.1 dev wlan0

# Verify
ip route show
# Should now show: default via 192.168.1.1 dev wlan0

# Test connectivity
curl -I https://api.line.me
```

### Option B: Via Android Device Directly

1. Open **Termux app** on the Android device
2. Run:
```bash
su
ip route add default via 192.168.1.1 dev wlan0
```

### Option C: If Gateway is Different

Find the correct gateway:
```bash
# Check DHCP info
ip route | grep wlan0

# Or check router settings
# Common gateways: 192.168.1.1, 192.168.0.1, 10.0.0.1
```

Then add:
```bash
su
ip route add default via <GATEWAY_IP> dev wlan0
```

---

## Solution 2: Restart Network (May Auto-Configure)

```bash
# SSH into server
ssh u0_a175@192.168.1.222 -p 8022

# Restart WiFi (as root)
su
ip link set wlan0 down
sleep 2
ip link set wlan0 up

# DHCP should auto-configure gateway
dhcpcd wlan0
```

---

## Solution 3: Use Termux Boot Script (Permanent Fix)

Create a boot script to auto-add gateway on startup:

```bash
# SSH into server
ssh u0_a175@192.168.1.222 -p 8022

# Install termux-boot
pkg install termux-boot

# Create boot script directory
mkdir -p ~/.termux/boot

# Create gateway script
cat > ~/.termux/boot/01-gateway.sh << 'EOF'
#!/data/data/com.termux/files/usr/bin/bash
# Wait for network
sleep 10
# Add default gateway (requires root)
su -c "ip route add default via 192.168.1.1 dev wlan0"
EOF

# Make executable
chmod +x ~/.termux/boot/01-gateway.sh
```

---

## Solution 4: Alternative - Use ngrok/Cloudflare Tunnel

If you cannot fix the gateway, use a reverse proxy:

### Using Cloudflare Tunnel (cloudflared)

```bash
# Install cloudflared
pkg install cloudflared

# Start tunnel
cloudflared tunnel --url http://localhost:8080
# This will give you a public URL

# Update .env with the new URL
LINE_CALLBACK_URL=https://YOUR-TUNNEL-URL.trycloudflare.com/line/callback
```

**Note:** This works WITHOUT needing outbound connectivity from server,
but LINE still needs to reach your webhook.

---

## Verification Steps

After applying fix, test:

### 1. Check Gateway

```bash
ip route show
# Should show: default via 192.168.1.1 dev wlan0
```

### 2. Test External Connectivity

```bash
# Test DNS
nslookup api.line.me

# Test HTTPS
curl -I https://api.line.me/v2/bot/info

# Expected: HTTP/1.1 401 (Unauthorized) ← This is good!
# Means we can reach LINE, just need valid token
```

### 3. Test from PHP

```bash
cd /data/data/com.termux/files/home/uni-activity

php artisan tinker
```

Then run:
```php
use Illuminate\Support\Facades\Http;

$response = Http::timeout(10)->get('https://api.line.me/v2/bot/info');
echo $response->status();  // Should show: 401 or similar, not timeout
```

### 4. Test LINE Notification

```bash
cd /data/data/com.termux/files/home/uni-activity

php artisan tinker
```

Then run:
```php
$lineService = app(\App\Services\LineService::class);
$lineService->pushMessage('YOUR_LINE_USER_ID', [[
    'type' => 'text',
    'text' => 'Test from server'
]]);
```

---

## Why This Happened

Possible causes:
1. **Manual network configuration** - Gateway not set in config
2. **DHCP not running** - Should auto-assign gateway
3. **Network restart** - Gateway lost after network restart
4. **Termux-specific** - Android may have cleared routing table

---

## Quick Diagnostic Command

Run this to check full network status:

```bash
echo "=== Network Interfaces ==="
ip addr show

echo -e "\n=== Routing Table ==="
ip route show

echo -e "\n=== Gateway Test ==="
ping -c 2 192.168.1.1

echo -e "\n=== External Connectivity ==="
ping -c 2 8.8.8.8

echo -e "\n=== LINE API Test ==="
curl -I --connect-timeout 5 https://api.line.me
```

---

## Emergency Workaround (If Cannot Fix Gateway)

If you cannot add gateway, use **Termux API Proxy**:

1. Install termux-api on another device with internet
2. Create a proxy service
3. Route LINE API calls through the proxy

Or:

**Use webhooks via Cloudflare Tunnel** and **disable push notifications** temporarily:

```php
// In .env
LINE_PUSH_ENABLED=false

// In LineService.php, modify pushMessage():
public function pushMessage(string $lineUserId, array $messages): bool
{
    if (!config('services.line.push_enabled', true)) {
        Log::info('LINE push disabled', ['to' => $lineUserId]);
        return false;
    }
    // ... rest of code
}
```

---

## Contact Administrator

**This issue requires system-level access (root/su) to fix properly.**

If you don't have root access:
1. Contact device administrator
2. Request: "Add default gateway: 192.168.1.1"
3. Or: Enable DHCP to auto-configure network

---

## Summary

| Component | Status | Notes |
|-----------|--------|-------|
| Network Interface | ✅ UP | wlan0: 192.168.1.222 |
| Internet (ICMP) | ✅ Working | Can ping external IPs |
| DNS Resolution | ✅ Working | Can resolve domains |
| **Default Gateway** | ❌ **MISSING** | **Need to add manually** |
| LINE API Reachability | ❌ Unreachable | Due to missing gateway |
| SSL Certificates | ✅ Valid | No issues |
| LINE Credentials | ✅ Configured | In .env file |

**Action Required:** Add default gateway with root access

**Command:** `su -c "ip route add default via 192.168.1.1 dev wlan0"`

---

**Last Updated:** 2026-07-18  
**Diagnostic Run:** Successful  
**Server:** 192.168.1.222:8022 (Termux/Android)
