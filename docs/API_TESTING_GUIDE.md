# 🔌 API Testing Guide - Docker Microservices

## 📊 System Architecture Overview

Your University Activity System is running as a **microservices architecture** with:

### Services Running:
| Service | Container | Port | Status | Purpose |
|---------|-----------|------|--------|---------|
| **BFF** | ms-bff | Internal | ✅ Healthy | Backend for Frontend |
| **User Service** | ms-user-service | 8001 | ✅ Healthy | User management |
| **Activity Service** | ms-activity-service | 8002 | ✅ Healthy | Activity management |
| **Job Service** | ms-job-service | 8003 | ✅ Healthy | Job listings |
| **Notification Service** | ms-notification-service | 3001, 8004 | ✅ Healthy | Notifications |
| **Audit Service** | ms-audit-service | 8005 | ✅ Healthy | Audit logging |
| **Kong Gateway** | ms-kong | 80, 8444 | ✅ Running | API Gateway |
| **Socket Server** | laravel-socketserver | 3000 | ✅ Running | Real-time WebSocket |
| **Nginx** | laravel-nginx | 8000 | ✅ Running | Web server |

### Databases:
| Database | Container | Port | Type | Purpose |
|----------|-----------|------|------|---------|
| User DB | ms-user-db | 33061 | MySQL 8.0 | User data |
| Activity DB | ms-activity-db | 33062 | MySQL 8.0 | Activity data |
| Job DB | ms-job-db | 33063 | MySQL 8.0 | Job data |
| Audit DB | ms-audit-db | 33064 | MySQL 8.0 | Audit logs |
| Notification DB | ms-notification-db | 27017 | MongoDB 7.0 | Chat/notifications |
| Monolith DB | ms-monolith-db | Internal | MySQL 8.0 | Legacy data |

### Infrastructure:
| Service | Container | Port | Purpose |
|---------|-----------|------|---------|
| Redis | ms-redis | 6380 | Caching & sessions |
| RabbitMQ | ms-rabbitmq | 5672, 15672 | Message broker |
| Ngrok | laravel-ngrok | 4040 | Public tunneling |

---

## 🧪 API Health Check Tests

### Test All Microservices Health

```powershell
# User Service
Invoke-WebRequest -Uri "http://localhost:8001/health" -UseBasicParsing

# Activity Service
Invoke-WebRequest -Uri "http://localhost:8002/health" -UseBasicParsing

# Job Service
Invoke-WebRequest -Uri "http://localhost:8003/health" -UseBasicParsing

# Notification Service
Invoke-WebRequest -Uri "http://localhost:8004/health" -UseBasicParsing

# Audit Service
Invoke-WebRequest -Uri "http://localhost:8005/health" -UseBasicParsing
```

**Expected Response:** `healthy` (200 OK)

---

## 🔑 API Endpoints by Service

### 1. User Service (Port 8001)

#### Health Check
```bash
GET http://localhost:8001/health
```

#### User Management
```bash
# Get all users
GET http://localhost:8001/api/users

# Get user by ID
GET http://localhost:8001/api/users/{id}

# Create user
POST http://localhost:8001/api/users
Content-Type: application/json
{
  "student_id": "6xxxxxxx",
  "name": "ชื่อนักศึกษา",
  "email": "student@example.com",
  "password": "password123"
}

# Update user
PUT http://localhost:8001/api/users/{id}

# Delete user
DELETE http://localhost:8001/api/users/{id}
```

### 2. Activity Service (Port 8002)

#### Health Check
```bash
GET http://localhost:8002/health
```

#### Activity Management
```bash
# Get all activities
GET http://localhost:8002/api/activities

# Get activity by ID
GET http://localhost:8002/api/activities/{id}

# Create activity
POST http://localhost:8002/api/activities
Content-Type: application/json
{
  "title": "ชื่อกิจกรรม",
  "description": "รายละเอียด",
  "activity_date": "2026-05-01",
  "start_time": "09:00:00",
  "end_time": "12:00:00",
  "location": "สถานที่",
  "max_participants": 100
}

# Register for activity
POST http://localhost:8002/api/activities/{id}/register

# Check-in to activity
POST http://localhost:8002/api/activities/{id}/checkin
```

### 3. Job Service (Port 8003)

#### Health Check
```bash
GET http://localhost:8003/health
```

#### Job Listings
```bash
# Get all jobs
GET http://localhost:8003/api/jobs

# Get job by ID
GET http://localhost:8003/api/jobs/{id}

# Create job listing
POST http://localhost:8003/api/jobs
Content-Type: application/json
{
  "title": "ตำแหน่งงาน",
  "description": "รายละเอียดงาน",
  "requirements": "คุณสมบัติ",
  "salary": "เงินเดือน",
  "location": "สถานที่ทำงาน"
}

# Apply for job
POST http://localhost:8003/api/jobs/{id}/apply

# Get job applications
GET http://localhost:8003/api/jobs/{id}/applications
```

### 4. Notification Service (Port 8004)

#### Health Check
```bash
GET http://localhost:8004/health
```

#### Notifications
```bash
# Get user notifications
GET http://localhost:8004/api/notifications

# Mark as read
POST http://localhost:8004/api/notifications/{id}/read

# Send notification
POST http://localhost:8004/api/notifications
Content-Type: application/json
{
  "user_id": 1,
  "title": "หัวข้อแจ้งเตือน",
  "message": "ข้อความ",
  "type": "info"
}
```

### 5. Audit Service (Port 8005)

#### Health Check
```bash
GET http://localhost:8005/health
```

#### Audit Logs
```bash
# Get audit logs
GET http://localhost:8005/api/audit-logs

# Get logs by user
GET http://localhost:8005/api/audit-logs/user/{userId}

# Get logs by action
GET http://localhost:8005/api/audit-logs/action/{action}

# Create audit log
POST http://localhost:8005/api/audit-logs
Content-Type: application/json
{
  "user_id": 1,
  "action": "login",
  "description": "User logged in",
  "ip_address": "127.0.0.1"
}
```

---

## 🌐 Kong API Gateway

Kong acts as the API Gateway routing requests to microservices.

### Kong Admin API
```bash
# Check Kong status
GET http://localhost:8444

# List services
GET http://localhost:8444/services

# List routes
GET http://localhost:8444/routes
```

### Access via Kong Gateway
```bash
# All API requests should go through Kong on port 80
GET http://localhost/api/users
GET http://localhost/api/activities
GET http://localhost/api/jobs
```

---

## 🔌 WebSocket / Real-time

### Socket.io Server (Port 3000)
```javascript
// Connect to socket server
const socket = io('http://localhost:3000');

// Listen for events
socket.on('activity-update', (data) => {
  console.log('Activity updated:', data);
});

// Emit events
socket.emit('join-activity', { activityId: 123 });
```

---

## 🧪 Testing with PowerShell

### Create Test Script
```powershell
# test-api.ps1

Write-Host "Testing Microservices Health..." -ForegroundColor Cyan

$services = @(
    @{Name="User Service"; Port=8001},
    @{Name="Activity Service"; Port=8002},
    @{Name="Job Service"; Port=8003},
    @{Name="Notification Service"; Port=8004},
    @{Name="Audit Service"; Port=8005}
)

foreach ($service in $services) {
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:$($service.Port)/health" -UseBasicParsing
        $status = [System.Text.Encoding]::UTF8.GetString($response.Content)
        Write-Host "✓ $($service.Name): $status" -ForegroundColor Green
    } catch {
        Write-Host "✗ $($service.Name): Failed" -ForegroundColor Red
    }
}
```

Run:
```powershell
.\test-api.ps1
```

---

## 📝 Testing with Postman

### Import Collection

Create a Postman collection with these requests:

1. **User Service**
   - GET Health Check
   - GET All Users
   - POST Create User
   - GET User by ID

2. **Activity Service**
   - GET Health Check
   - GET All Activities
   - POST Create Activity
   - POST Register Activity

3. **Job Service**
   - GET Health Check
   - GET All Jobs
   - POST Create Job
   - POST Apply Job

4. **Notification Service**
   - GET Health Check
   - GET Notifications
   - POST Send Notification

5. **Audit Service**
   - GET Health Check
   - GET Audit Logs

### Environment Variables
```json
{
  "user_service": "http://localhost:8001",
  "activity_service": "http://localhost:8002",
  "job_service": "http://localhost:8003",
  "notification_service": "http://localhost:8004",
  "audit_service": "http://localhost:8005",
  "kong_gateway": "http://localhost",
  "socket_server": "http://localhost:3000"
}
```

---

## 🔍 Monitoring & Debugging

### Check Container Logs
```powershell
# User Service logs
docker logs ms-user-service

# Activity Service logs
docker logs ms-activity-service

# Kong Gateway logs
docker logs ms-kong
```

### Check Container Status
```powershell
docker ps
```

### Access Container Shell
```powershell
docker exec -it ms-user-service sh
```

### RabbitMQ Management
```
http://localhost:15672
Username: guest
Password: guest
```

### Redis Commander
```
http://localhost:8081
```

### Ngrok Dashboard
```
http://localhost:4040
```

---

## 🚀 Quick API Test Commands

### Test All Services
```powershell
# Quick health check all services
8001, 8002, 8003, 8004, 8005 | ForEach-Object {
    $port = $_
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:$port/health" -UseBasicParsing
        Write-Host "Port $port : OK" -ForegroundColor Green
    } catch {
        Write-Host "Port $port : FAIL" -ForegroundColor Red
    }
}
```

### Test Kong Gateway
```powershell
Invoke-WebRequest -Uri "http://localhost:8444" -UseBasicParsing | Select-Object StatusCode
```

### Test Socket Server
```powershell
Invoke-WebRequest -Uri "http://localhost:3000" -UseBasicParsing
```

---

## ✅ API Status Summary

Based on current Docker containers:

| Component | Status | Notes |
|-----------|--------|-------|
| User Service | ✅ Healthy | Port 8001 responding |
| Activity Service | ✅ Healthy | Port 8002 responding |
| Job Service | ✅ Healthy | Port 8003 responding |
| Notification Service | ✅ Healthy | Ports 3001, 8004 |
| Audit Service | ✅ Healthy | Port 8005 responding |
| Kong Gateway | ✅ Running | Port 80, 8444 |
| Socket Server | ✅ Running | Port 3000 |
| Nginx | ✅ Running | Port 8000 |
| All Databases | ✅ Healthy | MySQL & MongoDB |
| Redis | ✅ Healthy | Port 6380 |
| RabbitMQ | ✅ Healthy | Ports 5672, 15672 |

---

## 🎯 Next Steps

1. **Test Individual Endpoints** - Use Postman or curl to test each API endpoint
2. **Check Authentication** - Verify JWT tokens or session-based auth
3. **Test Data Flow** - Create → Read → Update → Delete operations
4. **Monitor Performance** - Check response times and error rates
5. **Load Testing** - Use tools like Apache Bench or k6

---

**All microservices are healthy and ready for API testing! 🎉**
