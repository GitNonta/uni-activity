#!/bin/bash
# ── Cloudflare quick tunnel + reverse proxy ที่ปรับ header ให้ครบ ─────────
# ลำดับสาย: ผู้ใช้ → Cloudflare edge → cloudflared → Caddy(:8301) → Laravel(:8199)
# Caddy เติม X-Forwarded-Proto/Host/Port/Prefix และ X-Real-IP (จาก CF-Connecting-IP)
# และคงหัว X-Forwarded-For ของ edge ไว้ (trusted_proxies 127.0.0.1)
# Laravel ฝั่ง app ต้อง trustProxies('*') อยู่แล้ว (bootstrap/app.php)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CADDYFILE="$ROOT/config/tunnel/Caddyfile"
URL_FILE="${1:-/tmp/cf_url.txt}"

# 1) Caddy (ถ้ายังไม่รัน — พอร์ต 8301)
if ! curl -s -m 3 -o /dev/null http://127.0.0.1:8301/; then
  nohup caddy run --config "$CADDYFILE" >/tmp/caddy-tunnel.log 2>&1 &
  sleep 1
fi

# 2) cloudflared quick tunnel (kill ตัวเก่าก่อน — URL ของ quick tunnel เปลี่ยนทุกครั้ง)
pkill -f "cloudflared tunnel" 2>/dev/null || true
sleep 1
nohup cloudflared tunnel --url http://127.0.0.1:8301 --protocol http2 --metrics 127.0.0.1:20242 \
  >/tmp/cf_tunnel.log 2>&1 &

# 3) รอ URL แล้วบันทึกไฟล์เดียวกับที่ใช้กันทั้ง session
for i in $(seq 1 20); do
  URL=$(grep -oE "https://[a-z0-9-]+\.trycloudflare\.com" /tmp/cf_tunnel.log 2>/dev/null | head -1 || true)
  [ -n "$URL" ] && break
  sleep 1
done
[ -n "${URL:-}" ] || { echo "❌ ไม่ได้ URL จาก cloudflared (ดู /tmp/cf_tunnel.log)"; exit 1; }
echo "$URL" > "$URL_FILE"
echo "✅ tunnel ready: $URL"
echo "   ตรวจ header: curl $URL/debug/headers"
