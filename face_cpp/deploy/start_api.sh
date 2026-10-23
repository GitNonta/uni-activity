#!/data/data/com.termux/files/usr/bin/bash
# start_api.sh — เปิด face_api (SCRFD + ArcFace + MobileFaceNet) บน Termux/S2
#
#   bash start_api.sh [workers] [threads] [max_faces]
#
# ค่าเริ่มต้น workers=1 threads=8 คือค่าที่วัดแล้วเร็วที่สุดบน SDM450 (8 คอร์):
#   ลด latency ต่อคำขอ ~20% เทียบกับ workers=2 threads=4
# ใช้ workers=2 threads=4 เมื่อต้องการรับคำขอพร้อมกันมากกว่า 1 รายการ
set -u

W=${1:-1}
T=${2:-8}
M=${3:-5}
PORT=${FACE_API_PORT:-8010}
KEY=${FACE_API_KEY:-uni-activity-ai-secret-key-2026}
DIR=${FACE_API_DIR:-$HOME/face_api}

export PATH=$PREFIX/bin:$PATH
cd "$DIR" || { echo "no $DIR" >&2; exit 1; }

pkill -9 -f "face_api --serve" 2>/dev/null
sleep 1
: > "$DIR/api.log"
setsid nohup ./face_api --serve --model-dir models --port "$PORT" \
  --workers "$W" --threads "$T" --max-faces "$M" --api-key "$KEY" \
  >> "$DIR/api.log" 2>&1 &

sleep 3
curl -sS -m 5 -H "X-API-Key: $KEY" "http://127.0.0.1:$PORT/health" | head -c 300
echo
tail -2 "$DIR/api.log"
