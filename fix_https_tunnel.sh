#!/usr/bin/env bash
# ============================================================
# fix_https_tunnel.sh — เปิด Cloudflare quick tunnel ใหม่ + อัปเดต URL อ้างอิงในไฟล์ต่างๆ
# ใช้เมื่อ https://xxx.trycloudflare.com ขึ้น net::ERR_NAME_NOT_RESOLVED (quick tunnel ตาย)
#
# รันได้ทั้งเหนือขอบเขต (Termux, นอก proot) และในโฟลเดอร์ติดตั้ง:
#   bash fix_https_tunnel.sh                    # autodetect: /data/data/com.termux/files/home/uni-activity ถ้ามี
#   bash fix_https_tunnel.sh /path/to/uni-activity   # ระบุ path เองก็ได้
# ============================================================
set -u

say() { printf '✅ %s\n' "$*"; }
fail() { printf '❌ %s\n' "$*" >&2; exit 1; }
command -v curl >/dev/null 2>&1 || fail "ต้องมี curl (ติดตั้ง: pkg install -y curl หรือ apt install curl)"

# ── 0. หาโปรเจกต์ ─────────────────────────────────────────────
if [ -n "${1:-}" ]; then
  PROJECT_DIR="$1"
elif [ -d "/data/data/com.termux/files/home/uni-activity" ]; then
  PROJECT_DIR="/data/data/com.termux/files/home/uni-activity"
else
  PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
fi
[ -d "$PROJECT_DIR" ] || fail "ไม่พบโฟลเดอร์โปรเจกต์: $PROJECT_DIR (หรือระบุ path: bash fix_https_tunnel.sh /path/uni-activity)"
say "โปรเจกต์: $PROJECT_DIR"

# ใช้ log ตำแหน่งเดียวกับ auto_update_tunnel_url.py ฝั่ง Termux (นอก proot) เมื่ออยู่บน Termux; ไม่งั้น fallback ที่ HOME ปัจจุบัน
if [ -d /data/data/com.termux/files/home ]; then
  LOG_FILE="/data/data/com.termux/files/home/cloudflared.log"
else
  LOG_FILE="${HOME:-/root}/cloudflared.log"
fi
ACTIVE_JSON="$PROJECT_DIR/docs/active_url.json"
INDEX_HTML="$PROJECT_DIR/docs/index.html"
URL_REGEX='https://[a-zA-Z0-9-]+\.trycloudflare\.com'

# ── 1. หาไบนารี cloudflared ─────────────────────────────────
CLOUDFLARED="$(command -v cloudflared 2>/dev/null || true)"
if [ -z "$CLOUDFLARED" ]; then
  for cf in \
    "/data/data/com.termux/files/usr/bin/cloudflared" \
    "/data/data/com.termux/files/home/.local/bin/cloudflared" \
    "/root/.local/bin/cloudflared" \
    /usr/local/bin/cloudflared /usr/bin/cloudflared; do
    [ -x "$cf" ] && CLOUDFLARED="$cf" && break
  done
fi
[ -n "$CLOUDFLARED" ] && [ -x "$CLOUDFLARED" ] || fail "ไม่พบ cloudflared (ติดตั้ง: pkg install -y cloudflared หรือ apt install cloudflared)"
say "ใช้ cloudflared: $CLOUDFLARED"

# ── 2. หา origin port ของแอปที่ตอบสนองจริง ───────────────────
ORIGIN_PORT=""
for port in 80 8000 8080 8082 8088 8199 8090 8900; do
  code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 3 "http://127.0.0.1:$port/" 2>/dev/null)"
  if [ -n "$code" ] && [ "$code" != "000" ]; then ORIGIN_PORT="$port"; break; fi
done
[ -n "$ORIGIN_PORT" ] || fail "ไม่พบแอปที่ port ใดเลย (80/8000/8080/8082/8088/8199) — เริ่มแอป (nginx/php artisan serve/octane) ก่อนแล้วรันใหม่"
say "พบแอป origin: http://127.0.0.1:$ORIGIN_PORT"

# ── 3. ปิด cloudflared quick tunnel เก่าที่ค้าง ──────────────
# ปิดเฉพาะกระบวนการ cloudflared ที่ชี้ขึ้น URL http (เก็บ SSH tunnel ไว้)
if command -v pgrep >/dev/null 2>&1; then
  pgrep -af 'cloudflared.*--url http' | awk '{print $1}' | while read -r pid; do
    kill "$pid" && say "ปิด cloudflared เดิม: PID $pid"
  done
  sleep 2
fi

# ── 4. เปิด quick tunnel ใหม่ ────────────────────────────────
rm -f "$LOG_FILE"
say "กำลังเปิด quick tunnel ใหม่ → http://127.0.0.1:$ORIGIN_PORT (รอ ~20–40 วินาที)"
nohup "$CLOUDFLARED" tunnel \
  --url "http://127.0.0.1:$ORIGIN_PORT" \
  --protocol http2 --edge-ip-version 4 --no-autoupdate \
  > "$LOG_FILE" 2>&1 &

# ── 5. รอ URL ใหม่จาก log ────────────────────────────────────
NEW_URL=""
for i in $(seq 1 30); do
  sleep 2
  u="$(grep -oE "$URL_REGEX" "$LOG_FILE" 2>/dev/null | tail -n 1)"
  if [ -n "$u" ]; then NEW_URL="$u"; break; fi
done
[ -n "$NEW_URL" ] || fail "ไม่พบ URL ใหม่ใน log ($LOG_FILE) — ดูต้นเหตุจาก: tail -n 30 \"$LOG_FILE\""
say "ได้ URL ใหม่: $NEW_URL"

# ยืนยัน URL ตอบสนองจริง
try_code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$NEW_URL" 2>/dev/null)"
try_code="${try_code:-000}"
case "$try_code" in
  000) fail "URL ใหม่ไม่ตอบสนอง (HTTP $try_code) — ดู log: $LOG_FILE" ;;
  *) say "URL ใหม่ตอบกลับ HTTP $try_code — tunnel พร้อมใช้งาน" ;;
esac

# ── 6. อัปเดตไฟล์ที่อ้างอิง URL ───────────────────────────────
STAMP="$(date '+%Y-%m-%d %H:%M:%S')"
cat > "$ACTIVE_JSON" <<EOF
{
  "ssh_url": "",
  "updated_at": "$STAMP",
  "url": "$NEW_URL"
}
EOF
say "อัปเดต $ACTIVE_JSON"

if [ -f "$INDEX_HTML" ]; then
  cp -f "$INDEX_HTML" "$INDEX_HTML.bak"
  sed -E "s|$URL_REGEX|$NEW_URL|g" "$INDEX_HTML" > "$PROJECT_DIR/docs/.index.html.tmp" \
    && mv "$PROJECT_DIR/docs/.index.html.tmp" "$INDEX_HTML" \
    && say "อัปเดต $INDEX_HTML (สำรองเดิมไว้ที่ index.html.bak)"
fi

if [ -f "$PROJECT_DIR/.env" ]; then
  cp -f "$PROJECT_DIR/.env" "$PROJECT_DIR/.env.bak"
  sed -E "s|^APP_URL=$URL_REGEX|APP_URL=$NEW_URL|" "$PROJECT_DIR/.env" > "$PROJECT_DIR/.env.tmp" \
    && mv "$PROJECT_DIR/.env.tmp" "$PROJECT_DIR/.env" \
    && say "อัปเดต APP_URL ใน .env (สำรองเดิมไว้ใน .env.bak)"
fi

# ── 7. เคลียร์ Laravel cache (ถ้ามี php) ─────────────────────
if command -v php >/dev/null 2>&1 && [ -f "$PROJECT_DIR/artisan" ]; then
  (cd "$PROJECT_DIR" && php artisan config:clear && php artisan route:clear) \
    && say "เคลียร์ Laravel config/route cache แล้ว"
fi

echo
echo "🎯 หน้าเว็บพร้อมใช้งานแล้วที่: $NEW_URL"
echo "     (ต้องการให้ URL คงที่แบบถาวร ให้ย้ายไปใช้ named tunnel แทน quick tunnel)"
