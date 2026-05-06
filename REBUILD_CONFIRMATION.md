# ⚠️ Docker Rebuild Confirmation

## What Will Happen

Running `.\docker-rebuild.ps1` will:

1. ✅ Stop all 18 running containers
2. ✅ Remove all containers
3. ✅ Remove all Docker images (~5-10GB)
4. ✅ Remove all volumes (⚠️ **DATABASE DATA WILL BE LOST**)
5. ✅ Clear Docker cache
6. ✅ Build fresh images (5-15 minutes)
7. ✅ Start all containers

## ⚠️ Important Warnings

### Data Loss
- All database data will be deleted
- Redis cache will be cleared
- MongoDB data will be removed
- Any uploaded files in volumes will be lost

### Downtime
- Services will be unavailable for 10-20 minutes
- Active users will be disconnected
- API requests will fail during rebuild

### Resources Required
- 10GB+ free disk space
- 4GB+ RAM
- Stable internet connection
- 15-20 minutes of time

## 💾 Backup First (Optional)

If you want to keep data:

```powershell
# Backup databases
docker exec ms-user-db mysqldump -u root -proot uni_activity > backup_user.sql
docker exec ms-activity-db mysqldump -u root -proot uni_activity > backup_activity.sql
docker exec ms-job-db mysqldump -u root -proot uni_activity > backup_job.sql

# Backup MongoDB
docker exec ms-notification-db mongodump --out /backup
```

## ✅ Ready to Proceed?

### Option 1: Full Rebuild (Recommended)
```powershell
.\docker-rebuild.ps1
```

### Option 2: Soft Restart (No data loss)
```powershell
docker-compose restart
```

### Option 3: Update Without Rebuild
```powershell
docker-compose up -d
```

## 🎯 When to Use Each Option

### Use Full Rebuild When:
- Dockerfile changes not taking effect
- Build cache causing issues
- Starting completely fresh
- Major dependency updates

### Use Soft Restart When:
- Just need to restart services
- Config changes only
- Want to keep all data

### Use Update When:
- Code changes only
- Minor updates
- Quick refresh

---

**Choose wisely! Full rebuild = fresh start but loses data.**
