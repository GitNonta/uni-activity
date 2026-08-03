# 🚀 UNI ACTIVITY - Deployment & Service Management

## 🔴 Critical Alert: Reverb Service Offline & 502 Bad Gateway

### Quick Fix (Emergency)

If Reverb is down RIGHT NOW:

```bash
python py/emergency_reverb_fix.py
```

This will:
- ✅ Kill old Reverb processes
- ✅ Check and start Redis, PHP-FPM, Nginx if needed
- ✅ Start Reverb with proper configuration
- ✅ Show service status

---

## 🛠️ Complete Fix & Deployment

For comprehensive diagnostics and fixes:

```bash
python py/fix_reverb_and_deploy.py
```

This script will:
1. **Diagnose** all services (Redis, PHP-FPM, Nginx, Reverb)
2. **Fix** Nginx configuration for WebSocket support
3. **Restart** all services in correct order
4. **Verify** deployment is successful
5. **Show** detailed status dashboard

---

## 📊 Service Monitoring

### Continuous Monitoring (every 10 seconds)

```bash
python py/monitor_services.py
```

### Quick One-Time Check

```bash
python py/monitor_services.py quick
```

---

## 🐛 Common Issues & Solutions

### Issue 1: 502 Bad Gateway

**Cause:** PHP-FPM or Nginx not running/misconfigured

**Solution:**
```bash
# Check PHP-FPM
pgrep php-fpm

# If not running, start it
php-fpm

# Check Nginx
pgrep nginx

# If not running, start it
nginx
```

### Issue 2: Reverb Service Offline

**Cause:** Reverb process crashed or not started

**Solution:**
```bash
# Kill old processes
pkill -9 -f 'artisan reverb'

# Start Reverb
cd /data/data/com.termux/files/home/uni-activity
nohup php artisan reverb:start --host=0.0.0.0 --port=8082 > storage/logs/reverb.log 2>&1 &

# Check log
tail -f storage/logs/reverb.log
```

### Issue 3: WebSocket Connection Failed

**Cause:** Nginx not properly proxying WebSocket connections

**Solution:**
Run the comprehensive fix script which will update Nginx config:
```bash
python py/fix_reverb_and_deploy.py
```

### Issue 4: Redis Connection Error

**Cause:** Redis server not running

**Solution:**
```bash
# Start Redis
redis-server --daemonize yes

# Check if running
pgrep redis-server
```

---

## 📋 Manual Service Management

### Start All Services (Correct Order)

```bash
# 1. Redis
redis-server --daemonize yes

# 2. PHP-FPM
php-fpm

# 3. Nginx
nginx

# 4. Reverb
cd /data/data/com.termux/files/home/uni-activity
nohup php artisan reverb:start --host=0.0.0.0 --port=8082 > storage/logs/reverb.log 2>&1 &
```

### Stop All Services

```bash
# Stop Reverb
pkill -f 'artisan reverb'

# Stop Nginx
nginx -s stop

# Stop PHP-FPM
pkill php-fpm

# Stop Redis (optional)
pkill redis-server
```

### Restart Services

```bash
# Restart Nginx
nginx -s reload

# Restart PHP-FPM
pkill php-fpm && php-fpm

# Restart Reverb
pkill -f 'artisan reverb' && \
cd /data/data/com.termux/files/home/uni-activity && \
nohup php artisan reverb:start --host=0.0.0.0 --port=8082 > storage/logs/reverb.log 2>&1 &
```

---

## 🔍 Diagnostic Commands

### Check Process Status

```bash
# Check all services
ps aux | grep -E '(redis|php-fpm|nginx|reverb)' | grep -v grep

# Check specific service
pgrep -a nginx
pgrep -a php-fpm
pgrep -a redis-server
pgrep -af 'artisan reverb'
```

### Check Listening Ports

```bash
# Check all important ports
netstat -tuln | grep -E ':(80|8080|8082|9000|6379)'

# Check specific port
netstat -tuln | grep :8080  # Nginx
netstat -tuln | grep :8082  # Reverb
netstat -tuln | grep :9000  # PHP-FPM
netstat -tuln | grep :6379  # Redis
```

### Check Logs

```bash
# Reverb log
tail -f /data/data/com.termux/files/home/uni-activity/storage/logs/reverb.log

# Laravel log
tail -f /data/data/com.termux/files/home/uni-activity/storage/logs/laravel.log

# Nginx error log
tail -f /data/data/com.termux/files/usr/var/log/nginx/error.log

# Nginx access log
tail -f /data/data/com.termux/files/usr/var/log/nginx/access.log
```

---

## 🌐 Service Endpoints

After deployment, test these endpoints:

- **HTTP:** http://192.168.1.222:8080
- **WebSocket:** ws://192.168.1.222:8080/app/uni-chat-key
- **Health Check:** http://192.168.1.222:8080/health

---

## ⚙️ Configuration Files

### Nginx Config
**Location:** `/data/data/com.termux/files/usr/etc/nginx/nginx.conf`

Key settings:
- HTTP port: 8080
- WebSocket proxy: `/app` → `127.0.0.1:8082`
- PHP-FPM: `127.0.0.1:9000`

### Reverb Config
**Location:** `config/reverb.php` (Laravel project)

Key settings:
- Host: `0.0.0.0`
- Port: `8082`
- App ID: `uni-chat`
- App Key: `uni-chat-key`

### Environment Variables
**Location:** `.env` (Laravel project)

Important Reverb variables:
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=uni-chat
REVERB_APP_KEY=uni-chat-key
REVERB_APP_SECRET=uni-chat-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_INTERNAL_PORT=8082
REVERB_SERVER_HOST=0.0.0.0
REVERB_SCHEME=http
```

---

## 🔐 Server Access

- **Host:** 192.168.1.222
- **SSH Port:** 8022
- **Username:** u0_a175
- **Platform:** Termux (Android)

---

## 📝 Deployment Checklist

Before deploying, ensure:

- [ ] Redis is installed and running
- [ ] PHP 8.2+ and PHP-FPM are installed
- [ ] Nginx is installed
- [ ] Laravel dependencies installed (`composer install`)
- [ ] `.env` file configured correctly
- [ ] Storage directories writable (`chmod -R 775 storage bootstrap/cache`)
- [ ] Laravel key generated (`php artisan key:generate`)
- [ ] Database migrated (`php artisan migrate`)
- [ ] Assets compiled (`npm run build`)

---

## 🆘 Emergency Contacts

If automated scripts fail:

1. **Check server connectivity:**
   ```bash
   ping 192.168.1.222
   ssh u0_a175@192.168.1.222 -p 8022
   ```

2. **Manual SSH access:**
   ```bash
   ssh u0_a175@192.168.1.222 -p 8022
   # Password: 2345678A
   ```

3. **Check system resources:**
   ```bash
   free -h          # Memory
   df -h            # Disk space
   top              # CPU usage
   ```

---

## 📚 Additional Resources

- [Laravel Reverb Documentation](https://laravel.com/docs/11.x/reverb)
- [Nginx WebSocket Proxying](https://nginx.org/en/docs/http/websocket.html)
- [PHP-FPM Configuration](https://www.php.net/manual/en/install.fpm.configuration.php)

---

## 🔄 Auto-Restart on Boot (Optional)

Create a systemd service or cron job to auto-start services on boot:

```bash
# Add to crontab
@reboot redis-server --daemonize yes
@reboot php-fpm
@reboot nginx
@reboot cd /data/data/com.termux/files/home/uni-activity && php artisan reverb:start --host=0.0.0.0 --port=8082 > storage/logs/reverb.log 2>&1 &
```

---

**Last Updated:** 2026-07-18
**Version:** 1.0.0
