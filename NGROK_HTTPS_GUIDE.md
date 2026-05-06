# 🔒 Ngrok HTTPS Configuration Guide

## Overview

Ngrok provides a secure HTTPS tunnel to your local Docker application, allowing you to:
- Access your app via HTTPS from anywhere
- Test webhooks and external integrations
- Share your development environment
- Test on mobile devices
- Use features that require HTTPS (camera, geolocation, etc.)

---

## 🚀 Quick Setup

### Step 1: Get Ngrok Auth Token (Free)

1. **Sign up for ngrok:**
   ```
   https://dashboard.ngrok.com/signup
   ```

2. **Get your auth token:**
   ```
   https://dashboard.ngrok.com/get-started/your-authtoken
   ```

3. **Copy your token** (looks like: `2abc...xyz`)

### Step 2: Add Token to .env

Open `.env` and add your token:
```env
NGROK_AUTHTOKEN=your_token_here
```

### Step 3: Run Setup Script

```powershell
.\setup-ngrok.ps1
```

That's it! Your app is now accessible via HTTPS.

---

## 📋 Manual Setup

If you prefer manual setup:

### 1. Add Token to .env
```env
NGROK_AUTHTOKEN=your_ngrok_token
```

### 2. Start Ngrok Container
```powershell
docker-compose up -d ngrok
```

### 3. Get Your HTTPS URL
```powershell
# Visit ngrok dashboard
Start-Process "http://localhost:4040"

# Or get URL via API
Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" | 
  Select-Object -ExpandProperty tunnels | 
  Where-Object { $_.proto -eq "https" } | 
  Select-Object -ExpandProperty public_url
```

### 4. Update APP_URL
Update `.env` with your ngrok URL:
```env
APP_URL=https://abc123.ngrok-free.app
```

### 5. Restart App
```powershell
docker-compose restart app
```

---

## 🎯 What You Get

### HTTPS URLs
```
HTTPS: https://abc123.ngrok-free.app
HTTP:  http://abc123.ngrok-free.app
```

### Ngrok Dashboard
```
http://localhost:4040
```

Features:
- View all requests in real-time
- Inspect request/response details
- Replay requests
- View connection status

---

## 🔧 Configuration Options

### Basic Configuration (Current)
```yaml
ngrok:
  image: ngrok/ngrok:latest
  command:
    - "http"
    - "app:80"
  environment:
    - NGROK_AUTHTOKEN=${NGROK_AUTHTOKEN}
  ports:
    - "4040:4040"
```

### Advanced Configuration

#### Custom Domain (Paid Plan)
```yaml
ngrok:
  command:
    - "http"
    - "app:80"
    - "--domain=myapp.ngrok.io"
```

#### Basic Auth
```yaml
ngrok:
  command:
    - "http"
    - "app:80"
    - "--basic-auth=username:password"
```

#### IP Restrictions (Paid Plan)
```yaml
ngrok:
  command:
    - "http"
    - "app:80"
    - "--cidr-allow=1.2.3.4/32"
```

#### Custom Region
```yaml
ngrok:
  command:
    - "http"
    - "app:80"
    - "--region=eu"  # us, eu, ap, au, sa, jp, in
```

---

## 📱 Use Cases

### 1. Mobile Testing
Access your app from your phone:
```
https://abc123.ngrok-free.app
```

### 2. Webhook Testing
Use ngrok URL for webhooks:
```
Webhook URL: https://abc123.ngrok-free.app/webhook
```

### 3. External API Testing
Test OAuth callbacks, payment gateways, etc.

### 4. Share with Team
Share the ngrok URL with team members for testing.

### 5. HTTPS-Required Features
Test features that require HTTPS:
- Camera access
- Geolocation
- Service workers
- PWA features

---

## 🛠️ Useful Commands

### View Ngrok Status
```powershell
docker ps | Select-String "ngrok"
```

### View Ngrok Logs
```powershell
docker logs laravel-ngrok -f
```

### Restart Ngrok
```powershell
docker-compose restart ngrok
```

### Stop Ngrok
```powershell
docker-compose stop ngrok
```

### Get Current URL
```powershell
Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" | 
  Select-Object -ExpandProperty tunnels | 
  Select-Object proto, public_url
```

### Update APP_URL Automatically
```powershell
$url = (Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels").tunnels | 
  Where-Object { $_.proto -eq "https" } | 
  Select-Object -ExpandProperty public_url

$env = Get-Content .env -Raw
$env = $env -replace 'APP_URL=.*', "APP_URL=$url"
Set-Content .env -Value $env -NoNewline

docker-compose restart app
```

---

## 🔍 Troubleshooting

### Ngrok Container Won't Start

**Check logs:**
```powershell
docker logs laravel-ngrok
```

**Common issues:**
- Invalid auth token
- Port 4040 already in use
- Network connectivity issues

### No HTTPS URL Showing

**Wait a moment:**
```powershell
Start-Sleep -Seconds 10
Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels"
```

**Check if ngrok is running:**
```powershell
docker ps | Select-String "ngrok"
```

### "ERR_NGROK_108" Error

This means your auth token is invalid or missing.

**Solution:**
1. Get a new token from https://dashboard.ngrok.com
2. Update `.env` with correct token
3. Restart: `docker-compose restart ngrok`

### URL Changes on Restart

Free ngrok URLs change on each restart.

**Solutions:**
- Use paid plan for static domain
- Update APP_URL after each restart
- Use the update script above

### App Not Accessible via Ngrok

**Check APP_URL:**
```powershell
docker exec laravel-app php artisan config:show app.url
```

**Should match ngrok URL:**
```env
APP_URL=https://abc123.ngrok-free.app
```

**If different, update and restart:**
```powershell
# Update .env
docker exec laravel-app php artisan config:clear
docker-compose restart app
```

---

## 📊 Ngrok Plans

### Free Plan
- ✅ HTTPS tunnels
- ✅ Random URLs
- ✅ 40 connections/minute
- ❌ Custom domains
- ❌ Reserved domains
- ❌ IP restrictions

### Paid Plans
- ✅ Custom domains
- ✅ Reserved domains
- ✅ More connections
- ✅ IP restrictions
- ✅ Basic auth
- ✅ Multiple tunnels

See: https://ngrok.com/pricing

---

## 🔒 Security Considerations

### 1. Don't Expose Production Data
Ngrok makes your local app publicly accessible. Don't use with production databases.

### 2. Use Auth Token
Always use an auth token (even free tier).

### 3. Add Basic Auth (Optional)
```yaml
command:
  - "http"
  - "app:80"
  - "--basic-auth=user:pass"
```

### 4. Monitor Access
Check ngrok dashboard for unexpected requests:
```
http://localhost:4040
```

### 5. Temporary Use
Stop ngrok when not needed:
```powershell
docker-compose stop ngrok
```

---

## 📝 Configuration Files

### docker-compose.yml
```yaml
ngrok:
  image: ngrok/ngrok:latest
  container_name: laravel-ngrok
  restart: unless-stopped
  command:
    - "http"
    - "app:80"
    - "--log=stdout"
    - "--log-level=info"
  environment:
    - NGROK_AUTHTOKEN=${NGROK_AUTHTOKEN:-}
  networks:
    - laravel-network
  ports:
    - "4040:4040"
  depends_on:
    - app
```

### .env
```env
NGROK_AUTHTOKEN=your_token_here
APP_URL=https://your-ngrok-url.ngrok-free.app
```

---

## ✅ Verification Checklist

- [ ] Ngrok auth token added to .env
- [ ] Ngrok container running (`docker ps`)
- [ ] Dashboard accessible (http://localhost:4040)
- [ ] HTTPS URL obtained
- [ ] APP_URL updated in .env
- [ ] App container restarted
- [ ] Application accessible via HTTPS URL
- [ ] No SSL warnings in browser

---

## 🎉 Success!

Once configured, you'll have:
- ✅ HTTPS access to your local app
- ✅ Public URL for testing
- ✅ Real-time request inspection
- ✅ Mobile device testing capability
- ✅ Webhook testing support

---

## 📚 Additional Resources

- **Ngrok Documentation:** https://ngrok.com/docs
- **Ngrok Dashboard:** https://dashboard.ngrok.com
- **Ngrok API:** https://ngrok.com/docs/api
- **Docker Hub:** https://hub.docker.com/r/ngrok/ngrok

---

**Your application is now accessible via HTTPS through ngrok! 🔒**
