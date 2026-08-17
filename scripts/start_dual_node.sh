#!/data/data/com.termux/files/usr/bin/bash
# Dual-Node Mobile Cluster Launcher for Termux
# Usage:
#   bash scripts/start_dual_node.sh node1 [SECONDARY_PHONE_IP]
#   bash scripts/start_dual_node.sh node2 [PRIMARY_PHONE_IP]

ROLE="${1:-node1}"
REMOTE_IP="${2:-192.168.1.223}"
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

    # 2. Start Redis / Dragonfly
    echo "⚡ Checking Redis..."
    if ! pgrep -x "redis-server" > /dev/null; then
        redis-server --daemonize yes --port 6379 --bind 0.0.0.0
        sleep 1
    fi
    echo "✅ Redis running on port 6379"

    # 3. Start Laravel Reverb (WebSocket)
    echo "📡 Starting Laravel Reverb..."
    pkill -f "artisan reverb:start" 2>/dev/null
    nohup php artisan reverb:start --host=0.0.0.0 --port=8080 > /dev/null 2>&1 &
    sleep 1
    echo "✅ Reverb running on port 8080"

    # 4. Start Local Laravel Octane Instance (Node 1 Worker)
    echo "⚡ Starting Laravel Octane (Node 1)..."
    pkill -f "artisan octane:start.*8000" 2>/dev/null
    nohup php artisan octane:start --server=swoole --host=127.0.0.1 --port=8000 --workers=2 > /dev/null 2>&1 &
    sleep 2
    echo "✅ Octane Worker 1 running on port 8000"

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
    echo "🧠 Starting Python AI Microservice..."
    pkill -f "uvicorn.*8001" 2>/dev/null
    nohup uvicorn ai_service.main:app --host 0.0.0.0 --port 8001 --workers 2 > /dev/null 2>&1 &
    sleep 2
    echo "✅ Python AI Service running on port 8001"

    # 2. Start Laravel Octane Instance (Node 2 Worker)
    echo "⚡ Starting Laravel Octane (Node 2)..."
    pkill -f "artisan octane:start.*8000" 2>/dev/null
    nohup php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=2 > /dev/null 2>&1 &
    sleep 2
    echo "✅ Octane Worker 2 running on port 8000"

    # 3. Start High-Priority Queue Worker for AI & Line Notifications
    echo "📬 Starting Background Queue Worker..."
    pkill -f "artisan queue:work" 2>/dev/null
    nohup php artisan queue:work --queue=high,default --tries=3 --timeout=60 --sleep=3 > /dev/null 2>&1 &
    echo "✅ Queue Worker active"

    echo "================================================="
    echo "🎉 Node 2 Worker & AI Node is ACTIVE and linked to Master!"
    echo "================================================="
fi
