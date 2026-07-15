# 🐳 Docker Cache Clear & Live Build Guide

## 🚀 Quick Start

### Method 1: PowerShell Script (Recommended)
```powershell
.\docker-rebuild.ps1
```

### Method 2: Batch File
```bash
docker-rebuild.bat
```

### Method 3: Manual Commands
See "Manual Steps" section below.

---

## 📋 What This Does

The rebuild process will:

1. ✅ Stop all running containers
2. ✅ Remove all containers
3. ✅ Remove all Docker images
4. ✅ Remove all volumes (data will be lost!)
5. ✅ Remove custom networks
6. ✅ Prune Docker system cache
7. ✅ Build fresh images from Dockerfiles
8. ✅ Start all containers

**⚠️ Warning:** This will delete all Docker data including databases!

---

## 🎯 When to Use This

### Use Docker Rebuild When:
- ✅ Dockerfile changes not taking effect
- ✅ Dependencies not updating
- ✅ Containers behaving unexpectedly
- ✅ "Image not found" errors
- ✅ Build cache causing issues
- ✅ Starting fresh after major changes

### Don't Use When:
- ❌ Just need to restart containers (use `docker-compose restart`)
- ❌ Want to keep database data
- ❌ Only config changes (use `docker-compose up -d`)

---

## 📝 Step-by-Step Process

### Automated (Recommended)

1. **Run the script:**
   ```powershell
   .\docker-rebuild.ps1
   ```

2. **Wait for completion** (5-15 minutes depending on your system)

3. **Verify services:**
   ```powershell
   .\test-api-health.ps1
   ```

4. **Access application:**
   ```
   http://localhost:8000
   ```

### Manual Steps

If you prefer manual control:

```powershell
# 1. Stop all containers
docker-compose down

# 2. Remove all containers
docker rm -f $(docker ps -aq)

# 3. Remove all images
docker rmi -f $(docker images -q)

# 4. Remove all volumes
docker volume rm -f $(docker volume ls -q)

# 5. Remove networks
docker network prune -f

# 6. Prune system
docker system prune -af --volumes

# 7. Build and start
docker-compose up -d --build

# 8. Check status
docker ps
```

---

## 🔍 Monitoring the Build

### Watch Build Progress
```powershell
# Follow logs during build
docker-compose logs -f
```

### Check Container Status
```powershell
docker ps
```

### Check Specific Service
```powershell
docker logs ms-user-service
docker logs ms-activity-service
docker logs ms-kong
```

### Check Health Status
```powershell
docker ps --format "table {{.Names}}\t{{.Status}}"
```

---

## ✅ Verification Steps

### 1. Check All Containers Running
```powershell
docker ps
```

Expected: 15-20 containers running

### 2. Test Microservices Health
```powershell
.\test-api-health.ps1
```

Expected: All services showing "healthy"

### 3. Test Web Access
```
http://localhost:8000
```

Expected: Application loads

### 4. Test API Gateway
```
http://localhost:80
```

Expected: Kong gateway responds

### 5. Test Individual Services
```
http://localhost:8001/health  (User Service)
http://localhost:8002/health  (Activity Service)
http://localhost:8003/health  (Job Service)
```

Expected: All return "healthy"

---

## 🛠️ Troubleshooting

### Build Fails

**Problem:** Docker build fails with errors

**Solutions:**
1. Check Docker Desktop is running
2. Ensure enough disk space (10GB+ free)
3. Check internet connection (downloads images)
4. Review error messages in output
5. Try building one service at a time:
   ```powershell
   docker-compose build user-service
   docker-compose up -d user-service
   ```

### Containers Won't Start

**Problem:** Containers exit immediately

**Solutions:**
1. Check logs:
   ```powershell
   docker logs <container-name>
   ```
2. Check port conflicts:
   ```powershell
   netstat -ano | findstr "8000"
   ```
3. Verify .env configuration
4. Check database credentials

### Services Not Healthy

**Problem:** Health checks failing

**Solutions:**
1. Wait longer (can take 2-3 minutes)
2. Check service logs
3. Verify database connections
4. Check network connectivity between containers

### Out of Disk Space

**Problem:** "No space left on device"

**Solutions:**
1. Clean Docker:
   ```powershell
   docker system prune -af --volumes
   ```
2. Remove unused images:
   ```powershell
   docker image prune -af
   ```
3. Check disk space:
   ```powershell
   Get-PSDrive C
   ```

---

## 📊 Build Time Estimates

| Component | Time | Notes |
|-----------|------|-------|
| Stop containers | 10s | Quick |
| Remove containers | 5s | Quick |
| Remove images | 30s | Depends on count |
| Remove volumes | 10s | Quick |
| System prune | 1-2min | Cleans cache |
| Build images | 5-10min | Downloads & builds |
| Start containers | 1-2min | Initialization |
| **Total** | **8-15min** | First time longer |

---

## 🔄 Alternative: Selective Rebuild

If you don't want to rebuild everything:

### Rebuild Single Service
```powershell
docker-compose build user-service
docker-compose up -d user-service
```

### Rebuild Without Cache
```powershell
docker-compose build --no-cache user-service
```

### Restart Without Rebuild
```powershell
docker-compose restart
```

### Update and Restart
```powershell
docker-compose up -d
```

---

## 💾 Backup Before Rebuild

### Export Database Data
```powershell
# MySQL databases
docker exec ms-user-db mysqldump -u root -p uni_activity > backup_user.sql
docker exec ms-activity-db mysqldump -u root -p uni_activity > backup_activity.sql

# MongoDB
docker exec ms-notification-db mongodump --out /backup
```

### Export Volumes
```powershell
docker run --rm -v mysql-data:/data -v ${PWD}:/backup alpine tar czf /backup/mysql-data.tar.gz /data
```

---

## 🚀 Post-Rebuild Checklist

After rebuild completes:

- [ ] All containers running (`docker ps`)
- [ ] Health checks passing (`.\test-api-health.ps1`)
- [ ] Web application accessible (http://localhost:8000)
- [ ] API Gateway responding (http://localhost:80)
- [ ] Microservices healthy (ports 8001-8005)
- [ ] Databases accessible
- [ ] Redis connected
- [ ] RabbitMQ running
- [ ] No error logs (`docker-compose logs`)

---

## 📈 Performance Tips

### Speed Up Builds

1. **Use BuildKit:**
   ```powershell
   $env:DOCKER_BUILDKIT=1
   docker-compose build
   ```

2. **Parallel builds:**
   ```powershell
   docker-compose build --parallel
   ```

3. **Use .dockerignore:**
   Ensure `.dockerignore` excludes unnecessary files

4. **Layer caching:**
   Order Dockerfile commands from least to most frequently changed

---

## 🎯 Quick Commands Reference

```powershell
# Full rebuild (clears everything)
.\docker-rebuild.ps1

# Stop all
docker-compose down

# Start all
docker-compose up -d

# Restart all
docker-compose restart

# View logs
docker-compose logs -f

# Check status
docker ps

# Test health
.\test-api-health.ps1

# Remove everything
docker system prune -af --volumes

# Build without cache
docker-compose build --no-cache

# Rebuild single service
docker-compose up -d --build user-service
```

---

## 📞 Support

### Check Docker Version
```powershell
docker --version
docker-compose --version
```

### Check Docker Info
```powershell
docker info
```

### Check System Resources
```powershell
docker system df
```

### View All Containers (including stopped)
```powershell
docker ps -a
```

---

## ⚠️ Important Notes

1. **Data Loss:** Rebuild removes all volumes and data
2. **Time:** First build takes longer (downloads images)
3. **Internet:** Requires internet connection
4. **Resources:** Needs 4GB+ RAM, 10GB+ disk space
5. **Backup:** Always backup important data first

---

## 🎉 Success Indicators

After successful rebuild:

✅ All containers show "Up" status
✅ Health checks show "healthy"
✅ No error messages in logs
✅ Application accessible at localhost:8000
✅ API endpoints responding
✅ Databases accepting connections

---

**Ready to rebuild? Run `.\docker-rebuild.ps1` and grab a coffee! ☕**
