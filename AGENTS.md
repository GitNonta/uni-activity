# AGENTS.md — Laravel PHP Project Rules
# ไฟล์นี้ถูกอ่านโดย Antigravity, Cursor, และ Claude Code อัตโนมัติ

## Project Stack
- **Backend:** Laravel 13, PHP 8.2+
- **Database:** PostgreSQL (primary core database)
- **Cache / Queue / Locks:** Valkey (drop-in แทน Redis) / Dragonfly
- **Real-time:** Laravel Reverb (WebSocket)
- **AI Microservice:** Python FastAPI (InsightFace, OpenCV)
- **Auth:** Laravel Sanctum
- **Queue:** Redis

---

## Coding Standards (บังคับทุกไฟล์)

- ใช้ `declare(strict_types=1);` ทุกไฟล์ PHP
- ทุก method ต้องมี type hints ทั้ง parameter และ return type
- ใช้ `readonly` สำหรับ constructor property ที่ไม่ถูกเปลี่ยนแปลง
- ใช้ named arguments เมื่อ method มี parameter มากกว่า 3 ตัว

## Architecture Rules

- **Controller** — บาง ไม่มี business logic ไม่มี DB query ตรงๆ
- **Validation** — อยู่ใน Form Request เท่านั้น ห้าม `$request->validate()` ใน Controller
- **Response** — ใช้ API Resource (`JsonResource`) สำหรับทุก JSON response
- **Database** — อยู่ใน Repository เท่านั้น Controller ไม่แตะ Model โดยตรง
- **Transactions** — ใช้ `DB::transaction()` ทุกครั้งที่มีการเขียนข้อมูลหลายขั้นตอน
- **N+1** — ต้อง eager load ด้วย `with()` เสมอก่อน loop collection

## Security Rules

- ห้าม hardcode credentials, keys, URLs ใดๆ — ใช้ `env()` เสมอ
- ทุก route ต้องอยู่ใน `auth:sanctum` middleware
- ทุก Controller method ต้องมี `$this->authorize()` หรือใช้ Policy ผ่าน Form Request
- Channel authorization ต้องกำหนดใน `routes/channels.php` เสมอ

## Broadcasting (Reverb) Rules

- Event ที่ broadcast ต้อง implement `ShouldBroadcast`
- Frontend ใช้ `import.meta.env.VITE_REVERB_*` ไม่ใช่ hardcode
- `.listen()` ต้องมี dot นำหน้าชื่อ event: `.listen('.MessageSent', ...)`
- `laravel-echo` และ `pusher-js` ต้องอยู่ใน `dependencies` (ไม่ใช่ devDependencies)

## UI & Graphics Rules (SVG เท่านั้น — บังคับทุกที่)

- **ใช้ SVG เท่านั้น** สำหรับทุกไอคอน (Icons), กราฟิก (Graphics), สัญลักษณ์สถานะ, และองค์ประกอบ UI ในระบบ
- **ถ้าระบบแก้อะไรหรือเพิ่มอะไรในส่วน UI, Web, Dashboard, หรือ Template ต้องใช้ SVG เท่านั้น**
- **ห้ามใช้ Emoji** เป็นไอคอน UI หรือสัญลักษณ์แสดงผลโดยเด็ดขาด (เช่น ห้ามใช้ 📱, 💻, 🖥️, 🔍, ⏳, ⏸, ▶, ✕ — ให้เขียนเป็น SVG เสมอ)
- **ห้ามใช้ Raster Image (PNG, JPG, GIF)** สำหรับไอคอน UI
- **ห้ามใช้ Icon Font** (เช่น FontAwesome webfonts) — ใช้ Inline SVG หรือ SVG component เสมอ เพื่อความคมชัด น้ำหนักเบา และปรับแต่งผ่าน CSS ได้สมบูรณ์

## Testing Rules

- ทุก feature ต้องมี Feature Test
- ใช้ `Event::fake()` เมื่อ test ที่มี broadcasting
- ใช้ `RefreshDatabase` trait ทุก test class
- Test naming: `test_[subject]_can/cannot_[action]()`

## Git & Deployment Rules (Commit & Push ทุกครั้ง — บังคับ)

- **ต้อง Commit และ Push ทุกครั้งที่ทำงานเสร็จสิ้น**: ทุกครั้งที่ทำงานเสร็จ, เพิ่มฟีเจอร์, แก้ไขบั๊ก หรือปรับปรุงระบบ และผ่านการทดสอบแล้ว ระบบต้องรัน `git add`, `git commit` ด้วยข้อความที่สื่อความหมายชัดเจน และ `git push` ขึ้น repository ทุกครั้ง ห้ามค้างการเปลี่ยนแปลงไว้บน local เด็ดขาด
