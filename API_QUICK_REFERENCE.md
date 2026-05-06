# 🚀 API Quick Reference Card

## 📍 Service Ports

| Service | Port | URL |
|---------|------|-----|
| User | 8001 | http://localhost:8001 |
| Activity | 8002 | http://localhost:8002 |
| Job | 8003 | http://localhost:8003 |
| Notification | 8004 | http://localhost:8004 |
| Audit | 8005 | http://localhost:8005 |
| Kong Gateway | 80 | http://localhost |
| Nginx | 8000 | http://localhost:8000 |
| Socket.io | 3000 | http://localhost:3000 |

## ⚡ Quick Test Commands

### Health Check All Services
```powershell
.\test-api-health.ps1
```

### Test Individual Service
```powershell
Invoke-WebRequest -Uri "http://localhost:8001/health" -UseBasicParsing
```

### Check Docker Status
```powershell
docker ps
```

## 🔑 Common API Calls

### Users
```bash
GET    http://localhost:8001/api/users
POST   http://localhost:8001/api/users
GET    http://localhost:8001/api/users/{id}
PUT    http://localhost:8001/api/users/{id}
DELETE http://localhost:8001/api/users/{id}
```

### Activities
```bash
GET  http://localhost:8002/api/activities
POST http://localhost:8002/api/activities
GET  http://localhost:8002/api/activities/{id}
POST http://localhost:8002/api/activities/{id}/register
POST http://localhost:8002/api/activities/{id}/checkin
```

### Jobs
```bash
GET  http://localhost:8003/api/jobs
POST http://localhost:8003/api/jobs
GET  http://localhost:8003/api/jobs/{id}
POST http://localhost:8003/api/jobs/{id}/apply
```

## 🛠️ Docker Commands

```powershell
# View logs
docker logs ms-user-service

# Restart service
docker restart ms-user-service

# Access shell
docker exec -it ms-user-service sh

# Restart all
docker-compose restart

# Stop all
docker-compose down

# Start all
docker-compose up -d
```

## 📊 Monitoring URLs

- RabbitMQ: http://localhost:15672 (guest/guest)
- Ngrok: http://localhost:4040
- Kong Admin: http://localhost:8444

## ✅ Status: ALL SYSTEMS OPERATIONAL

**For detailed documentation, see API_TESTING_GUIDE.md**
