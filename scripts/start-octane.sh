#!/bin/bash
# scripts/start-octane.sh — เปิด Octane (FrankenPHP) ด้วย Caddyfile ของโครงการ
#   ทำให้ static โหลดไว: brotli + Cache-Control (immutable บน /build/*)
# ใช้: bash scripts/start-octane.sh [--port 8000]
set -eu
cd "$(dirname "$0")/.."
PORT=8000
while [ "$#" -gt 0 ]; do
    case "$1" in
        --port) PORT="$2"; shift 2 ;;
        *) shift ;;
    esac
done

pkill -9 -f "frankenphp ru[n]" 2>/dev/null || true
sleep 2

exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port="$PORT" \
    --workers=1 \
    --caddyfile=config/octane/Caddyfile
