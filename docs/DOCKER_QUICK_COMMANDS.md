# 🐳 Docker Quick Commands

## 🚀 Rebuild Commands

```powershell
# Full rebuild (clears cache)
.\docker-rebuild.ps1

# Or use batch file
docker-rebuild.bat
```

## 🔄 Common Operations

```powershell
# Stop all
docker-compose down

# Start all
docker-compose up -d

# Restart all
docker-compose restart

# Rebuild and start
docker-compose up -d --build

# View logs
docker-compose logs -f

# Check status
docker ps
```

## 🧪 Testing

```powershell
# Test all services
.\test-api-health.ps1

# Test single service
Invoke-WebRequest -Uri "http://localhost:8001/health"
```

## 🧹 Cleanup

```powershell
# Remove everything
docker system prune -af --volumes

# Remove stopped containers
docker container prune -f

# Remove unused images
docker image prune -af

# Remove unused volumes
docker volume prune -f
```

## 📊 Monitoring

```powershell
# Container status
docker ps

# Resource usage
docker stats

# Disk usage
docker system df

# Service logs
docker logs ms-user-service
docker logs -f ms-activity-service
```

## 🔧 Individual Services

```powershell
# Restart single service
docker restart ms-user-service

# Rebuild single service
docker-compose build user-service
docker-compose up -d user-service

# View service logs
docker logs ms-user-service

# Access service shell
docker exec -it ms-user-service sh
```

## 🎯 Quick Reference

| Action | Command |
|--------|---------|
| Full rebuild | `.\docker-rebuild.ps1` |
| Start | `docker-compose up -d` |
| Stop | `docker-compose down` |
| Restart | `docker-compose restart` |
| Logs | `docker-compose logs -f` |
| Status | `docker ps` |
| Test | `.\test-api-health.ps1` |
| Clean | `docker system prune -af` |

---

**For detailed guide, see: DOCKER_REBUILD_GUIDE.md**
