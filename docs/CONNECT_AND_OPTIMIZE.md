# 🚀 คำสั่งเข้า Server และ Optimize

## 📋 วิธีที่ 1: SSH เข้าไปรันทีละคำสั่ง (แนะนำ)

### 1. เข้า SSH
```bash
ssh -p 8022 u0_a175@192.168.1.222
```
**Password:** `2345678A`

---

### 2. เช็คสถานะปัจจุบัน
```bash
# System info
uname -a
free -h
df -h

# Check services
pgrep -l postgres
pgrep -l redis
netstat -tlnp | grep -E '5432|6379'
```

---

### 3. Optimize PostgreSQL
```bash
# Backup current config
cp $PREFIX/var/lib/postgresql/postgresql.conf $PREFIX/var/lib/postgresql/postgresql.conf.backup

# Apply optimized config
cat > $PREFIX/var/lib/postgresql/postgresql.conf << 'EOF'
# PostgreSQL Optimized Configuration
max_connections = 100
shared_buffers = 128MB
effective_cache_size = 512MB
maintenance_work_mem = 64MB
work_mem = 8MB
wal_buffers = 8MB
max_wal_size = 1GB
min_wal_size = 80MB
checkpoint_completion_target = 0.9
random_page_cost = 1.1
effective_io_concurrency = 200
log_min_duration_statement = 1000
log_line_prefix = '%t [%p]: '
synchronous_commit = off
fsync = on
full_page_writes = on
EOF

# Restart PostgreSQL
pg_ctl restart -D $PREFIX/var/lib/postgresql

# Verify
psql -U postgres -c "SHOW shared_buffers;"
psql -U postgres -c "SHOW max_connections;"
```

---

### 4. Optimize Redis
```bash
# Backup current config
cp $PREFIX/etc/redis.conf $PREFIX/etc/redis.conf.backup

# Apply optimized config
cat > $PREFIX/etc/redis.conf << 'EOF'
# Redis Optimized Configuration
bind 0.0.0.0
port 6379
tcp-backlog 511
timeout 0
tcp-keepalive 300
maxmemory 256mb
maxmemory-policy allkeys-lru
maxmemory-samples 5
save ""
appendonly no
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-lazy-flush yes
stop-writes-on-bgsave-error no
rdbcompression no
EOF

# Restart Redis
pkill redis-server
sleep 1
redis-server $PREFIX/etc/redis.conf &

# Verify
redis-cli ping
redis-cli config get maxmemory
redis-cli info memory
```

---

### 5. ตรวจสอบผลลัพธ์
```bash
# Check services
pgrep -l postgres
pgrep -l redis

# Check ports
netstat -tlnp | grep -E '5432|6379'

# Check memory
free -h
```

---

## 📋 วิธีที่ 2: รันแบบ One-liner (เร็วสุด)

เข้า SSH แล้วรันคำสั่งเดียวนี้:

```bash
ssh -p 8022 u0_a175@192.168.1.222
# Enter password: 2345678A

# จากนั้นวาง command นี้:
cp $PREFIX/var/lib/postgresql/postgresql.conf $PREFIX/var/lib/postgresql/postgresql.conf.backup && cat > $PREFIX/var/lib/postgresql/postgresql.conf << 'EOF'
max_connections = 100
shared_buffers = 128MB
effective_cache_size = 512MB
work_mem = 8MB
maintenance_work_mem = 64MB
wal_buffers = 8MB
checkpoint_completion_target = 0.9
random_page_cost = 1.1
synchronous_commit = off
EOF
pg_ctl restart -D $PREFIX/var/lib/postgresql && cp $PREFIX/etc/redis.conf $PREFIX/etc/redis.conf.backup && cat > $PREFIX/etc/redis.conf << 'EOF'
bind 0.0.0.0
port 6379
maxmemory 256mb
maxmemory-policy allkeys-lru
save ""
appendonly no
lazyfree-lazy-eviction yes
tcp-backlog 511
EOF
pkill redis-server ; sleep 1 ; redis-server $PREFIX/etc/redis.conf & && echo "✅ Optimization complete!" && pgrep -l postgres && pgrep -l redis
```

---

## 📋 วิธีที่ 3: ใช้ PuTTY (Windows)

### ติดตั้ง PuTTY
```powershell
winget install PuTTY.PuTTY
```

### เชื่อมต่อ
1. เปิด PuTTY
2. Host Name: `192.168.1.222`
3. Port: `8022`
4. Connection type: SSH
5. Click "Open"
6. Login as: `u0_a175`
7. Password: `2345678A`

จากนั้นรันคำสั่งใน **วิธีที่ 1** ข้างบน

---

## 🎯 หลังจาก Optimize เสร็จแล้ว

### Update Laravel Config
กลับมาที่ Windows PowerShell รัน:

```powershell
cd d:\projects\uni-activity

# Backup current .env
Copy-Item .env .env.backup.local

# Use optimized config
Copy-Item .env.optimized .env

# Clear and recache
php artisan config:clear
php artisan cache:clear
php artisan config:cache

# Test connection
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit
```

---

## ✅ Verification Checklist

หลัง optimize ให้เช็คว่า:

- [ ] PostgreSQL รีสตาร์ทสำเร็จ (`pgrep postgres`)
- [ ] Redis รีสตาร์ทสำเร็จ (`pgrep redis`)
- [ ] Port 5432 เปิดอยู่ (`netstat -tlnp | grep 5432`)
- [ ] Port 6379 เปิดอยู่ (`netstat -tlnp | grep 6379`)
- [ ] Laravel เชื่อมต่อ database ได้ (`php artisan tinker`)
- [ ] Redis cache ทำงาน (`redis-cli ping`)

---

## 🔄 Rollback (ถ้าจำเป็น)

```bash
# Rollback PostgreSQL
cp $PREFIX/var/lib/postgresql/postgresql.conf.backup $PREFIX/var/lib/postgresql/postgresql.conf
pg_ctl restart -D $PREFIX/var/lib/postgresql

# Rollback Redis
cp $PREFIX/etc/redis.conf.backup $PREFIX/etc/redis.conf
pkill redis-server
redis-server $PREFIX/etc/redis.conf &
```

---

## 📞 Support

ถ้ามีปัญหา:
1. เช็ค logs: `tail -f $PREFIX/var/log/postgresql/*.log`
2. เช็ค Redis log: `redis-cli`
3. Rollback ตาม command ข้างบน

**Good luck! 🚀**
