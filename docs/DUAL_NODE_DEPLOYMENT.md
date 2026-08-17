# 📱 Dual-Node Mobile Cluster Deployment Guide

คู่มือการตั้งค่าระบบ **Active-Active Load Balancing** และการกระจายงานสำหรับ **มือถือ 2 เครื่อง (Termux Server)**

---

## 🏗️ โทโพโลยีของระบบ (Cluster Architecture)

```
                    ┌───────────────────────────────┐
                    │      Cloudflare / Ngrok       │
                    └───────────────┬───────────────┘
                                    │
                       (Port 8088 Gateway Port)
                                    │
                                    ▼
       ┌────────────────────────────────────────────────────────┐
       │             📱 Phone 1: Master Gateway & DB            │
       │                   (IP: 192.168.1.222)                  │
       │                                                        │
       │  • Nginx Load Balancer (Port 8088) ───► least_conn     │
       │  • PostgreSQL 16 (Port 5432 - Centralized DB)          │
       │  • Redis / Dragonfly (Port 6379 - Shared Cache/Lock)   │
       │  • Laravel Octane Instance #1 (Port 8000)              │
       │  • Laravel Reverb (Port 8080 - WebSocket Server)       │
       └────────────────────────────┬───────────────────────────┘
                                    │
                           (Local WiFi Network)
                                    │
                                    ▼
       ┌────────────────────────────────────────────────────────┐
       │             📱 Phone 2: Compute & AI Worker            │
       │                   (IP: 192.168.1.223)                  │
       │                                                        │
       │  • Laravel Octane Instance #2 (Port 8000)              │
       │  • Python AI Microservice (Port 8001 - FastAPI)        │
       │    (InsightFace + OpenCV Passive Liveness)             │
       │  • Laravel Queue Workers (High-Throughput Jobs)        │
       └────────────────────────────────────────────────────────┘
```

---

## 🚀 ขั้นตอนการติดตั้งและรันระบบ

### 1. การเตรียมความพร้อมบนมือถือทั้ง 2 เครื่อง
เชื่อมต่อมือถือทั้ง 2 เครื่องเข้า WiFi วงเดียวกัน (เช่น `192.168.1.xxx`) หรือผ่าน Tailscale VPN

### 2. รันบน Phone 1 (Master Node)
```bash
cd ~/uni-activity
git pull origin main

# สตาร์ท Node 1 โดยระบุ IP ของ Phone 2
bash scripts/start_dual_node.sh node1 192.168.1.223
```
- Nginx Load Balancer จะเปิดให้บริการที่พอร์ต `8088`
- กระจาย Request ไปยัง `127.0.0.1:8000` (Phone 1) และ `192.168.1.223:8000` (Phone 2) อัตโนมัติ

### 3. รันบน Phone 2 (Worker & AI Node)
บน Phone 2 ตั้งค่า `.env` ให้ชี้ Database และ Redis ไปที่ Phone 1:
```ini
DB_HOST=192.168.1.222
DB_PORT=5432
REDIS_HOST=192.168.1.222
REDIS_PORT=6379
```
จากนั้นสั่งรัน Worker และ AI Service:
```bash
cd ~/uni-activity
git pull origin main

# สตาร์ท Node 2
bash scripts/start_dual_node.sh node2 192.168.1.222
```

---

## 🐳 การรันบน Docker Compose (Local / Staging)

ในระดับ Docker Compose ระบบจะจำลองโครงสร้าง 2 Nodes อัตโนมัติผ่าน `app1` และ `app2`:
```bash
# รันคลัสเตอร์ Active-Active พร้อม Nginx Load Balancer
docker compose up -d --build
```
- Nginx Load Balancer: `http://localhost:8000`
- Balance Requests: `app1:80` (Node 1) และ `app2:80` (Node 2) แบบ `least_conn`
