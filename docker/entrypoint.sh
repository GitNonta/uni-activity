#!/bin/sh
set -e

APP_DIR=/var/www/html

# ── Ensure storage directory structure exists ──
mkdir -p "$APP_DIR/storage/app/public"
mkdir -p "$APP_DIR/storage/framework/cache/data"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/testing"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/logs"

# ── Create .env from environment variables if not present ──
if [ ! -f "$APP_DIR/.env" ]; then
    echo "APP_NAME=\"${APP_NAME:-Laravel}\"" > "$APP_DIR/.env"
    echo "APP_ENV=${APP_ENV:-production}" >> "$APP_DIR/.env"
    echo "APP_KEY=${APP_KEY:-}" >> "$APP_DIR/.env"
    echo "APP_DEBUG=${APP_DEBUG:-false}" >> "$APP_DIR/.env"
    echo "APP_URL=${APP_URL:-http://localhost}" >> "$APP_DIR/.env"
    echo "" >> "$APP_DIR/.env"
    echo "DB_CONNECTION=${DB_CONNECTION:-mysql}" >> "$APP_DIR/.env"
    echo "DB_HOST=${DB_HOST:-mysql}" >> "$APP_DIR/.env"
    echo "DB_PORT=${DB_PORT:-3306}" >> "$APP_DIR/.env"
    echo "DB_DATABASE=${DB_DATABASE:-laravel}" >> "$APP_DIR/.env"
    echo "DB_USERNAME=${DB_USERNAME:-root}" >> "$APP_DIR/.env"
    echo "DB_PASSWORD=${DB_PASSWORD:-}" >> "$APP_DIR/.env"
    echo "" >> "$APP_DIR/.env"
    echo "CACHE_DRIVER=${CACHE_DRIVER:-file}" >> "$APP_DIR/.env"
    echo "SESSION_DRIVER=${SESSION_DRIVER:-file}" >> "$APP_DIR/.env"
    echo "QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}" >> "$APP_DIR/.env"
    echo "" >> "$APP_DIR/.env"
    echo "REDIS_HOST=${REDIS_HOST:-redis}" >> "$APP_DIR/.env"
    echo "REDIS_PORT=${REDIS_PORT:-6379}" >> "$APP_DIR/.env"
    echo "" >> "$APP_DIR/.env"
    echo "MONGODB_HOST=${MONGODB_HOST:-mongodb}" >> "$APP_DIR/.env"
    echo "MONGODB_DATABASE=${MONGODB_DATABASE:-}" >> "$APP_DIR/.env"
fi

# ── Generate APP_KEY if missing ──
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    php artisan key:generate --force
fi

# ── Clear cached config so new env vars take effect ──
php artisan config:clear
php artisan route:clear
php artisan view:clear

# ── Wait for MySQL to be ready ──
echo "Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
until php artisan db:monitor --databases=mysql 2>/dev/null || [ $COUNT -ge $MAX_TRIES ]; do
    COUNT=$((COUNT + 1))
    echo "  MySQL not ready (attempt $COUNT/$MAX_TRIES)..."
    sleep 2
done

# ── Run migrations ──
php artisan migrate --force --no-interaction || echo "WARNING: Migration failed, continuing..."

# ── Re-cache for production (skip view cache if it fails) ──
php artisan config:cache || echo "WARNING: Config cache failed"
php artisan route:cache || echo "WARNING: Route cache failed"
# Skip view cache as it may fail with missing components
# php artisan view:cache

# ── Fix permissions ──
chown -R www-data:www-data \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"
chmod -R 775 \
    "$APP_DIR/storage" \
    "$APP_DIR/bootstrap/cache"

# ── Create storage symlink ──
php artisan storage:link --force 2>/dev/null || true

# ── Create supervisor log directory ──
mkdir -p /var/log/supervisor

echo "=== Application ready ==="

# ── Start Supervisor (php-fpm + nginx) ──
exec /usr/bin/supervisord -c /etc/supervisord.conf
