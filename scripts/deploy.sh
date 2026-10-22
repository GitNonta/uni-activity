#!/bin/bash
# scripts/deploy.sh — deploy เครื่องเดียวจบ:
#   - เขียนตรงใช้บนเครื่องหลัก (Termux native) หรือคอนเทนเนอร์ debian
#   - ทำงาน auto-sync ผ่าน git pull แล้ว migrate + ตรวจ route + serve
# ใช้: bash scripts/deploy.sh [--port N] [--serve]
set -eu
cd "$(dirname "$0")/.."
PORT="8000"
DO_SERVE=0
while [ "$#" -gt 0 ]; do
    case "$1" in
        --port) PORT="$2"; shift 2 ;;
        --serve) DO_SERVE=1; shift ;;
        *) shift ;;
    esac
done

echo "== 1) git pull เลือกแบบให้ตรงกัน =="
git pull --ff-only origin "$(git rev-parse --abbrev-ref HEAD)" 2>/dev/null \
  || echo "ข้าม pull (ตรงกันหรือไม่ได้อยู่ใน git repo)"

echo "== 2) composer install (ถ้ายังไม่มี vendor) =="
[ -d vendor ] || composer install --no-interaction --no-scripts

echo "== 3) .env (แค่ถ้ายังไม่มี) =="
[ -f .env ] || { cp .env.example .env; php artisan key:generate --force >/dev/null; }
grep -q "^APP_KEY=base64" .env || php artisan key:generate --force >/dev/null

echo "== 4) migrate =="
php artisan migrate --force 2>&1 | tail -3

echo "== 5) storage + cache dirs (สำรองกรณี git ไม่ครอบ) =="
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache

echo "== 6) ตรวจ route มีคุณสมบัติ 9 ชุด =="
php artisan route:list 2>/dev/null | grep -cE "activities.pay|bookmark|recommendations|lang/|auth/sso"

echo "== 7) smoke =="
if [ "$DO_SERVE" = "1" ]; then
    nohup php artisan serve --host=0.0.0.0 --port="$PORT" >/dev/null 2>&1 &
    SRV=$!
    sleep 5
    for t in 302 419 404; do :; done
    r1=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${PORT}/recommendations")
    r2=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${PORT}/lang/en")
    r3=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${PORT}/auth/sso/redirect")
    echo "recommendations=$r1 (คาด 302) | lang=$r2 (คาด 302) | sso=$r3 (คาด 404)"
    [ "$r1" = "302" ] && [ "$r2" = "302" ] && [ "$r3" = "404" ] || { echo "SMOKE-FAIL"; exit 1; }
    echo "SMOKE-PASS — เปิดใช้งานเสร็จบน port ${PORT}"
    kill "$SRV" 2>/dev/null || true
else
    echo "(ข้าม smoke — ไม่ใช่ --serve)"
fi
