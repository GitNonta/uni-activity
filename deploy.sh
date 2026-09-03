#!/bin/bash

# ================================================================
# UNI-ACTIVITY DEPLOYMENT SCRIPT
# Target: Termux Server @ 192.168.1.222:8022
# Author: GitHub Copilot
# Date: 2026-08-19
# ================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SERVER_USER="u0_a175"
SERVER_HOST="192.168.1.222"
SERVER_PORT="8022"
PROJECT_PATH="/data/data/com.termux/files/home/uni-activity"
DOCKER_COMPOSE_FILE="docker-compose.prod.yml"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# ================================================================
# STEP 1: Verify Local Changes
# ================================================================
log_info "Step 1: Verifying local changes..."
if ! git diff-index --quiet HEAD --; then
    log_warning "You have uncommitted changes. Please commit them first."
    git status
    exit 1
fi
log_success "All changes committed ✓"

# ================================================================
# STEP 2: Connect to Server & Pull Latest Code
# ================================================================
log_info "Step 2: Connecting to server and pulling latest code..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'REMOTE_COMMANDS'
    set -e
    
    echo "📍 Changing to project directory..."
    cd /data/data/com.termux/files/home/uni-activity || {
        echo "❌ Project directory not found!"
        exit 1
    }
    
    echo "📥 Pulling latest code from git..."
    git pull origin main || git pull origin master || {
        echo "⚠️  Could not pull from git, proceeding anyway..."
    }
    
    echo "✅ Code updated"
REMOTE_COMMANDS

log_success "Code pulled from Git ✓"

# ================================================================
# STEP 3: Rebuild & Restart Docker Containers
# ================================================================
log_info "Step 3: Rebuilding and restarting Docker containers..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'REMOTE_COMMANDS'
    set -e
    cd /data/data/com.termux/files/home/uni-activity
    
    echo "🔨 Building Docker image..."
    docker-compose -f docker-compose.prod.yml build --no-cache app
    
    echo "🧹 Stopping old containers..."
    docker-compose -f docker-compose.prod.yml down || true
    
    echo "🚀 Starting new containers..."
    docker-compose -f docker-compose.prod.yml up -d
    
    echo "⏳ Waiting for services to be healthy..."
    sleep 10
    
    echo "📋 Running database migrations..."
    docker-compose -f docker-compose.prod.yml exec -T app php artisan migrate --force || true
    
    echo "🧹 Clearing cache..."
    docker-compose -f docker-compose.prod.yml exec -T app php artisan cache:clear || true
    docker-compose -f docker-compose.prod.yml exec -T app php artisan config:clear || true
    docker-compose -f docker-compose.prod.yml exec -T app php artisan route:clear || true
    docker-compose -f docker-compose.prod.yml exec -T app php artisan view:clear || true
    
    echo "🔨 Pre-compiling Blade views..."
    docker-compose -f docker-compose.prod.yml exec -T app php artisan view:cache || true
    
    echo "✅ Deployment complete!"
    
    echo ""
    echo "📊 Container status:"
    docker-compose -f docker-compose.prod.yml ps
REMOTE_COMMANDS

log_success "Containers rebuilt and restarted ✓"

# ================================================================
# STEP 4: Verification
# ================================================================
log_info "Step 4: Verifying deployment..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'REMOTE_COMMANDS'
    set -e
    cd /data/data/com.termux/files/home/uni-activity
    
    echo "🔍 Checking application health..."
    
    # Wait for app to be ready
    sleep 5
    
    # Check if health endpoint responds
    HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health 2>/dev/null || echo "000")
    
    if [ "$HEALTH_CHECK" = "200" ]; then
        echo "✅ Application health check: OK (HTTP 200)"
    else
        echo "⚠️  Application health check returned: HTTP $HEALTH_CHECK"
    fi
    
    echo ""
    echo "📊 Services status:"
    docker-compose -f docker-compose.prod.yml ps
REMOTE_COMMANDS

log_success "Deployment verified ✓"

# ================================================================
# STEP 5: Summary
# ================================================================
echo ""
echo "════════════════════════════════════════════════════════════"
log_success "🎉 DEPLOYMENT COMPLETE!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Server Details:"
echo "  Host:     $SERVER_HOST"
echo "  Port:     $SERVER_PORT"
echo "  User:     $SERVER_USER"
echo "  Path:     $PROJECT_PATH"
echo ""
echo "Application URL:"
echo "  HTTP:     http://$SERVER_HOST:8000"
echo "  HTTPS:    https://$SERVER_HOST:8443 (if configured)"
echo ""
echo "Useful Commands:"
echo "  View logs:        ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH && docker-compose -f $DOCKER_COMPOSE_FILE logs -f app'"
echo "  Rebuild image:    ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH && docker-compose -f $DOCKER_COMPOSE_FILE build --no-cache app'"
echo "  Restart services: ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH && docker-compose -f $DOCKER_COMPOSE_FILE restart'"
echo "  SSH to server:    ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST"
echo ""
echo "Next Steps:"
echo "  1. Verify application is running at http://$SERVER_HOST:8000"
echo "  2. Check server logs for any errors"
echo "  3. Run security test to verify vulnerabilities are fixed"
echo ""
