# AGENTS.md — Laravel PHP Project Rules
# ไฟล์นี้ถูกอ่านโดย Antigravity, Cursor, และ Claude Code อัตโนมัติ

## Project Stack
- **Backend:** Laravel 11, PHP 8.2+
- **Database:** PostgreSQL (primary), Redis (cache/queue), Cassandra (archive)
- **Real-time:** Laravel Reverb (WebSocket)
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

## Testing Rules

- ทุก feature ต้องมี Feature Test
- ใช้ `Event::fake()` เมื่อ test ที่มี broadcasting
- ใช้ `RefreshDatabase` trait ทุก test class
- Test naming: `test_[subject]_can/cannot_[action]()`
