# face_api — บริการสกัดใบหน้า (SCRFD + ArcFace + MobileFaceNet 512D) บน S2

บริการ HTTP ตัวเดียวจบ: **รูปเต็ม → ตรวจหาใบหน้า → align → embedding 512D**
รันด้วย ncnn (CPU, fp32) บนเครื่อง **S2** (`192.168.1.140`) — ไม่ต้องใช้ Python/OpenCV
ตอนรัน (ถอดรูปด้วย stb_image ที่ฝังมากับไบนารี)

## endpoint

| method | path | หมายเหตุ |
|---|---|---|
| GET | `/health` | สถานะ + สถิติ latency (ใช้กับ load balancer ได้) |
| POST | `/extract` | ตรวจ + align + embed ทุกใบหน้า (ค่าเริ่มต้นไม่เกิน 5) |
| POST | `/detect` | เฉพาะ bbox + 5 landmark (ไม่ embed) |
| POST | `/embed` | รับ crop 112×112 ที่ align แล้ว → embedding เดียว |

- ส่งภาพได้ 2 แบบ: `multipart/form-data` ชื่อฟิลด์ `image` (แบบที่ Laravel `Http::attach` ส่ง)
  หรือ raw body ที่ `Content-Type: image/*`
- ทุก endpoint (ยกเว้นเมื่อปิด key) ต้องมี header `X-API-Key`
- `/extract?faces=1` จำกัดจำนวนใบหน้าที่ embed (ประหยัดเวลาเมื่อต้องการแค่หน้าแรก)
- คำตอบของ `/extract` มี `embedding_512d` (512 ค่า L2-normalized), `embedding` (เหมือนกัน),
  `embedding_128d: null` (PCA 128D ยังไม่ได้ fit — เหมือนโหมด fdx ของ ai_service),
  `bbox`, `det_score`, `num_faces`, `faces[]`, `timings_ms`

```bash
curl -H 'X-API-Key: uni-activity-ai-secret-key-2026' \
     -F 'image=@photo.jpg' 'http://192.168.1.140:8010/extract?faces=1'
```

## โมเดล

| ไฟล์ | ที่มา |
|---|---|
| `det_10g.param/.bin` | `insightface` buffalo_l `det_10g.onnx` → ncnn ด้วย pnnx `inputshape=[1,3,640,640]` |
| `w600k_mbf.param/.bin` | MobileFaceNet ArcFace (มีอยู่แล้วใน `face_cpp/models`) |

```bash
pip install pnnx            # หรือโหลด wheel manylinux_aarch64
pnnx det_10g.onnx inputshape=[1,3,640,640]   # ได้ det_10g.ncnn.param/.bin
```

> ⚠️ โมเดลถูก convert ที่ 640×640 คงที่ — `--det-size` อื่นจะทำให้ reshape เพี้ยน
> ให้ใช้ 640 (ค่าเริ่มต้น) เท่านั้น

## build (Termux / bionic)

```bash
PR=$HOME/ncnn-prebuilt/ncnn-20260526-android-vulkan/arm64-v8a
c++ -O3 -std=c++17 -fopenmp -static-openmp face_api.cpp -o face_api \
  -I$PR/include/ncnn -I. $PR/lib/libncnn.a $PR/lib/libSPIRV.a $PR/lib/libglslang.a \
  -pthread -landroid -ljnigraphics -llog -ldl
```

## deploy บน S2

```
~/face_api/
├── face_api                     # ไบนารี (scp จากเครื่องที่ build)
├── models/{det_10g,w600k_mbf}.{param,bin}
├── start_api.sh                 # cp จาก face_cpp/deploy/
└── api.log
```

```bash
bash ~/start_api.sh 1 8 5        # workers threads max_faces
mkdir -p ~/.termux/boot && cp termux-boot.sh ~/.termux/boot/99-face-api.sh   # autostart
```

## performance (S2 = SDM450, 8 คอร์, Android/Termux, CPU fp32)

ภาพ selfie 640×480 (1 หน้า) / ภาพกลุ่ม 1280×886 (5 หน้า) — วัดผ่าน HTTP:

| config | detect | embed/หน้า | e2e selfie | e2e t1 (`faces=1`) |
|---|---:|---:|---:|---:|
| `workers=2 threads=4` (ก่อนจูน) | ~900–950 ms | ~70 ms | 1.25 s | 1.25 s |
| **`workers=1 threads=8` (ค่าเริ่มต้น)** | **~760 ms** | ~63 ms | **~1.05 s** | ~1.09 s |
| `workers=2 threads=8` | ~770 ms (แย่งคอร์) | — | 1.25 s | 1.27 s |

สิ่งที่ทำให้เร็วขึ้นโดย**ผลลัพธ์ไม่เปลี่ยน** (bitwise-safe):
1. โหลดโมเดลครั้งเดียว + warmup kernel ตอน start → ไม่มีค่าโหลดโมเดลต่อคำขอ (cold start ~1.19 s, ไม่ใช่ 1.5 s+)
2. `threads=8` ให้ ncnn ใช้ครบทุกคอร์ (จาก 4 → 8 ได้ ~20%)
3. `?faces=1` ไม่ embed หน้าที่ไม่ใช้ (ภาพ 5 หน้า: 310 ms → 63 ms)
4. ไม่มี OpenCV/JPEG lib เพิ่ม — ถอดรูปด้วย stb_image ในโปรเซสเดียวกับโมเดล

## parity กับ insightface (reference)

ทดสอบด้วย `det_10g.onnx` (onnxruntime) + `norm_crop` แบบ cv2 บนภาพเดียวกัน:

- bbox ตรงกันภายใน **0.04–0.12 px**, `det_score` ต่างกัน < 0.001
- crop ที่ align แล้วเทียบทีละพิกเซล: mean |diff| **0.20–0.34** ระดับสี, max 8 (จาก 255)
- embedding (ทั้ง chain ของเรา) ↔ embedding ของ crop จาก reference: **cos = 0.99918**
- embedding L2-normalized = 1.000000, dim = 512

ตรวจซ้ำได้ด้วย `--dump-crop DIR` (เขียน crop 112×112 เป็น PPM) เทียบกับ crop ของ reference

## เชื่อมต่อกับโปรเจกต์ Laravel

### 1) ตั้งค่า `.env`

```dotenv
FACE_API_URL=http://192.168.1.140:8010   # ว่าง = ปิด ใช้ AI_SERVERS เดิม
# หลายโหนด = round-robin + failover (ถ้าตั้งรายการนี้ จะใช้แทน FACE_API_URL)
FACE_API_URLS=http://192.168.1.140:8010,http://127.0.0.1:8010
FACE_API_KEY=uni-activity-ai-secret-key-2026
FACE_API_TIMEOUT=15
```

แล้ว `php artisan config:clear` (ระบบนี้ cache config — อย่าลืม clear ทุกครั้งที่แก้ .env)

### 2) ไฟล์ที่เชื่อมไว้

| ไฟล์ | บทบาท |
|---|---|
| `config/services.php` | บล็อก `face_api.{url,urls,key,timeout}` |
| `app/Services/FaceExtractApiService.php` | client: `isEnabled()`, `urls()`, `healthAll()`, `extract($bytes, $filename, $faces)`, `verify($bytes, $embedding512d, $liveness)` |
| `app/Jobs/ExtractFaceBiometricsJob.php` | คิว `ai` ใช้ face_api ก่อน → fallback AI cluster |
| `app/Services/FaceVerificationService.php` | `ensureFaceEncodings()` + แสดง face_api ใน `/api/.../metrics` (`checkServerHealth`) |
| `app/Services/ClusterHealthService.php` | node `face-api-1` ในหน้า Admin → System → Cluster |
| `app/Console/Commands/ExtractMissingFaceEncodings.php` | `face:extract-missing` ใช้ face_api ก่อน |

Fallback: ทุกจุดเรียกผ่าน `try/catch` — ถ้า S2 ล่ม/ปิดอยู่ ระบบจะกลับไปยิง AI cluster เดิมอัตโนมัติ
(log เป็น `warning`) ผู้ใช้จึงไม่เห็น error

### 2.0 โหนดที่สองบนเครื่อง web (.222) — เปิดอยู่

รัน `face_api` ตัวเดียวกับ S2 บนเครื่องที่รัน Laravel (4 คอร์) ที่พอร์ต 8010:

```bash
# build (ใช้ ncnn prebuilt เดิม) — บนเครื่อง .222
g++ -O3 -std=c++17 -fopenmp -static-openmp face_api.cpp -o face_api.local \
  -I$HOME/ncnn-prebuilt/ncnn-*/arm64-v8a/include/ncnn -I. \
  $HOME/ncnn-prebuilt/ncnn-*/arm64-v8a/lib/libncnn.a \
  $HOME/ncnn-prebuilt/ncnn-*/arm64-v8a/lib/libSPIRV.a \
  $HOME/ncnn-prebuilt/ncnn-*/arm64-v8a/lib/libglslang.a \
  -pthread -landroid -ljnigraphics -llog -ldl

mkdir -p ~/face_api && cp face_api.local ~/face_api/face_api && ln -sfn ~/face_cpp/models ~/face_api/models
cp face_cpp/deploy/start_api.sh ~/face_api/start_api.sh && cp ~/face_api/start_api.sh ~/start_api.sh
bash ~/start_api.sh 1 6 5        # workers threads max_faces — 6 เธรดเพราะใช้เครื่องเดียวกับ web
cp face_cpp/deploy/termux-boot.sh ~/.termux/boot/99-face-api.sh   # autostart (แก้เป็น 1 6 5)
```

ตรวจ: `curl -H 'X-API-Key: …' http://127.0.0.1:8010/health` → `threads=6, workers=1, uptime_s>0`

> `.env` ใช้ `http://127.0.0.1:8010` เพราะ Laravel (php-fpm) รันอยู่บนเครื่องเดียวกัน — เร็วที่สุด
> และไม่ผูกกับ IP ที่อาจเปลี่ยน ถ้าต้องเรียกจาก Debian proot container ให้ใช้ `http://192.168.1.222:8010`
> (ใน proot `127.0.0.1` ไม่ใช่ loopback ของ Termux host)

**ความสอดคล้องระหว่างโหนด (สำคัญเมื่อ web สลับโหนด):** สกัดภาพเดียวกันจาก .140 และ 127.0.0.1
ได้ embedding ตรงกันทุกบิต — `cosine = 1.000000`, `max_abs_diff = 0.000000`, L2 norm = 1
และ descriptor ที่ลงทะเบียนจากโหนด .140 ตรวจผ่านบนโหนด local ได้ `sim=1.0000`

### 2.1 พฤติกรรมของ client

- **หลายโหนด / round-robin + failover**: ตั้ง `FACE_API_URLS` แล้ว client จะ **สลับโหนดแบบ round-robin**
  (ตัวนับอยู่ใน cache key `face_api:round_robin`) เพื่อให้ web ใช้ทุกโหนดจริง ๆ และถ้าโหนดที่ถึงคิวยิงไม่สำเร็จ
  จะไล่โหนดถัดไปให้ทันที — `node` ใน response บอกเสมอว่าโหนดไหนตอบ; ถ้าทุกโหนดล่มจะ throw พร้อม error ของแต่ละโหนด
- **รูปที่ stb_image อ่านไม่ได้** (Progressive/CMYK JPEG, HEIC): แปลงเป็น JPEG ด้วย GD ก่อนส่ง;
  ถ้าโหนดยังตอบ `decode failed` จะแปลงใหม่แล้วลองซ้ำอีกหนึ่งรอบ (อัตโนมัติ)
- **คุณภาพภาพ vs การปลอมแปลง**: `liveness_checks.rejection_reason` ถูกแปลงเป็น
  `rejection_kind` = `quality` (`face_too_small`, `image_blurry`, `bad_exposure`, `low_color_variance`, `no_face`),
  `mismatch` (ใบหน้าไม่ตรง) หรือ `spoof` (`screen_moire_detected`, `glass_glare_detected`, `color_gamut_rejected`)
  พร้อม `rejection_message` ภาษาไทย — หน้าเช็คอินจึงบอกผู้ใช้ได้ถูกว่า "ถ่ายใหม่ให้ชัด/ใกล้ขึ้น"
  หรือ "ตรวจพบภาพปลอม" แทนที่จะกล่าวหาว่าใช้ภาพปลอมทุกกรณี

### 3) ตรวจว่าเชื่อมติด

```bash
php artisan tinker --execute='$f = app(App\\Services\\FaceExtractApiService::class); print_r($f->health());'
php artisan face:extract-missing --dry-run --limit=3     # ดูว่าจะสกัดใครบ้าง (ไม่เขียน DB)
php artisan face:extract-missing --limit=50             # สกัดจริง (เขียน face_descriptor)
```

ผลทดสอบจริงบนเครื่อง app (S1 → S2, ภาพโปรไฟล์ 1.1 MB):
`success=true faces=1 dim=512 det_score=0.886 server_ms=1605 client_ms=1939`
และหน้า Cluster แสดง `face-api-1 http://192.168.1.140:8010 HEALTHY`

### 4) ผลตรวจสอบล่าสุด (ทั้งเส้นทาง web → face_api)

| เส้นทาง | ผล |
|---|---|
| `FaceExtractApiService::healthAll()` | `1 node healthy` — `scrfd10g+arcface+mobilefacenet512`, `avg_ms≈920` |
| enroll: `ExtractFaceBiometricsJob` (user #7, ภาพ `.webp`) | `Successfully extracted ... via face-api:http://192.168.1.140:8010`, descriptor 512D norm=1.0 |
| verify: `FaceVerificationController@verify` (user #3) | `http=200 is_match=true pct=100 live=true node=face-api:http://192.168.1.140:8010` |
| failover (โหนดแรกตาย) | ยิงสำเร็จที่โหนดถัดไป, `node` ชี้โหนดที่ใช้จริง |
| ทุกโหนดล่ม | `RuntimeException: face_api extract failed on all nodes: ...` (มี error ของแต่ละโหนด) |
| `GET /api/face/metrics`, `ClusterHealthService` | แสดง node `face-api-*` พร้อม pipeline/avg_ms/uptime |
| 2 โหนด (`.140` + `127.0.0.1`) | `healthAll()` = 2/2 healthy — `avg_ms≈968` (remote) / `≈1367` (local) |
| round-robin 4 คำขอ | `127.0.0.1 ×2`, `192.168.1.140 ×2` — สลับกันจริง, ทุกคำขอได้ 512D |
| cluster dashboard | `face-api-1 HEALTHY (.140)`, `face-api-2 HEALTHY (127.0.0.1)`, `web-api HEALTHY` → `HEALTHY 3/3` |

> **Python AI node เดิม (`.45:8001`) ถูกลบออกจากระบบแล้ว** — ลบ `AI_SERVERS` / `AI_SERVER_URL` ออกจาก `.env`,
> ตั้ง default ของ `services.ai_server.url` เป็น `''` และ `AiLoadBalancerService::getNodes()` คืน `[]` เมื่อไม่ได้ตั้งค่า
> (ก่อนหน้านี้ fallback ฮาร์ดโค้ดไป `127.0.0.1:8001`) → ไม่มี node ผีค้างในมอนิเตอร์/หน้า Cluster อีก
> เหลือ face_api เป็นบริการใบหน้าเดียว; ถ้าต้องการ fallback Python กลับมา ให้เติม `AI_SERVERS=` ใน `.env`

## มอนิเตอร์ (:9999) — เช็คสถานะ API จริงแล้ว

เดิม `go-monitor` ตรวจแค่ TCP `127.0.0.1:8001` และหน้า Cluster ใช้ข้อมูลตัวอย่าง (hardcode)
ตอนนี้ `collector.realAICluster()` ยิง `GET /health` จริงของทุก API โดยอ่านรายการจาก `.env`:

| node | แหล่งที่มา | ข้อมูลที่แสดง |
|---|---|---|
| `face-api-1`, `face-api-2` | `FACE_API_URLS` (หรือ `FACE_API_URL`) + `FACE_API_KEY` | status, latency, pipeline, avg_ms, uptime_s, requests/errors, threads, models |
| `ai-node-N` (ถ้าตั้ง `AI_SERVERS`) | `AI_SERVERS` + `AI_SERVER_KEY` | status, latency, models, pipeline |
| `web-api` | `http://127.0.0.1:8080/health` (override ด้วย `MONITOR_WEB_API`) | status + latency (Laravel + DB/redis/queue) |

- ผลลัพธ์ cache 5 วินาที (key ในหน่วยความจำ) — dashboard โหลดเร็วและไม่ยิงถี่เกินไป
- `cluster_state` เป็น `HEALTHY` (ทุกตัวตอบ) / `DEGRADED` (บางตัวตอบ) / `CRITICAL` (ไม่ตอบเลย)
- แสดงผลที่ `GET /api/cluster/metrics` (`data.ai_cluster`) และ `GET /api/stats` (`ai_cluster`)
- การ์ดในหน้า Cluster แสดง `Pipeline`, `Latency เฉลี่ย`, `Uptime`, `Requests / Errors` และ `ประเภท API`
  (เดิมโค้ด `node.models.join()` จะ crash ถ้า `models` เป็น object — แก้ให้รองรับทั้ง array/object แล้ว)
- **การ์ด Services Status บนหน้า Dashboard** (`services/checker.go`) ก็เช็ค API จริงแล้ว
  (เดิมดูแค่ TCP `127.0.0.1:8001` ทำให้ AI service ขึ้น Stopped ตลอดทั้งที่ face_api ทำงาน):
  `Face API (<host>:<port>)` ต่อโหนด, `AI Node (<host>:<port>)`, `Web API (Laravel)`
  โดยแสดง `Running (x ms)` / `Stopped (timeout|refused|HTTP n)` และ cache 5 วินาที
  (collector เรียกทุก 2.5 วินาทีเมื่อมี client เปิด dashboard — ไม่ยิง API ถี่เกิน)
- **ปุ่มควบคุม (▶ start / ■ stop / ↻ restart) ต่อการ์ด** ส่ง `POST /api/control {target,action}`:
  `face_api` (เฉพาะโหนด `127.0.0.1:8010` → รัน `~/start_api.sh 1 6 5`), `php_fpm`, `nginx`, `queue_worker`, `reverb`, `valkey`, `postgres` ฯลฯ
  โหนด `192.168.1.140` ไม่มีปุ่ม (ไม่มี SSH control จากมอนิเตอร์) — ใช้กรณีนี้เป็น fallback อัตโนมัติแทน
- **กราฟ latency ย้อนหลัง** ต่อโหนด ในการ์ด Cluster (`node.history`): ring buffer 120 จุด
  (≈10 นาที ที่คาบ 5 วินาที) เก็บในหน่วยความจำของมอนิเตอร์ — รีสตาร์ทมอนิเตอร์ = กราฟเริ่มใหม่
  แกน x เป็นเวลาจริง (ช่วงที่มอนิเตอร์หลับจึงเห็นเป็นช่องว่าง) เส้นสีเขียว = HEALTHY, แดง = ล้ม
  ข้อมูลเดียวกับ `GET /api/cluster/metrics` → `data.ai_cluster.nodes[].history[{t,ms,ok}]`

ตรวจเร็ว ๆ:

```bash
curl -s http://127.0.0.1:9999/api/cluster/metrics | python3 -m json.tool | grep -A 4 cluster_state
```

ผลจริงล่าสุด: `HEALTHY 3/3` — `face-api-1 HEALTHY (13 ms)`, `face-api-2 HEALTHY (3 ms)`, `web-api HEALTHY (88 ms)`

> หลังแก้โค้ด/`.env` ต้อง `php artisan config:clear` และ **รีสตาร์ท `php artisan queue:work`**
> (worker เป็น process ยาว — ถ้าไม่รีสตาร์ทจะยังใช้โค้ดเก่าในหน่วยความจำ)

> หมายเหตุ: `embedding_128d` ยังเป็น `null` จนกว่าจะ fit PCA 128D ใหม่ (ดู `ai_service/FDX_MIGRATION.md`)
> — โค้ดฝั่ง Laravel รองรับ null อยู่แล้ว (`face_descriptor_js` จะคงเป็น null)
