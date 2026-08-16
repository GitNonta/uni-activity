# 🌐 Network & Routing Setup Guide

**Production Server:** 192.168.1.222 (Termux)  
**Development Computer:** 192.168.1.45 (Windows)  
**Goal:** Make server accessible from internet/network

---

## 📋 Network Architecture Options

### Option 1: Local Network Only (LAN) 🏠
**Use Case:** Office/Home network only  
**Security:** High  
**Complexity:** Low

```
Internet
   ↓
Router (192.168.1.1)
   ↓
├── 192.168.1.45 (Your PC)
└── 192.168.1.222 (Production Server) ← Accessible only from LAN
```

### Option 2: Port Forwarding (Router) 🌍
**Use Case:** External access via router  
**Security:** Medium  
**Complexity:** Medium

```
Internet (Public IP: e.g., 203.x.x.x)
   ↓
Router (192.168.1.1) ← Port forwarding rules
   ↓
192.168.1.222 (Production Server)
   - Port 80 → 8000 (HTTP)
   - Port 443 → 8443 (HTTPS)
   - Port 5432 (PostgreSQL) - Optional
```

### Option 3: Ngrok Tunnel 🚇
**Use Case:** Quick testing, demos, webhooks  
**Security:** Medium  
**Complexity:** Low

```
Internet
   ↓
Ngrok Cloud (https://xxx.ngrok-free.app)
   ↓
192.168.1.222 (Production Server)
```

### Option 4: Reverse Proxy (Recommended for Production) 🔒
**Use Case:** Production deployment  
**Security:** High  
**Complexity:** High

```
Internet
   ↓
Nginx/Apache (192.168.1.45 or separate server)
   ↓
192.168.1.222 (Backend Server)
```

---

## 🚀 Setup Instructions

### ✅ Option 1: Local Network Access (Current Setup)

**Status:** ✅ Already configured!

Your current setup:
- Server 192.168.1.222 listens on `0.0.0.0` (all interfaces)
- Accessible from any device on 192.168.1.0/24 network
- No additional routing needed

**Test:**
```bash
# From any device on same network
curl http://192.168.1.222:8000
psql -h 192.168.1.222 -U admin -d uni_activity
```

---

### 🌍 Option 2: Port Forwarding (Internet Access)

#### Step 1: Find Router IP
```powershell
# On your computer (192.168.1.45)
ipconfig | Select-String "Default Gateway"
# Should show: 192.168.1.1
```

#### Step 2: Access Router Admin Panel
1. Open browser: `http://192.168.1.1`
2. Login (common defaults):
   - Username: `admin` or `administrator`
   - Password: `admin`, `password`, or on router sticker

#### Step 3: Configure Port Forwarding

**Add these rules in router:**

| Service | External Port | Internal IP | Internal Port | Protocol |
|---------|---------------|-------------|---------------|----------|
| HTTP | 80 | 192.168.1.222 | 8000 | TCP |
| HTTPS | 443 | 192.168.1.222 | 8443 | TCP |
| SSH | 2222 | 192.168.1.222 | 8022 | TCP |
| PostgreSQL | 5432 | 192.168.1.222 | 5432 | TCP |
| Redis | 6379 | 192.168.1.222 | 6379 | TCP |

**⚠️ Security Warning:**
- **DO NOT** expose PostgreSQL (5432) to internet in production
- **DO NOT** expose Redis (6379) to internet
- Only expose HTTP/HTTPS

#### Step 4: Find Public IP
```powershell
# Get your public IP
curl ifconfig.me
# or visit: https://whatismyip.com
```

#### Step 5: Update Laravel .env
```env
APP_URL=http://YOUR_PUBLIC_IP

# Or if using domain
APP_URL=https://yourdomain.com
```

#### Step 6: Test External Access
```bash
# From outside your network (use phone with mobile data)
curl http://YOUR_PUBLIC_IP
```

---

### 🚇 Option 3: Ngrok Tunnel (Quick Setup)

#### On Server (192.168.1.222)

**Install Ngrok on Termux:**
```bash
# SSH to server
ssh -p 8022 u0_a175@192.168.1.222

# Install ngrok
pkg install wget unzip
wget https://bin.equinox.io/c/bNyj1mQVY4c/ngrok-v3-stable-linux-arm64.tgz
tar -xzf ngrok-v3-stable-linux-arm64.tgz
mv ngrok $PREFIX/bin/

# Configure authtoken (replace with your token from ngrok dashboard)
ngrok config add-authtoken <YOUR_NGROK_AUTHTOKEN>

# Start tunnel to port 8000
ngrok http 8000
```

**You'll get URL like:**
```
Forwarding: https://abc123.ngrok-free.app -> http://localhost:8000
```

#### Update Laravel .env
```env
APP_URL=https://abc123.ngrok-free.app

# For LINE webhooks
LINE_CALLBACK_URL=https://abc123.ngrok-free.app/line/callback
```

**Pros:**
- ✅ Instant HTTPS
- ✅ No router config needed
- ✅ Works behind any firewall

**Cons:**
- ❌ URL changes every restart (free plan)
- ❌ Rate limited
- ❌ Not for production

---

### 🔒 Option 4: Reverse Proxy Setup (Production)

#### Architecture:
```
Internet → Nginx (192.168.1.45) → Laravel (192.168.1.222)
```

#### On Your Computer (192.168.1.45)

**Install Nginx:**
```powershell
# Using Chocolatey
choco install nginx

# Or download from: http://nginx.org/en/download.html
```

**Configure Nginx (`C:\nginx\conf\nginx.conf`):**
```nginx
http {
    upstream backend {
        server 192.168.1.222:8000;
    }

    server {
        listen 80;
        server_name yourdomain.com;

        location / {
            proxy_pass http://backend;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        # WebSocket support for Reverb
        location /ws {
            proxy_pass http://192.168.1.222:8080;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
        }
    }
}
```

**Start Nginx:**
```powershell
cd C:\nginx
start nginx
```

---

## 🔧 Server-Side Configuration (192.168.1.222)

### Check Current Network Setup

```bash
# SSH to server
ssh -p 8022 u0_a175@192.168.1.222

# Check IP address
ip addr show

# Check listening ports
netstat -tlnp | grep -E '8000|5432|6379|8080'

# Check firewall (Termux usually doesn't have firewall)
iptables -L -n 2>/dev/null || echo "No iptables"
```

### Configure Laravel for Network Access

**On Server (192.168.1.222):**
```bash
# Edit .env
nano ~/uni-activity/.env
```

**Update these settings:**
```env
APP_URL=http://192.168.1.222:8000
# or
APP_URL=http://YOUR_PUBLIC_IP
# or
APP_URL=https://yourdomain.com

# Reverb for external access
REVERB_SERVER_HOST=0.0.0.0
REVERB_HOST=192.168.1.222
# or YOUR_PUBLIC_IP

# Trust proxies (if using reverse proxy)
TRUSTED_PROXIES=192.168.1.45,192.168.1.1
```

### Start Laravel Server

```bash
# On server 192.168.1.222
cd ~/uni-activity

# Option 1: PHP built-in server (development)
php artisan serve --host=0.0.0.0 --port=8000

# Option 2: Using screen (keeps running)
screen -S laravel
php artisan serve --host=0.0.0.0 --port=8000
# Press Ctrl+A then D to detach

# Option 3: Using nohup
nohup php artisan serve --host=0.0.0.0 --port=8000 > /dev/null 2>&1 &
```

---

## 🔐 Security Recommendations

### Firewall Rules (If Available)

```bash
# Only allow specific IPs to database
iptables -A INPUT -p tcp --dport 5432 -s 192.168.1.0/24 -j ACCEPT
iptables -A INPUT -p tcp --dport 5432 -j DROP

# Only allow specific IPs to Redis
iptables -A INPUT -p tcp --dport 6379 -s 192.168.1.0/24 -j ACCEPT
iptables -A INPUT -p tcp --dport 6379 -j DROP
```

### PostgreSQL Access Control

**Edit `$PREFIX/var/lib/postgresql/pg_hba.conf`:**
```
# Allow only from your network
host    all    all    192.168.1.0/24    md5

# Or specific IP only
host    all    all    192.168.1.45/32   md5

# Reject all others
host    all    all    0.0.0.0/0         reject
```

### Redis Security

**Edit `$PREFIX/etc/redis.conf`:**
```conf
# Bind to specific network only
bind 192.168.1.222 127.0.0.1

# Require password
requirepass YOUR_STRONG_PASSWORD

# Disable dangerous commands
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command CONFIG ""
```

---

## 📊 Routing Table Management

### View Current Routes

```bash
# On server (Termux)
ip route show

# Common output:
# default via 192.168.1.1 dev wlan0
# 192.168.1.0/24 dev wlan0 scope link
```

### Add Static Route (if needed)

```bash
# Add route to specific network
ip route add 10.0.0.0/24 via 192.168.1.1

# Make persistent (add to startup script)
echo "ip route add 10.0.0.0/24 via 192.168.1.1" >> ~/.bashrc
```

---

## 🧪 Testing Checklist

### Test 1: Local Network Access
```bash
# From your PC (192.168.1.45)
curl http://192.168.1.222:8000

# Should return Laravel welcome page or app
```

### Test 2: Database Connection
```bash
# From your PC
psql -h 192.168.1.222 -U admin -d uni_activity -c "SELECT 1;"
```

### Test 3: Redis Connection
```bash
# From your PC
redis-cli -h 192.168.1.222 ping
```

### Test 4: External Access (if configured)
```bash
# From outside network (use phone with mobile data)
curl http://YOUR_PUBLIC_IP

# Or visit in browser
# http://YOUR_PUBLIC_IP
```

### Test 5: WebSocket (Reverb)
```javascript
// In browser console (if using Reverb)
const socket = new WebSocket('ws://192.168.1.222:8080');
socket.onopen = () => console.log('Connected!');
```

---

## 🎯 Quick Setup Script

**Create this on your server:**
```bash
#!/data/data/com.termux/files/usr/bin/bash
# network-setup.sh

echo "🌐 Network Setup for Production Server"
echo ""

# Check current IP
echo "Current IP addresses:"
ip addr show | grep "inet " | grep -v "127.0.0.1"

echo ""
echo "Listening services:"
netstat -tlnp 2>/dev/null | grep -E "5432|6379|8000|8080"

echo ""
echo "Testing external access:"
echo "  Laravel: http://192.168.1.222:8000"
echo "  PostgreSQL: 192.168.1.222:5432"
echo "  Redis: 192.168.1.222:6379"
echo "  Reverb: ws://192.168.1.222:8080"

echo ""
echo "To start Laravel server:"
echo "  php artisan serve --host=0.0.0.0 --port=8000"
```

---

## 📝 Common Issues & Solutions

### Issue 1: Cannot access from external network
**Solution:**
1. Check router port forwarding
2. Verify firewall rules
3. Check if ISP blocks ports
4. Use Ngrok as alternative

### Issue 2: "Connection refused"
**Solution:**
1. Ensure service binds to `0.0.0.0` not `127.0.0.1`
2. Check if service is running: `pgrep -l postgres redis`
3. Verify port: `netstat -tlnp | grep PORT`

### Issue 3: Slow connection from external
**Solution:**
1. Check network latency: `ping 192.168.1.222`
2. Enable caching (Redis)
3. Use CDN for static assets
4. Enable gzip compression

---

## 🚀 Recommended Setup for Production

**Best Practice Architecture:**
```
Internet
   ↓
Cloudflare (CDN + DDoS protection)
   ↓
Your Public IP with SSL
   ↓
Nginx Reverse Proxy (192.168.1.45)
   ↓
Laravel App (192.168.1.222:8000)
   ├── PostgreSQL (local: 127.0.0.1:5432)
   ├── Redis (local: 127.0.0.1:6379)
   └── Reverb (0.0.0.0:8080)
```

**Benefits:**
- ✅ SSL/TLS encryption
- ✅ DDoS protection
- ✅ Load balancing ready
- ✅ Database not exposed
- ✅ Caching at edge

---

## 📄 Next Steps

1. **Choose your setup option** (1-4 above)
2. **Configure accordingly**
3. **Test thoroughly**
4. **Monitor performance**
5. **Setup SSL certificate** (Let's Encrypt)
6. **Configure backups**

---

## 💡 Quick Commands Reference

```bash
# Check network status
ip addr show
netstat -tlnp

# Test connectivity
ping 192.168.1.222
curl http://192.168.1.222:8000
psql -h 192.168.1.222 -U admin -d uni_activity

# Start services
php artisan serve --host=0.0.0.0 --port=8000
php artisan reverb:start --host=0.0.0.0 --port=8080

# Monitor connections
watch -n 1 'netstat -an | grep -E "5432|6379|8000" | wc -l'
```

---

**Current Status:** ✅ Server ready for network configuration  
**Recommended:** Start with Option 1 (LAN) then move to Option 3 (Ngrok) for testing  
**Production:** Use Option 4 (Reverse Proxy) with SSL

**Need help with specific setup? Let me know which option you want! 🚀**
