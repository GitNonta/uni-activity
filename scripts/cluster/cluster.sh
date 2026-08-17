#!/usr/bin/env bash
# =================================================================
# UNI-ACTIVITY DISTRIBUTED CLUSTER UNIFIED ORCHESTRATOR
# Single command to inspect, start, stop, restart, health check, and deploy the entire cluster
# =================================================================

set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$DIR"

COLOR_GREEN="\033[0;32m"
COLOR_CYAN="\033[0;36m"
COLOR_YELLOW="\033[1;33m"
COLOR_RED="\033[0;31m"
COLOR_RESET="\033[0m"

function print_header() {
    echo -e "${COLOR_CYAN}==================================================================${COLOR_RESET}"
    echo -e "${COLOR_CYAN}   UNI-ACTIVITY DISTRIBUTED CLUSTER CONTROL ORCHESTRATOR          ${COLOR_RESET}"
    echo -e "${COLOR_CYAN}==================================================================${COLOR_RESET}"
}

function show_status() {
    php artisan cluster:status "$@"
}

function check_health() {
    php artisan cluster:health "$@"
}

function start_cluster() {
    print_header
    echo -e "${COLOR_GREEN}🚀 Starting Uni-Activity Cluster services...${COLOR_RESET}"

    # 1. Start Priority Queue Worker in background if not running
    if ! pgrep -f "artisan queue:work" > /dev/null 2>&1; then
        echo -e "  ⚙️  Starting Redis Priority Queue Workers..."
        nohup php artisan queue:work redis --queue=ai,notifications,exports,cassandra,default --tries=3 --timeout=120 --sleep=1 > storage/logs/queue.log 2>&1 &
        echo -e "  ${COLOR_GREEN}✓${COLOR_RESET} Queue Workers started (PID: $!)"
    else
        echo -e "  ${COLOR_YELLOW}ℹ${COLOR_RESET} Queue Workers already running."
    fi

    # 2. Start Laravel Task Scheduler in background if not running
    if ! pgrep -f "artisan schedule:work" > /dev/null 2>&1; then
        echo -e "  ⚙️  Starting Task Scheduler..."
        nohup php artisan schedule:work > storage/logs/scheduler.log 2>&1 &
        echo -e "  ${COLOR_GREEN}✓${COLOR_RESET} Task Scheduler started (PID: $!)"
    else
        echo -e "  ${COLOR_YELLOW}ℹ${COLOR_RESET} Task Scheduler already running."
    fi

    # 3. Start Laravel Reverb WebSocket Server if not running
    if ! pgrep -f "artisan reverb:start" > /dev/null 2>&1; then
        echo -e "  ⚙️  Starting Laravel Reverb WebSocket Server..."
        nohup php artisan reverb:start --host=0.0.0.0 --port=8080 > storage/logs/reverb.log 2>&1 &
        echo -e "  ${COLOR_GREEN}✓${COLOR_RESET} Reverb WebSocket Server started (PID: $!)"
    else
        echo -e "  ${COLOR_YELLOW}ℹ${COLOR_RESET} Reverb Server already running."
    fi

    # 4. Start Laravel Octane (Swoole) if not running
    if ! pgrep -f "artisan octane:start" > /dev/null 2>&1; then
        echo -e "  ⚙️  Starting Laravel Octane (Swoole Engine)..."
        nohup php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=auto > storage/logs/octane.log 2>&1 &
        echo -e "  ${COLOR_GREEN}✓${COLOR_RESET} Laravel Octane Engine started (PID: $!)"
    else
        echo -e "  ${COLOR_YELLOW}ℹ${COLOR_RESET} Laravel Octane Engine already running."
    fi

    echo -e "\n${COLOR_GREEN}✓ Cluster startup sequence completed.${COLOR_RESET}\n"
    show_status
}

function stop_cluster() {
    print_header
    echo -e "${COLOR_YELLOW}🛑 Stopping Uni-Activity Cluster services...${COLOR_RESET}"

    pkill -f "artisan octane:start" || true
    pkill -f "artisan reverb:start" || true
    pkill -f "artisan queue:work" || true
    pkill -f "artisan schedule:work" || true

    echo -e "${COLOR_GREEN}✓ All cluster background workers stopped successfully.${COLOR_RESET}"
}

function deploy_cluster() {
    print_header
    echo -e "${COLOR_CYAN}🔄 Deploying latest updates across Cluster...${COLOR_RESET}"

    echo -e "  1. Fetching latest Git changes..."
    git fetch origin
    git reset --hard origin/main

    echo -e "  2. Optimizing Laravel Cache & Configurations..."
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
    php artisan view:cache

    echo -e "  3. Running Database Migrations..."
    php artisan migrate --force

    echo -e "  4. Reloading Octane & Queue Workers..."
    if pgrep -f "artisan octane:start" > /dev/null 2>&1; then
        php artisan octane:reload || true
    fi
    php artisan queue:restart || true

    echo -e "\n${COLOR_GREEN}✓ Cluster deployment completed successfully!${COLOR_RESET}\n"
    show_status
}

# Main Command Dispatcher
case "$1" in
    status|info)
        shift
        show_status "$@"
        ;;
    health|check)
        shift
        check_health "$@"
        ;;
    start)
        start_cluster
        ;;
    stop)
        stop_cluster
        ;;
    restart)
        stop_cluster
        sleep 2
        start_cluster
        ;;
    deploy)
        deploy_cluster
        ;;
    *)
        echo -e "${COLOR_CYAN}Uni-Activity Cluster Orchestrator CLI${COLOR_RESET}"
        echo -e "Usage: $0 {status|health|start|stop|restart|deploy} [options]"
        echo -e ""
        echo -e "Commands:"
        echo -e "  status   - View full cluster topology, health & metrics table"
        echo -e "  health   - Run automated health probe check (exit code 0/1)"
        echo -e "  start    - Start all cluster background workers (Octane, Reverb, Queues)"
        echo -e "  stop     - Gracefully stop all cluster workers"
        echo -e "  restart  - Restart all cluster workers"
        echo -e "  deploy   - Git pull, clear/prewarm cache, migrate, and reload workers"
        exit 1
        ;;
esac
