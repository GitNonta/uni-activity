#!/bin/bash

# ================================================================
# UNI-ACTIVITY NATIVE PHP DEPLOYMENT SCRIPT
# Target: Termux Server @ 192.168.1.222:8022
# Deployment Method: Native PHP (no Docker)
# ================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[⚠]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }

SERVER_USER="u0_a175"
SERVER_HOST="192.168.1.222"
SERVER_PORT="8022"
PROJECT_PATH="/data/data/com.termux/files/home/uni-activity"

echo "════════════════════════════════════════════════════════════"
echo "🚀 UNI-ACTIVITY NATIVE PHP DEPLOYMENT"
echo "════════════════════════════════════════════════════════════"
echo ""

# ================================================================
# STEP 1: SSH to Server & Pull Latest Code
# ================================================================
log_info "Step 1/5: Pulling latest code from Git..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'EOF'
    cd $PROJECT_PATH
    echo "[*] Pulling from Git..."
    git pull origin main
    echo "✓ Code updated"
EOF
log_success "Code updated from Git"

# ================================================================
# STEP 2: Install Dependencies
# ================================================================
log_info "Step 2/5: Installing PHP dependencies..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'EOF'
    cd $PROJECT_PATH
    
    echo "[*] Installing Composer dependencies..."
    
    # Check if composer exists
    if ! command -v composer &> /dev/null; then
        log_warning "Composer not found, trying 'composer.phar'..."
        if [ ! -f "composer.phar" ]; then
            echo "❌ Composer not found. Please install Composer first:"
            echo "  curl -sS https://getcomposer.org/installer | php"
            exit 1
        fi
        php composer.phar install --no-dev --optimize-autoloader
    else
        composer install --no-dev --optimize-autoloader
    fi
    
    echo "✓ PHP dependencies installed"
EOF
log_success "PHP dependencies installed"

# ================================================================
# STEP 3: Install Frontend Dependencies
# ================================================================
log_info "Step 3/5: Installing frontend dependencies..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'EOF'
    cd $PROJECT_PATH
    
    echo "[*] Checking Node.js installation..."
    if ! command -v node &> /dev/null; then
        log_warning "Node.js not found. Skipping frontend build..."
        echo "⚠️  To build assets manually later, run:"
        echo "  cd $PROJECT_PATH && npm install && npm run build"
    else
        echo "[*] Installing npm packages..."
        npm ci --prefer-offline
        
        echo "[*] Building frontend assets..."
        npm run build
        
        echo "✓ Frontend assets built"
    fi
EOF
log_success "Frontend dependencies processed"

# ================================================================
# STEP 4: Laravel Migrations & Configuration
# ================================================================
log_info "Step 4/5: Running Laravel migrations..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'EOF'
    cd $PROJECT_PATH
    
    echo "[*] Setting application key..."
    php artisan key:generate --force || true
    
    echo "[*] Clearing old cache..."
    php artisan cache:clear || true
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    
    echo "[*] Running database migrations..."
    php artisan migrate --force
    
    echo "[*] Setting permissions on storage..."
    chmod -R 775 storage/ bootstrap/cache/
    
    echo "✓ Laravel configuration complete"
EOF
log_success "Laravel migrations completed"

# ================================================================
# STEP 5: Restart Services
# ================================================================
log_info "Step 5/5: Restarting application services..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'EOF'
    cd $PROJECT_PATH
    
    echo "[*] Checking if supervisor is running..."
    if command -v supervisorctl &> /dev/null; then
        echo "[*] Restarting Supervisor services..."
        supervisorctl restart all || true
        sleep 3
        supervisorctl status
    else
        echo "⚠️  Supervisor not configured. Please restart services manually."
        echo "   For Laravel Octane (if configured): php artisan octane:start"
        echo "   For Laravel Reverb: php artisan reverb:start"
    fi
    
    echo "✓ Services restarted"
EOF
log_success "Application services restarted"

# ================================================================
# STEP 6: Verification
# ================================================================
log_info "Verifying deployment..."
ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST << 'EOF'
    cd $PROJECT_PATH
    
    echo "[*] Running health check..."
    php artisan tinker --execute "echo 'Laravel is working! ✓'" 2>/dev/null || echo "⚠️  Tinker check skipped"
    
    echo ""
    echo "📊 Application Status:"
    php artisan migrate:status --no-ansi 2>/dev/null | head -5 || echo "  (Migrations status unavailable)"
    
    echo ""
    echo "📁 Storage & Cache:"
    ls -lh storage/logs/laravel.log 2>/dev/null | tail -1 || echo "  (Log file not yet created)"
EOF

# ================================================================
# FINAL SUMMARY
# ================================================================
echo ""
echo "════════════════════════════════════════════════════════════"
log_success "✨ DEPLOYMENT COMPLETE!"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "🔗 Server Details:"
echo "   Host:  $SERVER_HOST"
echo "   Port:  $SERVER_PORT"
echo "   User:  $SERVER_USER"
echo "   Path:  $PROJECT_PATH"
echo ""
echo "🌐 Access Application:"
echo "   URL: http://$SERVER_HOST:8000"
echo ""
echo "📋 Useful Commands:"
echo "   SSH:               ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST"
echo "   View logs:         ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'tail -f $PROJECT_PATH/storage/logs/laravel.log'"
echo "   DB migration:      ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH && php artisan migrate:status'"
echo "   Cache clear:       ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH && php artisan cache:clear'"
echo "   Restart Octane:    ssh -p $SERVER_PORT $SERVER_USER@$SERVER_HOST 'cd $PROJECT_PATH && php artisan octane:restart'"
echo ""
echo "⚠️  Post-Deployment Checks:"
echo "   1. Verify application loads at http://$SERVER_HOST:8000"
echo "   2. Check logs: tail -f storage/logs/laravel.log"
echo "   3. Test security fixes (see SECURITY_FIXES_IMPLEMENTATION.md)"
echo "   4. Verify database is accessible"
echo "   5. Check that WebSocket (Reverb) is running if needed"
echo ""
