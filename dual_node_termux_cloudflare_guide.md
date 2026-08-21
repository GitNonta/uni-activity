# คู่มือ: รัน uni-activity บน Huawei Y7 2019 x2 เครื่อง (Termux) + Cloudflare Tunnel URL เดียว

สถาปัตยกรรม (ตามที่โปรเจกต์กำหนดไว้ใน `docs/DUAL_NODE_DEPLOYMENT.md`):

```
                 Cloudflare Tunnel (Public URL เดียว)
                              │
                              ▼
   📱 Phone 1 — Master Gateway            📱 Phone 2 — Worker / AI (192.168.1.140)
   • Nginx Load Balancer :8088   ◄───────► • Laravel Octane :8000
   • PostgreSQL :5432                       • Python AI Service :8001
   • Redis :6379                            • Queue Worker (high,default)
   • Laravel Reverb (WS) :8080
   • Laravel Octane :8000
```

Phone 1 เป็นทั้งตัวรับทราฟฟิกจาก Cloudflare Tunnel และเป็น Node ประมวลผลไปด้วย, Phone 2 ช่วยแบ่งโหลดและรัน AI service

> ⚠️ **IP จริงของ Phone 2 ในระบบปัจจุบันคือ `192.168.1.140`** (ไม่ใช่ `192.168.1.223` เหมือนตอนเขียนคู่มือแรก) — เอกสารนี้แก้ IP ทั้งหมดเป็น `.140` แล้ว

---

## ⚠️ ข้อจำกัดที่ต้องรู้ก่อน (อ่านก่อนลงมือ)

- **Y7 2019 (Kirin 710F, RAM 2–3GB)** คือมือถือระดับล่าง ไม่ใช่เซิร์ฟเวอร์ — รับโหลดพร้อมกันได้หลัก "สิบ-ร้อยคน" ไม่ใช่หลักพัน อย่าตั้งเป้า high concurrency แบบ cloud server
- **AI Service (InsightFace + YOLOv8 + onnxruntime)** ในโฟลเดอร์ `ai_service/` เป็นไลบรารีหนักและบางแพ็กเกจ (เช่น `onnxruntime`, `insightface`) **อาจไม่มี prebuilt wheel สำหรับ Termux/Android aarch64** ต้อง build เองซึ่งอาจไม่สำเร็จหรือใช้เวลานานมาก — เตรียมใจว่าอาจต้องปิดฟีเจอร์ AI face-verification หรือย้ายไปรันบนเครื่องอื่นแทน
- ✅ **แก้ให้แล้วใน repo นี้**: `start_dual_node.sh` รัน AI service ถูกต้องแล้ว (`cd ai_service && uvicorn server:app` — ไม่ใช่ `ai_service.main:app`)
- ✅ **แก้ให้แล้วใน repo นี้**: `deploy_cf.py` และ `boot_setup.sh` ไม่มี secret ฝังในโค้ดแล้ว — อ่านจาก env var / `.env` (ดูหัวข้อด้านล่าง)
- ⚠️ **ไฟล์ `boot_setup.sh` ยังต้องระวัง**: มันตั้งค่า SSH password ผ่าน env var `SSH_PASSWORD` (ไม่ hardcode แล้ว) แต่ก่อน push ขึ้น GitHub ควรตรวจอีกทีว่าไม่มี secret หลงเหลือในไฟล์
- ไม่ต้องยุ่งกับโฟลเดอร์ `zero_day_research/`, `edl_tools/`, `advanced_zero_day_hunter.py` — ไม่เกี่ยวกับการรันเว็บแอปนี้เลย ตัดทิ้งได้เลยก่อนโอนไฟล์ขึ้นมือถือ เพื่อประหยัดพื้นที่

---

## ส่วนที่ 1 — เตรียมมือถือทั้ง 2 เครื่อง

ทำเหมือนกันทั้ง 2 เครื่องก่อน:

1. ติดตั้ง **Termux** จาก F-Droid (เวอร์ชัน Play Store เก่ามากและเลิกอัปเดตแล้ว อย่าใช้)
2. ติดตั้งเสริม **Termux:API** และ **Termux:Boot** จาก F-Droid ด้วย (ใช้กัน sleep/รันตอนบูตเครื่อง)
3. ตั้งค่า Android:
   - ปิด **Battery Optimization** ให้ Termux (Settings → Apps → Termux → Battery → Unrestricted)
   - เสียบสายชาร์จค้างไว้ตลอด (จะรันเป็นเซิร์ฟเวอร์ 24 ชม.)
   - ต่อ WiFi วงเดียวกันทั้ง 2 เครื่อง (เช่น `192.168.1.x`) แล้วตั้ง **IP แบบ Static** ในหน้า WiFi settings ของ router หรือของเครื่อง กันไม่ให้ IP เปลี่ยนหลัง reconnect
4. เปิด Termux แล้วรันคำสั่งพื้นฐาน:

```bash
termux-setup-storage
pkg update -y && pkg upgrade -y
pkg install -y git wget nginx openssh
termux-wake-lock   # กันเครื่อง sleep ระหว่างรันเซิร์ฟเวอร์
```

---

## ส่วนที่ 2 — โอนโปรเจกต์เข้ามือถือ

แนะนำให้ push โค้ด (ตัด `zero_day_research/`, `edl_tools/` ออกก่อน) ขึ้น GitHub repo ส่วนตัว แล้ว `git clone` ในมือถือทั้งสองเครื่อง — จัดการ `git pull` อัปเดตทีหลังง่ายกว่ามาก:

```bash
# ทำในทั้ง 2 เครื่อง
cd ~
git clone https://github.com/<your-user>/uni-activity.git
cd uni-activity
```

---

## ส่วนที่ 3 — Phone 1 (Master: DB + Redis + Reverb + Octane)

```bash
pkg install -y postgresql redis php php-pgsql composer nodejs

# 1) เริ่ม PostgreSQL
initdb $PREFIX/var/lib/postgresql   # ครั้งแรกครั้งเดียว
pg_ctl -D $PREFIX/var/lib/postgresql start
createuser --superuser --pwprompt root
createdb -O root uni_activity

# 2) ติดตั้ง dependency ของ Laravel
composer install --no-dev --optimize-autoloader
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

แก้ `.env` บน Phone 1:

```ini
APP_ENV=production
APP_URL=https://<ชื่อโดเมนที่จะได้จาก Cloudflare Tunnel>

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uni_activity
DB_USERNAME=root
DB_PASSWORD=<รหัสที่ตั้งตอน createuser>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

BROADCAST_CONNECTION=reverb
REVERB_HOST=127.0.0.1
REVERB_PORT=8080

# AI service รันอยู่ที่ Phone 2 — ชี้ไปที่นั่น (ดูหัวข้อ 7.5)
AI_SERVERS=http://192.168.1.140:8001
```

```bash
php artisan migrate --force
```

---

## ส่วนที่ 4 — Phone 2 (Worker: Octane + AI service + Queue)

```bash
pkg install -y php composer nodejs python

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

แก้ `.env` บน Phone 2 ให้ชี้กลับไปที่ Phone 1 (แทน `192.168.1.222` ด้วย IP จริงของ Phone 1):

```ini
DB_HOST=192.168.1.222
DB_PORT=5432
REDIS_HOST=192.168.1.222
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# AI service รันบนเครื่องนี้เอง
AI_SERVER_URL=http://127.0.0.1:8001
AI_SERVERS=http://127.0.0.1:8001

# Queue worker บน Phone 2 ที่ broadcast event ต้องส่งหา Reverb ที่รันบน Phone 1
BROADCAST_CONNECTION=reverb
REVERB_INTERNAL_HOST=192.168.1.222
REVERB_PORT=8080
REVERB_SCHEME=http
```

ติดตั้ง AI service (ทดลองก่อน — ดูข้อจำกัดด้านบน):

```bash
cd ai_service
pip install -r requirements.txt   # อาจ error ที่ onnxruntime/insightface — ถ้าล้มเหลวให้ปิดฟีเจอร์นี้ในระบบไปก่อน
cd ..
```

✅ **ไม่ต้องแก้ `start_dual_node.sh` แล้ว** — โค้ดใน repo นี้รัน `cd ai_service && uvicorn server:app` ถูกต้องแล้ว

---

## ส่วนที่ 5 — สตาร์ทคลัสเตอร์

**บน Phone 1** (ใส่ IP ของ Phone 2 เป็นอาร์กิวเมนต์):

```bash
bash scripts/start_dual_node.sh node1 192.168.1.140
```

**บน Phone 2** (ใส่ IP ของ Phone 1):

```bash
bash scripts/start_dual_node.sh node2 192.168.1.222
```

> ปรับจำนวน worker ได้ผ่าน env: `OCTANE_WORKERS=2 AI_WORKERS=1 bash scripts/start_dual_node.sh node1 192.168.1.140`
> (ค่า default: Octane 2 worker, AI 1 worker — เหมาะกับ RAM 2-3GB)

ทดสอบว่า Load Balancer ทำงาน:

```bash
curl http://127.0.0.1:8088/health-cluster
# ควรได้ {"status":"ok","cluster":"dual-node-active"}
```

---

## ส่วนที่ 6 — Cloudflare Tunnel: URL เดียวออกสู่อินเทอร์เน็ต

ทำบน **Phone 1 เท่านั้น** (เพราะเป็นตัวที่มี Nginx Load Balancer :8088 ซึ่งกระจายงานไป Phone 2 ให้อัตโนมัติอยู่แล้ว — ไม่ต้องเปิด Tunnel ที่ Phone 2)

```bash
pkg install -y cloudflared
cloudflared tunnel login          # เปิดลิงก์ยืนยันบัญชี Cloudflare ผ่านเบราว์เซอร์
cloudflared tunnel create uni-activity
```

สร้างไฟล์ config:

```bash
mkdir -p ~/.cloudflared
cat > ~/.cloudflared/config.yml <<'EOF'
tunnel: uni-activity
credentials-file: /data/data/com.termux/files/home/.cloudflared/<TUNNEL_ID>.json

ingress:
  - hostname: your-subdomain.yourdomain.com
    service: http://127.0.0.1:8088
  - service: http_status:404
EOF
```

ผูกโดเมนย่อยกับ tunnel (ต้องมีโดเมนอยู่ใน Cloudflare account):

```bash
cloudflared tunnel route dns uni-activity your-subdomain.yourdomain.com
```

รัน tunnel:

```bash
cloudflared tunnel run uni-activity
```

ถ้ายังไม่มีโดเมนของตัวเอง ใช้ **Quick Tunnel** ชั่วคราวได้ (ได้ URL `*.trycloudflare.com` แบบสุ่ม ไม่ persistent):

```bash
cloudflared tunnel --url http://127.0.0.1:8088
```

ทราฟฟิกทั้งหมดที่เข้ามาทาง URL นี้ → Nginx (:8088 / :8080) บน Phone 1 → กระจาย `least_conn` ไปยังแอปบน Phone 1 (127.0.0.1:8000) และ Phone 2 (192.168.1.140:8000) อัตโนมัติ ทั้ง WebSocket (`/app`) และ AI endpoint (`/ai/`) ก็ผ่าน route เดียวกันนี้

---

## ส่วนที่ 7 — ทำให้รันค้างตลอด (ไม่ตายเมื่อจอดับ/แอปโดน kill)

### 7.1 ติดตั้งแอปที่จำเป็น (ครั้งเดียว)
```bash
# บน Phone 1 และ Phone 2 (ถ้ายังไม่มี)
pkg install -y termux-services termux-api
# ติดตั้งแอป Termux:API (com.termux.api) — จำเป็นสำหรับ termux-wake-lock
#   - Phone 1: ติดตั้งแล้ว
#   - Phone 2: ดาวน์โหลด APK จาก https://github.com/termux/termux-api/releases
#     แล้วติดตั้งผ่าน USB ADB:  adb install termux-api-app_v0.53.0+github.debug.apk
#     (APK ต้อง sign ด้วย key เดียวกับ Termux ที่ติดตั้ง — ห้ามใช้ debug APK ต่าง key)
```

### 7.2 กัน Doze / battery kill ระหว่างเซสชัน
```bash
termux-wake-lock
```

### 7.3 Auto-start ตอนเปิดเครื่อง ผ่าน Termux:Boot
Termux:Boot รันทุกไฟล์ใน `~/.termux/boot/` (เรียงตามชื่อ) ตอนเปิดเครื่อง — วางสคริปต์เดียว `start-cluster.sh` ต่อเครื่อง:

- **Phone 1 (master):** `scripts/boot-node1.sh` → เริ่ม runsvdir (runit), sv up cloudflared/nginx/postgres/sshd, redis :6379 (0.0.0.0), AI, web workers + watchdog, reverb :8082, queue worker, monitor
- **Phone 2 (worker):** `scripts/boot-node2.sh` → sshd, AI, web workers + watchdog, queue worker

```bash
# บนแต่ละเครื่อง: คัดลอกสคริปต์จาก repo ไปไว้ boot dir แล้วให้สิทธิ์
cp scripts/boot-node1.sh ~/.termux/boot/start-cluster.sh   # Phone 1
cp scripts/boot-node2.sh ~/.termux/boot/start-cluster.sh   # Phone 2
chmod +x ~/.termux/boot/start-cluster.sh
```

⚠️ **สำคัญ:** ปิดการใช้งานสคริปต์ boot เก่าที่อาจขัดแย้ง (Octane/Horizon/redis :6380) — เปลี่ยนนามสกุลเป็น `.off` ไว้ก่อน:
```bash
cd ~/.termux/boot && for f in *; do case "$f" in *.off|start-cluster.sh) ;; *) mv "$f" "$f.off";; esac; done
```

หมายเหตุ: `~/.termux/boot/` มีแค่ `start-cluster.sh` ที่ active — สคริปต์เก่าทั้งหมดถูก rename เป็น `.off` แล้ว (deploy 21 ส.ค. 2026)

### 7.4 runit (termux-services) — บน Phone 1
Phone 1 ใช้ runit จัดการ cloudflared/nginx/postgres/sshd (auto-restart เมื่อ crash) อยู่แล้ว แต่ service หลักมี `down` marker — สคริปต์ boot จะ `sv up` ให้ทุกครั้งตอนเปิดเครื่อง:

---

## ส่วนที่ 7.5 — ⚠️ สำคัญมากสำหรับ 2 โหนด (พลาดแล้วพัง)

### 1) `APP_KEY` ต้องเหมือนกันทั้ง 2 เครื่อง
Laravel ใช้ `APP_KEY` เข้ารหัส cookie/session — ถ้า Phone 1 กับ Phone 2 มี key ต่างกัน ผู้ใช้จะถูก logout สลับไปมาเพราะ request สลับ node

```bash
# รัน key:generate บน Phone 1 เท่านั้น แล้ว COPY APP_KEY ไปใส่ .env ของ Phone 2
php artisan key:generate --force
# เปิด .env ของ Phone 1 → copy ค่า APP_KEY=... → วางแทนที่บน .env ของ Phone 2 (ห้าม generate ใหม่)
```

### 2) ไฟล์ upload (รูปโปรไฟล์/selfie) ต้องอยู่บน storage กลาง
ตอนนี้ทุกไฟล์เก็บใน `storage/app/public` **บนเครื่องที่รับ request** — ถ้า upload เข้า Phone 2 แล้ว browser เปิดผ่าน Phone 1 จะเจอ 404

ทางเลือก: ตั้ง `FILESYSTEM_DISK=s3` + ใช้ Cloudflare R2 (ฟรี 10GB) หรือ MinIO แล้วทั้ง 2 เครื่องชี้ bucket เดียวกัน:

```ini
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=uni-activity
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_URL=https://pub-<hash>.r2.dev
AWS_USE_PATH_STYLE_ENDPOINT=false
```

> วิธี local-only แบบไม่ต้องใช้ S3: ทำได้โดยให้ Nginx บน Phone 1 จับ `location /storage/` ชี้ไป `127.0.0.1:8000` (Node 1) เสมอ + ตั้งให้ POST / upload ไป Node 1 เท่านั้น (แต่ต้องแก้ `docker/lb.dual-node.conf` เอง) — แนะนำให้ใช้ S3/R2 ตรงๆ ง่ายกว่าและรอดูแลน้อยกว่า

### 3) `AI_SERVERS` บน Phone 1 ต้องชี้ไป Phone 2
Phone 1 ไม่ได้รัน AI service ในเครื่อง — อย่าปล่อยค่า default `127.0.0.1:8001` ไว้ ไม่งั้น face verification พัง

```ini
# บน Phone 1 (.env):
AI_SERVERS=http://192.168.1.140:8001
```

### 4) Reverb (WebSocket) รันที่ Phone 1 เท่านั้น
- บน Phone 2 ตั้ง `REVERB_INTERNAL_HOST=192.168.1.222` (ดูส่วนที่ 4) เพื่อให้ queue worker ส่ง broadcast ไปยัง Reverb บน Phone 1 ได้
- Frontend (`VITE_REVERB_*`) build บน Phone 1 ชี้ host ของ Cloudflare URL เดียวกัน — WebSocket ผ่าน Nginx `/app` route

### 5) เปิด DB/Redis ให้ Phone 2 เข้าถึงผ่าน LAN (ทำแล้วในระบบจริง)
Postgres กับ Redis บน Phone 1 ผูก `127.0.0.1` ไว้ก่อน — Phone 2 จะต่อไม่ได้ถ้าไม่เปิดให้ฟังบน LAN:

```bash
# Phone 1 — PostgreSQL: ฟังทุก interface + ตั้งรหัสผ่าน (pg_hba.conf มีบรรทัด LAN scram-sha-256 อยู่แล้ว)
sed -i "s/^listen_addresses = '127.0.0.1'/listen_addresses = '*'/" $PREFIX/var/lib/postgresql/postgresql.conf
psql -U postgres -d uni_activity -c "ALTER USER postgres PASSWORD '<รหัสผ่าน>';"
pg_ctl -D $PREFIX/var/lib/postgresql restart

# Phone 1 — Redis: ฟังทุก interface (เก็บ requirepass ไว้)
sed -i 's/^bind 127.0.0.1/bind 0.0.0.0/' $PREFIX/etc/redis.conf
# restart redis แล้ว
```

แล้วใน `.env` ของ **ทั้ง 2 เครื่อง** ตั้งค่าให้ตรงกัน:

```ini
# Phone 1 (ใช้ IP ตัวเองแทน 127.0.0.1 ได้ ไม่มีผลต่าง)
DB_PASSWORD="<รหัสผ่านที่ตั้งใน ALTER USER>"
REDIS_PASSWORD="<รหัสผ่าน redis>"

# Phone 2 — ต้องชี้ข้ามเครื่อง (รวมถึง DB_REPLICA_HOST ที่ config/database.php อ่านเป็น read-host!)
DB_HOST=192.168.1.222
DB_REPLICA_HOST=192.168.1.222   # พลาดตัวนี้ migrate:status จะไปชน 127.0.0.1 ของตัวเอง
REDIS_HOST=192.168.1.222
REDIS_PASSWORD="<รหัสผ่าน redis>"
```

> ⚠️ **เจอจริง**: `.env` ที่ copy จาก Phone 1 มี `DB_REPLICA_HOST=127.0.0.1` ติดมาด้วย — Phone 2 ใช้ read-connection นี้แล้วพยายามต่อ localhost ตัวเอง (Connection refused) ต้องแก้เป็น IP ของ Phone 1

### 6.5) ⚠️ ถ้าเห็น 401/403 บน `/chat/threads`, `/student/notifications`, `/broadcasting/auth` แบบสุ่ม

สาเหตุ: **Redis หลุดชั่วครู่** (LAN เด้ง, password ผิด, Redis โดน OOM kill) → `AppServiceProvider` เดิม
fallback session ไปเป็น `file` → file session อยู่คนละเครื่องกัน → request ที่ Nginx LB ส่งไป Phone 2
หา session ไม่เจอ → 401/403

แก้แล้วในโค้ด (commit หลัง 21 ส.ค. 2026): fallback เปลี่ยนเป็น `database` driver แทน — Postgres
เป็นตัวเดียวที่ทั้ง 2 เครื่องชี้ร่วมกัน ดังนั้น session ยังอยู่ร่วมกันได้แม้ Redis จะล่ม

ถ้ายังเจอ 401/403 อยู่ ให้เช็ค:
1. `REDIS_PASSWORD` ใน `.env` ของ **ทั้ง 2 เครื่อง** ตรงกับ `redis-server --requirepass` จริง (หลุดตัวเดียว → fallback ทำงาน)
2. `APP_KEY` ยัง MATCH กันทั้ง 2 เครื่อง (ถ้าต่างกัน cookie ถอดรหัสไม่ได้ → 401 ตลอด)
3. `redis-cli -a <password> ping` จาก Phone 2 → ต้องได้ `PONG` (ทดสอบการเข้าถึง Redis ข้ามเครื่อง)

---

### 6) php-redis ของ Termux อาจ compile ไม่ตรง PHP version
บนระบบจริง `pkg install php-redis` ได้ `6.3.0RC1` ที่ compile กับ PHP 8.4 (module API `20240924`) แต่ PHP ที่ติดตั้งเป็น 8.5.1 (API `20250925`) → โหลดไม่ได้ (`Unable to initialize module`)

ทางออก: **ไม่ต้องใช้ extension** — Laravel ใช้ `predis` (pure-PHP) ได้ ตรวจว่าใน vendor มี `predis/predis` แล้ว ตั้ง:

```ini
REDIS_CLIENT=predis
```

แล้วลบ `redis.ini` ที่สร้างเองทิ้งถ้าใส่ไปแล้ว:

```bash
rm -f $PREFIX/etc/php/conf.d/redis.ini
```

---

## ส่วนที่ 8 — จูนความเร็ว/รองรับโหลดเท่าที่ฮาร์ดแวร์นี้ทำได้

- `octane:start --workers=2` เหมาะกับ RAM 2-3GB แล้ว อย่าปรับขึ้นสูง เดี๋ยว OOM
- เปิด `OPcache` ให้ PHP (`pkg install php-opcache` ถ้ามี) ลดเวลา compile ทุก request
- Static asset (css/js/รูป) ให้ nginx cache ไว้แล้ว (`expires 7d`) ตาม `docker/lb.dual-node.conf` — อย่าลืม build ผ่าน `npm run build` ให้เรียบร้อยก่อน deploy
- ปิด `APP_DEBUG=false` และตั้ง `LOG_LEVEL=error` ใน production ลดโอเวอร์เฮด I/O
- ถ้า AI service รันไม่ไหวบนมือถือจริงๆ พิจารณาย้าย `ai_service/` ไปรันบน VPS เล็กๆ แยกต่างหาก แล้วให้ Phone 2 เรียกผ่าน API แทน จะลดโหลดบนมือถือทั้งคู่ได้มาก
- Redis ให้ตั้ง `maxmemory` + `maxmemory-policy allkeys-lru` กัน RAM เต็มจน Termux โดน Android kill

---

## สรุปเช็คลิสต์

- [x] ตัดโฟลเดอร์ zero_day_research/edl_tools ออกจากโค้ดที่จะ deploy
- [x] ย้าย secret ใน `deploy_cf.py` ไปไว้ `.env` (ทำแล้วใน repo)
- [x] แก้ `ai_service.main:app` → `ai_service.server:app` ใน start_dual_node.sh (ทำแล้วใน repo)
- [x] Phone 1: Postgres, Redis, Reverb, Nginx LB :8080+:8088 รันครบ (deployed 21 ส.ค. 2026)
- [x] Phone 2: Web worker :8000 + AI service + Queue worker รันครบ (deployed 21 ส.ค. 2026)
- [x] `APP_KEY` เหมือนกันทั้ง 2 เครื่อง (copy จาก Phone 1 → Phone 2 — verify แล้ว MATCH)
- [x] `AI_SERVERS` บน Phone 1 ชี้ไปที่ Phone 2 (healthy 2/2: local 19-27ms + .140 36-59ms)
- [x] `curl 127.0.0.1:8088/health-cluster` → `{"status":"ok","cluster":"dual-node-active"}`
- [x] Cloudflare Tunnel รันบน Phone 1 ชี้ `127.0.0.1:8080` (LB — URL เดิมไม่เปลี่ยน)
- [x] Termux:Boot + wake-lock ให้รันถาวร (deployed 21 ส.ค. 2026): `start-cluster.sh` ใน `~/.termux/boot/` ทั้ง 2 เครื่อง, termux-api ติดตั้งบน Phone 2 ผ่าน USB ADB แล้ว, สคริปต์ boot เก่าถูกปิด (`.off`), Horizon ตัวเก่าถูกหยุด (เหลือ queue:work ตัวเดียว)

> 📌 **สถานะจริง (deploy 21 ส.ค. 2026):** Web ใช้ `php artisan serve` 3 process ต่อเครื่อง (8000/8002/8003 — ยังไม่ใช่ Octane เพราะ Termux ไม่มี swoole/roadrunner/frankenphp ให้ PHP 8.5.1) — Nginx LB `least_conn` ระหว่าง 6 backends (3 ต่อเครื่อง), WebSocket ผ่าน Reverb :8082 (เปิด 0.0.0.0 ให้ Phone 2 broadcast ได้), Postgres+Redis เปิด LAN แล้ว. **ทุกเครื่องมี `watch_web_workers.sh` (watchdog) รันอยู่ — ถ้า Android OOM killer ฆ่า worker จะ restart เองภายใน ~12 วิ (log: `storage/logs/web-watchdog.log`)**. ไฟล์ upload ยังเป็น local storage ต่อเครื่อง (ดูหัวข้อ 7.5 ข้อ 2)
