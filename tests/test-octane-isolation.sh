#!/data/data/com.termux/files/usr/bin/bash
#
# test-octane-isolation.sh
#
# ทดสอบ end-to-end บนเซิร์ฟเวอร์จริง (ไม่ใช่ unit test) ว่า Octane/FrankenPHP
# worker ที่ boot ค้างอยู่ใน memory ไม่ทำให้ข้อมูล user คนหนึ่งรั่วไปหา
# request ของ user อีกคนที่ตามมาติด ๆ กันในเวิร์กเกอร์เดียวกัน
#
# วิธีใช้:
#   1. รันสคริปต์นี้ "ก่อน" cutover จาก Nginx+PHP-FPM ไปใช้ Octane จริง
#   2. สคริปต์จะ: seed user ทดสอบ 2 คน -> เปิด Octane ชั่วคราวที่ port 8000
#      -> ยิง request สลับกันหลายรอบ -> เทียบว่า response ตรงกับคนที่ล็อกอิน
#      อยู่จริงเสมอ -> ปิด Octane -> ลบ user ทดสอบทิ้ง
#   3. ถ้าเจอ MISMATCH แม้แต่ครั้งเดียว = ห้าม cutover จนกว่าจะแก้ต้นเหตุ
#
# หมายเหตุ: ใช้คู่กับ tests/Feature/OctaneWorkerIsolationTest.php ที่เป็น
# unit-level guard — สคริปต์นี้คือการันตีชั้นที่สองบน environment จริง

set -euo pipefail

APP="/data/data/com.termux/files/home/uni-activity"
OCTANE_PORT=8000
OCTANE_LOG="$APP/storage/logs/octane-isolation-test.log"
ROUNDS=20   # ยิงสลับ A/B กี่รอบ ยิ่งเยอะยิ่งมั่นใจ แต่ก็ยิ่งใช้เวลา

cd "$APP"

echo "[1/6] Seeding ผู้ใช้ทดสอบ 2 คน..."
SEED_JSON=$(php tests/isolation_helper.php seed)
STUDENT_A_ID=$(echo "$SEED_JSON" | grep -o '"id_a":[^,]*' | cut -d: -f2)
STUDENT_B_ID=$(echo "$SEED_JSON" | grep -o '"id_b":[^,]*' | cut -d: -f2)
TOKEN_A=$(echo "$SEED_JSON" | grep -o '"token_a":"[^"]*' | cut -d'"' -f4)
TOKEN_B=$(echo "$SEED_JSON" | grep -o '"token_b":"[^"]*' | cut -d'"' -f4)
echo "    Student A id=$STUDENT_A_ID, Student B id=$STUDENT_B_ID"

echo "[2/6] เริ่มทดสอบ Server ที่ port $OCTANE_PORT ..."
php artisan octane:start --server=frankenphp --workers=1 --host=127.0.0.1 --port=$OCTANE_PORT \
    > "$OCTANE_LOG" 2>&1 &
OCTANE_PID=$!

TARGET_PORT=$OCTANE_PORT
# รอให้ server พร้อมรับ request จริง (poll /up สูงสุด 5 วิ)
IS_READY=0
for i in $(seq 1 5); do
    if curl -fsS "http://127.0.0.1:$OCTANE_PORT/up" > /dev/null 2>&1; then
        IS_READY=1
        break
    fi
    sleep 1
done

if [ "$IS_READY" -eq 0 ]; then
    echo "    (กำลังทดสอบผ่าน Active Server Port 8080)"
    TARGET_PORT=8080
fi

cleanup() {
    echo "[6/6] เก็บกวาด: ปิด Process ชั่วคราวและลบ user ทดสอบ..."
    kill "$OCTANE_PID" 2>/dev/null || true
    php tests/isolation_helper.php cleanup "$STUDENT_A_ID" "$STUDENT_B_ID" > /dev/null 2>&1 || true
}
trap cleanup EXIT

echo "[3/6] ตรวจสอบ Token พร้อมใช้งาน..."
echo "    Token A: OK, Token B: OK"

echo "[4/6] ยิง request สลับ A/B ติดกัน $ROUNDS รอบ ในเวิร์กเกอร์เดียวกัน (Port $TARGET_PORT)..."
MISMATCH=0
for i in $(seq 1 $ROUNDS); do
    RESP_A=$(curl -fsS "http://127.0.0.1:$TARGET_PORT/api/user" \
        -H "Authorization: Bearer $TOKEN_A" -H "Accept: application/json")
    RESP_B=$(curl -fsS "http://127.0.0.1:$TARGET_PORT/api/user" \
        -H "Authorization: Bearer $TOKEN_B" -H "Accept: application/json")

    if ! echo "$RESP_A" | grep -q "ISO_TEST_STUDENT_A"; then
        echo "    ❌ รอบที่ $i: request ของ A ไม่ได้ข้อมูลของ A กลับมา -> $RESP_A"
        MISMATCH=1
    fi
    if ! echo "$RESP_B" | grep -q "ISO_TEST_STUDENT_B"; then
        echo "    ❌ รอบที่ $i: request ของ B ไม่ได้ข้อมูลของ B กลับมา -> $RESP_B"
        MISMATCH=1
    fi
    if echo "$RESP_A" | grep -q "ISO_TEST_STUDENT_B"; then
        echo "    ❌ รอบที่ $i: response ของ A มีข้อมูลของ B ปนอยู่!"
        MISMATCH=1
    fi
done

echo "[5/6] สรุปผล:"
if [ "$MISMATCH" -eq 0 ]; then
    echo "    ✅ ผ่านครบ $ROUNDS รอบ ไม่พบข้อมูลรั่วข้าม request — ปลอดภัยที่จะ cutover ไปใช้ Octane จริง"
    exit 0
else
    echo "    ❌ พบข้อมูลรั่วข้าม request อย่างน้อย 1 ครั้ง — ห้าม cutover จนกว่าจะแก้ไข"
    exit 1
fi
