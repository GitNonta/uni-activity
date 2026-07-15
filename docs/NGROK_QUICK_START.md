# 🚀 Ngrok HTTPS - Quick Start

## ⚡ 3-Step Setup

### 1. Get Your Token (30 seconds)
Visit: https://dashboard.ngrok.com/get-started/your-authtoken

### 2. Add to .env
```env
NGROK_AUTHTOKEN=your_token_here
```

### 3. Run Setup
```powershell
.\setup-ngrok.ps1
```

**Done!** Your app is now on HTTPS.

---

## 📍 Access Points

**Ngrok Dashboard:**
```
http://localhost:4040
```

**Your HTTPS URL:**
Check dashboard or run:
```powershell
Invoke-RestMethod http://localhost:4040/api/tunnels | 
  Select-Object -ExpandProperty tunnels | 
  Where-Object proto -eq "https" | 
  Select-Object -ExpandProperty public_url
```

---

## 🔧 Quick Commands

```powershell
# Start ngrok
docker-compose up -d ngrok

# View logs
docker logs laravel-ngrok -f

# Restart
docker-compose restart ngrok

# Stop
docker-compose stop ngrok

# Get URL
Start-Process "http://localhost:4040"
```

---

## ⚠️ Important Notes

1. **Free URLs change** on each restart
2. **Update APP_URL** in .env after getting URL
3. **Restart app** after updating APP_URL:
   ```powershell
   docker-compose restart app
   ```

---

## 📖 Full Guide

See `NGROK_HTTPS_GUIDE.md` for complete documentation.

---

**Quick setup complete! Access your app via HTTPS now. 🔒**
