# 📱 Second Device Setup Guide

**Main Server:** Huawei DUB-LX3 (Termux) @ 192.168.1.222:8022  
**Second Device:** Phone with Shizuki + Termux  
**Connection:** USB ADB  
**Goal:** Distribute workload — AI Server, Monitor, Queue Worker, Tunnel

---

## Architecture

```
┌──────────────────────┐          USB ADB          ┌──────────────────────┐
│    MAIN SERVER       │ ◄──────────────────────── │   SECOND DEVICE      │
│  192.168.1.222       │    code sync + control     │  (Shizuki+Termux)    │
│                      │                            │                      │
│  ✅ Laravel Octane   │                            │  ✅ AI Server (8001) │
│  ✅ PostgreSQL       │                            │  ✅ Monitor (9999)   │
│  ✅ Redis (primary)  │                            │  ✅ Queue Worker     │
│  ✅ WebSocket        │                            │  ✅ Cloudflare Tunnel│
│  ✅ PHP-FPM          │                            │  ✅ Redis (local)    │
│                      │                            │                      │
│  Public URL: ─────────────────────────────►  Cloudflare → same domain   │
└──────────────────────┘                            └──────────────────────┘
```

---

## Quick Start (3 Steps)

### Step 1: Connect USB & Enable ADB
```bash
# On your computer (with ADB installed):
adb devices
# Should show: XXXXXXXX device

# If not showing:
# 1. Enable Developer Options on second device
# 2. Enable USB Debugging
# 3. Accept ADB authorization prompt
```

### Step 2: Run Setup Script
```bash
# On main server / computer:
cd ~/uni-activity
bash scripts/setup_adb_device.sh
```

### Step 3: Sync Code & Start Services
```bash
# Sync latest code to second device:
bash scripts/sync_to_device.sh

# Start worker services on second device:
bash scripts/offload_services.sh

# Check status of both devices:
bash scripts/device_dashboard.sh
```

---

## Services Distribution

| Service | Main Server | Second Device | Notes |
|---------|:-----------:|:------------:|-------|
| Laravel (Octane) | ✅ Primary | ❌ | API/Web server |
| PostgreSQL | ✅ Primary | ❌ | Database |
| Redis (primary) | ✅ Primary | ✅ Local cache | Queue broker |
| AI Server (InsightFace) | ❌ | ✅ **Offloaded** | Port 8001 |
| Monitor Server | ❌ | ✅ **Offloaded** | Port 9999 |
| Queue Worker | ❌ | ✅ **Offloaded** | Redis queue |
| Cloudflare Tunnel | ❌ | ✅ **Offloaded** | → Main server |
| WebSocket (Reverb) | ✅ Primary | ❌ | Real-time |

---

## Manual Setup (If Scripts Don't Work)

### On Second Device (Termux):
```bash
# Install packages
pkg update -y && pkg install -y openssh python git nodejs-lts redis rsync cloudflared

# Setup SSH
ssh-keygen -A
mkdir -p ~/.ssh && chmod 700 ~/.ssh
# Paste main server public key into ~/.ssh/authorized_keys
sshd -p 8023

# Setup Redis
cat > ~/redis.conf << 'EOF'
bind 127.0.0.1
port 6379
protected-mode yes
requirepass UniActivityRedis2026!
daemonize yes
EOF
redis-server ~/redis.conf
```

### Copy Project from Main Server:
```bash
# From main server:
rsync -avz --exclude='vendor' --exclude='node_modules' --exclude='.git' \
  -e "ssh -p 8022" \
  /data/data/com.termux/files/home/uni-activity/ \
  u0_a175@SECOND_DEVICE_IP:~/uni-activity/

# Or via ADB (USB — faster):
cd ~/uni-activity
tar czf /tmp/sync.tar.gz --exclude=vendor --exclude=node_modules --exclude=.git .
adb push /tmp/sync.tar.gz /sdcard/
adb shell "su -c 'cp /sdcard/sync.tar.gz /data/data/com.termux/files/home/ && cd /data/data/com.termux/files/home/uni-activity && tar xzf ../sync.tar.gz'"
```

### Install Dependencies on Second Device:
```bash
cd ~/uni-activity
composer install --no-dev --optimize-autoloader
pip install -r py/requirements_deploy.txt
```

### Start Services:
```bash
cd ~/uni-activity

# AI Server (background)
nohup python ai_service/server.py > storage/logs/ai-server.log 2>&1 &

# Monitor Server (background)
nohup python py/monitor_server.py > storage/logs/monitor.log 2>&1 &

# Queue Worker (background)
nohup php artisan queue:work redis --sleep=3 --tries=3 --timeout=60 > storage/logs/queue-worker.log 2>&1 &

# Cloudflare Tunnel → main server
nohup cloudflared tunnel --url http://192.168.1.222:8000 --no-autoupdate > storage/logs/tunnel.log 2>&1 &
```

---

## Management Commands

```bash
# Sync code (USB ADB — fast):
./scripts/sync_to_device.sh

# Start all services:
./scripts/offload_services.sh

# Start only AI server:
./scripts/offload_services.sh --only-ai

# Start only monitor:
./scripts/offload_services.sh --only-monitor

# Stop all services:
./scripts/offload_services.sh --stop

# Check status:
./scripts/offload_services.sh --status

# Dashboard (both devices):
./scripts/device_dashboard.sh
```

---

## Troubleshooting

### ADB not detecting device:
```bash
# Restart ADB server
adb kill-server
adb start-server
adb devices

# If still not detected, check USB cable and try different port
```

### Cannot run Termux commands via ADB:
```bash
# Option 1: Use Shizuku to grant root
# Option 2: SSH instead of ADB for commands
ssh -p 8023 u0_a175@SECOND_DEVICE_IP

# Option 3: Use Termux:Boot to auto-start SSH on boot
pkg install termux-services
sv-enable sshd
sv start sshd
```

### Services not starting:
```bash
# Check logs
cat ~/uni-activity/storage/logs/ai-server.log
cat ~/uni-activity/storage/logs/monitor.log
cat ~/uni-activity/storage/logs/queue-worker.log
cat ~/uni-activity/storage/logs/tunnel.log

# Check if ports are in use
ss -tlnp | grep -E '8001|9999|6379'

# Check Redis connection
redis-cli -a UniActivityRedis2026! ping
```

### Sync keeps failing:
```bash
# Force fresh sync
rm -rf ~/uni-activity/vendor ~/uni-activity/node_modules
./scripts/sync_to_device.sh

# Or manual full resync:
adb shell "su -c 'rm -rf /data/data/com.termux/files/home/uni-activity'"
./scripts/sync_to_device.sh
```

---

## Environment Variables (.env.second-device)

Copy main `.env` to second device and add:

```env
# Device Role
DEVICE_ROLE=worker

# Local Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=UniActivityRedis2026!

# Main Server (for tunnel proxy)
MAIN_SERVER=http://192.168.1.222:8000

# AI Service (runs locally on this device)
AI_SERVICE_URL=http://127.0.0.1:8001

# Monitor (runs locally)
MONITOR_PORT=9999
```

---

## Auto-Sync Setup

To keep second device in sync automatically:

```bash
# On main server, create cron job:
crontab -e
# Add:
*/5 * * * * cd ~/uni-activity && bash scripts/sync_to_device.sh >> storage/logs/device-sync.log 2>&1
```

Or use the existing auto-sync in monitor_server.py which checks GitHub every 60 seconds.
