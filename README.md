# University Activity Management & Verification System

### ระบบบริหารจัดการกิจกรรมนักศึกษา + เช็คชื่อด้วย AI สแกนใบหน้าและ Real-Time WebSockets

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)](https://postgresql.org)
[![Redis](https://img.shields.io/badge/Redis-Cache%20%26%20Queue-DC382D?style=for-the-badge&logo=redis&logoColor=white)](https://redis.io)
[![Reverb](https://img.shields.io/badge/WebSockets-Reverb-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com/docs/reverb)
[![FastAPI](https://img.shields.io/badge/AI%20Service-FastAPI-009688?style=for-the-badge&logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com)
[![PHPUnit](https://img.shields.io/badge/Tests-PHPUnit%2011-3C9CD7?style=for-the-badge&logo=phpunit&logoColor=white)](https://phpunit.de)

---

## ภาพรวมโปรเจกต์

แพลตฟอร์มสำหรับมหาวิทยาลัยที่รวมงานบริหารกิจกรรมนักศึกษาไว้ในระบบเดียว ตั้งแต่การสร้างกิจกรรม
การลงทะเบียน การเช็คชื่อด้วยใบหน้าและ QR แบบไดนามิก ไปจนถึงการออกใบรับรองการเข้าร่วมกิจกรรม
ออกแบบให้ทำงานแบบ **server-authoritative** (การตัดสินใจเรื่องยืนยันตัวตน/สิทธิ์ทั้งหมดอยู่ฝั่ง server)
รองรับการอัปเดตแบบ real-time และมีชุดทดสอบอัตโนมัติครอบคลุมฟีเจอร์หลัก

**สิ่งที่โปรเจกต์นี้แสดงให้เห็น (สำหรับพอร์ตโฟลิโอ):**

- ออกแบบและพัฒนา full-stack application ขนาดใหญ่ (Laravel + PostgreSQL + Redis + WebSockets)
- ทำงานร่วมกับ AI microservice (Python/FastAPI + onnxruntime) ผ่าน API ที่มี API-key กำกับ
- ออกแบบระบบยืนยันตัวตนหลายชั้น (Sanctum, OTP, RBAC, Audit log, SSO/OIDC)
- เขียน automated tests ด้วย PHPUnit ครอบคลุม business logic และ security (51 Feature + 2 Unit)
- ดูแล deployment บน environment จำกัด (Android/Termux) พร้อมเอกสารสถาปัตยกรรมครบ

---

## คุณสมบัติหลัก

**1. ยืนยันตัวตนด้วยใบหน้า (AI Face Verification)**

- ตรวจจับใบหน้า + จัดตำแหน่ง (SCRFD + alignment) และสกัด embedding 512 มิติ (ArcFace / MobileFaceNet)
- Passive liveness heuristic (LBP, FFT, eye landmarks, color variance) คัดกรองภาพถ่าย/หน้าจอ
- ประมวลผลฝั่ง server เท่านั้น — client ไม่ถือ embedding หรือผลตัดสินใจ

**2. Real-Time Engine**

- Laravel Reverb (WebSockets) อัปเดตสถานะเช็คชื่อ จำนวนผู้เข้าร่วม และแชตแบบทันที
- Redis queue worker ประมวลผลงานเบื้องหลัง (แจ้งเตือน, ออกเอกสาร) แบบ asynchronous
- LINE Official Account แจ้งผลการลงทะเบียน/เข้างานให้นักศึกษา

**3. ระบบเช็คชื่อกิจกรรม**

- Dynamic QR Code เปลี่ยนตามเวลา กันการนำ QR ไปใช้ซ้ำ
- Walk-in registration สำหรับผู้ไม่ได้ลงทะเบียนล่วงหน้า
- Selfie verification พร้อมบันทึกพิกัด (geolocation) และ risk scoring

**4. ความปลอดภัยและการกำกับดูแล**

- RBAC (System Admin / Staff / Student), Faculty-scoped authorization
- Audit logging ทุกการเข้าถึง/แก้ไขข้อมูลสำคัญ
- Sanctum + OTP, Security headers, rate limiting, SQL binding safety

**5. รายงานและเอกสารทางการ**

- ใบรับรองการเข้าร่วมกิจกรรม (PDF, ฟอนต์ไทย Sarabun), Transcript
- นำเข้า/ส่งออกข้อมูลนักศึกษาและผลเข้าร่วมกิจกรรม (Excel)

**6. ฟีเจอร์เสริม**

- ปฏิทินกิจกรรม, ค้นหา/กรอง, บุ๊กมาร์ก, Recommendation engine
- i18n ไทย/อังกฤษ, PromptPay QR, waitlist จัดคิวอัตโนมัติ, SSO (OAuth2/OIDC)

---

## สถาปัตยกรรม (สรุป)

- **Web layer:** Laravel (Blade + REST API) เสิร์ฟผ่าน Nginx/FrankenPHP
- **Data layer:** PostgreSQL 16 เป็น source of truth, Redis สำหรับ cache/session/queue
- **Realtime layer:** Laravel Reverb (WebSocket) กระจาย event ผ่าน Redis pub/sub
- **AI layer:** FastAPI service (SCRFD detection + ArcFace/MobileFaceNet 512D embedding + passive liveness) — Laravel เรียกผ่าน HTTP พร้อม API key
- **Jobs:** Redis queue worker สำหรับแจ้งเตือน LINE / ออกเอกสาร / นำเข้าข้อมูล

ไดอะแกรมสถาปัตยกรรมแบบเต็ม (SVG: system plan, realtime flow, ER, sequence, use-case)
ถูกจัดเก็บไว้ที่ [`GitNonta/backup`](https://github.com/GitNonta/backup) → `svg/`, `docs/diagrams/`

---

## Tech Stack

| ส่วนประกอบ | เทคโนโลยี |
|:---|:---|
| Backend | Laravel 13.x, PHP 8.2+ |
| Database | PostgreSQL 16 (80 migrations) |
| Cache / Queue | Redis |
| Real-Time | Laravel Reverb (WebSockets) |
| Frontend | Blade, Tailwind CSS, Alpine.js, Vite |
| AI Microservice | Python 3.11, FastAPI, onnxruntime (SCRFD + ArcFace/MobileFaceNet 512D) |
| Auth | Laravel Sanctum, OTP, OIDC SSO |
| Testing | PHPUnit 11 (Feature + Unit) |
| Infra | Nginx, Cloudflare Tunnel, Docker (optional) |

---

## โครงสร้างโปรเจกต์

```text
uni-activity/
├── app/                  # Controllers, Models, Services, Jobs, Policies
├── bootstrap/  config/   # Laravel bootstrap และ configuration
├── database/             # migrations (80), seeders, factories
├── docs/                 # GitHub Pages (หน้า landing/callback)
├── public/               # entrypoint + assets
├── resources/            # Blade views, CSS/JS (Vite)
├── routes/               # web / api / console routes
├── storage/              # runtime storage (logs, cache, views)
├── ai_service/           # FastAPI face-verification service (Python)
├── face_dx/  face_cpp/   # face embedding engine (Direct3D11 / ncnn) + tooling
├── scripts/              # ops & maintenance scripts
└── docker-compose.yml    # optional container stack
```

---

## เริ่มต้นใช้งาน

**ความต้องการของระบบ:** PHP 8.2+, Composer, Node.js 20+, PostgreSQL 16, Redis

```bash
# 1) ติดตั้ง dependencies
composer install
npm install

# 2) ตั้งค่า environment
cp .env.example .env
php artisan key:generate

# 3) เตรียมฐานข้อมูล
php artisan migrate --seed

# 4) build frontend
npm run build

# 5) รันบริการ
php artisan serve                       # web app
php artisan reverb:start --port=8082    # websocket
php artisan queue:work redis            # background jobs
```

**AI service (ไม่บังคับ — ใช้เมื่อต้องการฟีเจอร์สแกนใบหน้า):**

```bash
cd ai_service
pip install -r requirements.txt
python server.py        # FastAPI บนพอร์ตภายใน LAN
```

ตั้งค่าใน `.env` ของ Laravel: `AI_SERVICE_URL`, `AI_SERVER_KEY`

---

## การทดสอบ

ชุดทดสอบ PHPUnit (58 ไฟล์ — Feature 51 / Unit 2) ถูกจัดเก็บแยกไว้ที่
[`GitNonta/backup`](https://github.com/GitNonta/backup) เพื่อให้ repo นี้เหลือเฉพาะ
production code; ต้องการรันเทสให้ copy `tests/` + `phpunit.xml` กลับเข้ามาก่อนแล้วรัน:

```bash
php artisan test
```

หัวข้อที่ชุดทดสอบครอบคลุม: authorization/policies, check-in race conditions และ
transaction atomicity, QR token rotation, face-verification security bypass,
queue architecture, realtime events, backup system, PDPA/biometric encryption

---

## เอกสาร

เอกสารทางเทคนิค (API reference, testing guide, face system, InsightFace architecture,
Reverb/chat setup, network setup, flowcharts) และไดอะแกรมทั้งหมด ถูกย้ายไปเก็บที่
[`GitNonta/backup`](https://github.com/GitNonta/backup) เพื่อให้ repo นี้เหลือเฉพาะโค้ด
— ดูสารบัญทั้งหมดได้ใน `README.md` ของ repo นั้น

---

## Repositories

| Repo | เนื้อหา |
|---|---|
| **uni-activity** (นี้) | ระบบหลัก Laravel + AI service + face engine |
| [GitNonta/monitor](https://github.com/GitNonta/monitor) | ชุด monitoring (Go service + React dashboard) แยกออกไปดูแลต่างหาก |
| [GitNonta/backup](https://github.com/GitNonta/backup) | เอกสาร ไดอะแกรม test suite และไฟล์ ops ที่แยกออกจาก repo หลัก |

---

<p align="center">
  <strong>University Activity Management & Verification System</strong><br/>
  Laravel · PostgreSQL · Redis · Reverb · FastAPI · PHPUnit
</p>
