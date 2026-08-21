#!/data/data/com.termux/files/usr/bin/bash
# Dual-Node Mobile Cluster Launcher for Termux
# Usage:
#   bash scripts/start_dual_node.sh node1 [SECONDARY_PHONE_IP]
#   bash scripts/start_dual_node.sh node2 [PRIMARY_PHONE_IP]

ROLE="${1:-node1}"
REMOTE_IP="${2:-192.168.1.140}"   # ค่า default = IP จริงของ Phone 2 (Y7 2019 ตัวที่ 2)
PROJECT_PATH="/data/data/com.termux/files/home/uni-activity"

cd "$PROJECT_PATH" || { echo "❌ Cannot access project directory $PROJECT_PATH"; exit 1; }

echo "================================================="
echo "📱 UNI-ACTIVITY DUAL-NODE CLUSTER LAUNCHER"
echo "Role: $ROLE | Remote Peer IP: $REMOTE_IP"
echo "================================================="

if [ "$ROLE" = "node1" ]; then
    echo "🚀 Starting Node 1 (Master Gateway & DB Node)..."

    # 1. Start PostgreSQL
    echo "🐘 Checking PostgreSQL..."
    pg_ctl -D "$PREFIX/var/lib/postgresql" status > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        pg_ctl -D "$PREFIX/var/lib/postgresql" start
        sleep 2
    fi
    echo "✅ PostgreSQL running on port 5432"

    # 2. Start Valkey datastore (drop-in แทน Redis / Dragonfly)
    echo "⚡ Checking Valkey..."
    RPW=$(awk -F= '/^REDIS_PASSWORD=/{print $2}' .env | tr -d '"\r')
    if ! (echo > /dev/tcp/127.0.0.1/6379) 2>/dev/null; then
        valkey-server --daemonize yes --port 6379 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-data" --dbfilename dump.rdb --pidfile "$HOME/valkey-data/valkey6379.pid"
        sleep 1
    fi
    echo "✅ Valkey running on port 6379 (sessions/cache)"
    if ! (echo > /dev/tcp/127.0.0.1/6380) 2>/dev/null; then
        mkdir -p "$HOME/valkey-queue-data"
        valkey-server --daemonize yes --port 6380 --bind 0.0.0.0 --requirepass "$RPW" --dir "$HOME/valkey-queue-data" --dbfilename dump.rdb --pidfile "$HOME/valkey-queue-data/valkey6380.pid"
        sleep 1
    fi
    echo "✅ Valkey running on port 6380 (queue)"

    # 3. Start Laravel Reverb (WebSocket)
    echo "📡 Starting Laravel Reverb..."
    pkill -f "artisan reverb:start" 2>/dev/null
    nohup php artisan reverb:start --host=0.0.0.0 --port=8080 > /dev/null 2>&1 &
    sleep 1
    echo "✅ Reverb running on port 8080"

    # 4. Start Local Laravel Web Workers (Node 1) — 3 parallel artisan serve processes
    # NOTE: Octane (swoole/roadrunner/frankenphp) ไม่มีใน Termux repo สำหรับ PHP 8.5.1 —
    # ใช้ artisan serve หลาย process แทน (แต่ละ process จัดการ request ได้พร้อมกัน 1 ตัว)
    echo "⚡ Starting Laravel Web Workers (Node 1)..."
    for port in 8000 8002 8003; do
        pkill -f "artisan serve.*$port" 2>/dev/null
        if ! ss -tln 2>/dev/null | grep -q ":$port "; then
            setsid nohup php artisan serve --host=0.0.0.0 --port=$port > serve-$port.log 2>&1 < /dev/null &
        fi
    done
    sleep 2
    echo "✅ Web Workers running on ports 8000, 8002, 8003"

    # 4b. Start Web-Worker Watchdog (auto-restart if Android OOM killer reaps workers)
    echo "🛡️ Starting Web-Worker Watchdog..."
    pkill -f "watch_web_workers[.]sh" 2>/dev/null
    setsid bash "$PROJECT_PATH/../watch_web_workers.sh" > /dev/null 2>&1 < /dev/null &
    sleep 1
    echo "✅ Watchdog active (restarts dead workers on :8000/:8002/:8003 within ~12s)"

    # 5. Start Dual-Node Nginx Load Balancer
    echo "⚖️ Configuring & Starting Nginx Cluster Load Balancer..."
    pkill -x nginx 2>/dev/null
    # Generate dynamic lb config with remote IP
    sed "s/192.168.1.223/$REMOTE_IP/g" docker/lb.dual-node.conf > "$PREFIX/etc/nginx/nginx.conf"
    nginx
    echo "✅ Nginx Load Balancer running on port 8088 (routing to Node 1 & Node 2)"

    echo "================================================="
    echo "🎉 Node 1 Cluster Gateway is LIVE at http://$(ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1' | head -n1 | awk '{print $2}'):8088"
    echo "================================================="

elif [ "$ROLE" = "node2" ]; then
    echo "🚀 Starting Node 2 (AI Processing & Worker Node)..."

    # 1. Start Python AI Microservice (FastAPI + InsightFace)
    # NOTE: ต้อง cd เข้า ai_service/ เพราะ server.py import `from liveness import ...`
    # แบบ relative — รัน `uvicorn server:app` จากโฟลเดอร์ ai_service จึงจะเจอ module
    # ใช้ --workers 1 (Y7 2019 RAM 2-3GB — แต่ละ worker โหลด InsightFace+YOLO หนักมาก)
    echo "🧠 Starting Python AI Microservice..."
    pkill -f "uvicorn.*8001" 2>/dev/null
    cd "$PROJECT_PATH/ai_service"
    nohup uvicorn server:app --host 0.0.0.0 --port 8001 --workers ${AI_WORKERS:-1} > /dev/null 2>&1 &
    cd "$PROJECT_PATH"
    sleep 2
    echo "✅ Python AI Service running on port 8001"

    # 2. Start Laravel Web Workers (Node 2) — 3 parallel artisan serve processes
    echo "⚡ Starting Laravel Web Workers (Node 2)..."
    for port in 8000 8002 8003; do
        pkill -f "artisan serve.*$port" 2>/dev/null
        if ! ss -tln 2>/dev/null | grep -q ":$port "; then
            setsid nohup php artisan serve --host=0.0.0.0 --port=$port > serve-$port.log 2>&1 < /dev/null &
        fi
    done
    sleep 2
    echo "✅ Web Workers running on ports 8000, 8002, 8003"

    # 2b. Start Web-Worker Watchdog (auto-restart if Android OOM killer reaps workers)
    echo "🛡️ Starting Web-Worker Watchdog..."
    pkill -f "watch_web_workers[.]sh" 2>/dev/null
    setsid bash "$PROJECT_PATH/../watch_web_workers.sh" > /dev/null 2>&1 < /dev/null &
    sleep 1
    echo "✅ Watchdog active (restarts dead workers on :8000/:8002/:8003 within ~12s)"

    # 3. Start High-Priority Queue Worker for AI & Line Notifications
    echo "📬 Starting Background Queue Worker..."
    pkill -f "artisan queue:work" 2>/dev/null
    nohup php artisan queue:work --queue=ai,notifications,exports,line-notifications,sync,stats,images,default --tries=3 --timeout=60 --sleep=3 > /dev/null 2>&1 &
    echo "✅ Queue Worker active"

    echo "================================================="
    echo "🎉 Node 2 Worker & AI Node is ACTIVE and linked to Master!"
    echo "================================================="
fi
