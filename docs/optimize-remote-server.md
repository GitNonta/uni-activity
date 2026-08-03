# 🚀 Optimization Plan สำหรับ Server 192.168.1.222

## 📊 Current Status
- **IP:** 192.168.1.222
- **Ping:** 12ms (excellent)
- **Services:**
  - PostgreSQL (5432) ✅
  - Redis (6379) ✅
  - Reverb WebSocket (8080) ✅
  - Reverb Internal (8082) ✅

---

## ⚡ Optimization Options

### 1. **PostgreSQL Database Optimization**
```sql
-- Connection pooling
max_connections = 200
shared_buffers = 256MB
effective_cache_size = 1GB
work_mem = 16MB
maintenance_work_mem = 128MB

-- Query performance
random_page_cost = 1.1
effective_io_concurrency = 200

-- WAL & Checkpoints
wal_buffers = 16MB
checkpoint_completion_target = 0.9
```

### 2. **Redis Cache Optimization**
```conf
# Memory management
maxmemory 512mb
maxmemory-policy allkeys-lru

# Persistence (disable for cache-only)
save ""
appendonly no

# Network
tcp-backlog 511
timeout 0
tcp-keepalive 300

# Performance
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
```

### 3. **Reverb WebSocket Optimization**
```env
# Scaling
REVERB_SCALING_ENABLED=true
REVERB_SERVER_WORKERS=4

# Memory
REVERB_MAX_REQUEST_SIZE=10000

# Keep-alive
REVERB_PING_INTERVAL=30
REVERB_TIMEOUT=60
```

### 4. **Network Optimization**
- Enable TCP BBR congestion control
- Increase socket buffer sizes
- Enable connection pooling
- Configure firewall rules

### 5. **Update Laravel .env to use this server**
```env
DB_HOST=192.168.1.222
REDIS_HOST=192.168.1.222
REVERB_HOST=192.168.1.222
REVERB_INTERNAL_HOST=192.168.1.222
```

---

## 🎯 Recommended Action

**Option A: Update Laravel to use this optimized server**
- Change .env to connect to 192.168.1.222
- Test connections
- Run migrations if needed

**Option B: Optimize the remote server directly**
- Need SSH access to 192.168.1.222
- Tune PostgreSQL config
- Tune Redis config
- Configure system-level optimizations

**Option C: Both**
- Update Laravel config
- Optimize remote server
- Maximum performance boost

---

## ⚠️ Note
Remote server optimization requires:
- SSH access to 192.168.1.222
- Root/sudo privileges
- Backup before making changes
