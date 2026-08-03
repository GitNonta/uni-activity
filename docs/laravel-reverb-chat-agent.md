# AI Agent Prompt — Laravel Reverb Real-time Chat System
# ชุดคำสั่งสำหรับ AI Agent สร้างระบบแชท Real-time ครบวงจร

---

## 🎯 MISSION STATEMENT

คุณคือ Senior Laravel Developer ที่เชี่ยวชาญด้าน WebSocket และ Real-time systems
ภารกิจของคุณคือสร้างระบบแชทแบบ Real-time ที่สมบูรณ์ด้วย Laravel Reverb
โดยไม่ต้องรีโหลดหน้าเว็บเพื่ออัพเดตข้อความ

---

## 📋 SCOPE — ขอบเขตที่ AI Agent ต้องทำ

### ✅ IN SCOPE (ทำ)

1. Database & Migrations
2. Models & Relationships
3. Events & Broadcasting
4. Controllers & API Routes
5. Channel Authorization
6. Frontend Integration (Echo + Pusher JS)
7. Queue Worker Configuration
8. Reverb Server Configuration
9. Policies & Authorization
10. Tests (Feature + Unit)

### ❌ OUT OF SCOPE (ไม่ทำ)

- การตั้งค่า DNS / SSL certificate
- CI/CD Pipeline
- Docker / Kubernetes configuration
- Payment / Subscription system
- Push Notifications (Mobile)
- File/Image upload ใน chat (เว้นแต่ระบุ)

---

## 🗂️ PHASE 1 — Database & Models

### Prompt 1.1 — Migrations

```
สร้าง Laravel migrations สำหรับระบบแชทต่อไปนี้:

1. `rooms` table:
   - id (ulid primary key)
   - name (string, nullable — null = direct message)
   - type (enum: 'direct', 'group') default 'direct'
   - created_by (foreignId → users)
   - timestamps + softDeletes

2. `room_user` (pivot) table:
   - room_id, user_id
   - role (enum: 'member', 'admin') default 'member'
   - last_read_at (timestamp, nullable)
   - joined_at (timestamp)

3. `messages` table:
   - id (ulid primary key)
   - room_id (foreignId → rooms, cascade delete)
   - user_id (foreignId → users, cascade set null)
   - body (text)
   - type (enum: 'text', 'system') default 'text'
   - read_by (json, default [])
   - deleted_at (softDelete)
   - timestamps

รัน migration และยืนยันว่าไม่มี error
```

---

### Prompt 1.2 — Models

```
สร้าง Eloquent Models พร้อม relationships ครบถ้วน:

1. `Room` model:
   - HasMany messages
   - BelongsToMany users (ผ่าน room_user) พร้อม withPivot(['role','last_read_at','joined_at'])
   - BelongsTo creator (User)
   - Scope: forUser($userId) — คืน rooms ที่ user นี้เป็นสมาชิก
   - Method: isDirectMessage() → bool
   - Method: getOtherUser(User $user) → User (สำหรับ direct message)

2. `Message` model:
   - BelongsTo room
   - BelongsTo user
   - Cast read_by เป็น array
   - Method: markReadBy(int $userId): void — เพิ่ม userId ใน read_by แล้ว save
   - Scope: unreadFor(int $userId) — ข้อความที่ userId ยังไม่ได้อ่าน
   - Global scope: ไม่แสดงข้อความที่ถูก softDelete (แสดงเป็น "ข้อความถูกลบแล้ว")

3. User model — เพิ่ม:
   - HasMany messages
   - BelongsToMany rooms

สร้าง Factory + Seeder สำหรับแต่ละ model ด้วย
```

---

## 📡 PHASE 2 — Broadcasting Events

### Prompt 2.1 — Events

```
สร้าง Broadcasting Events ต่อไปนี้ใน app/Events/:

1. `MessageSent`:
   - implements ShouldBroadcast, ShouldBroadcastNow (ไม่ผ่าน queue — ส่งทันที)
   - Constructor: (public Message $message)
   - broadcastOn(): PrivateChannel('chat.{room_id}')
   - broadcastAs(): 'message.sent'
   - broadcastWith(): คืน array ประกอบด้วย:
     {
       id, body, type,
       user: { id, name, avatar_url },
       room_id, created_at (ISO 8601),
       is_mine: false  // client จะ override เอง
     }

2. `MessageDeleted`:
   - broadcastOn(): PrivateChannel('chat.{room_id}')
   - broadcastAs(): 'message.deleted'
   - broadcastWith(): { message_id, room_id }

3. `UserTyping`:
   - implements ShouldBroadcast
   - broadcastOn(): PresenceChannel('chat.{room_id}')
   - broadcastAs(): 'user.typing'
   - broadcastWith(): { user_id, name, is_typing }
   - Queue: เปิดใช้ queue เพื่อป้องกัน flood

4. `RoomCreated`:
   - broadcastOn(): PrivateChannel('user.{user_id}') สำหรับทุก member
   - broadcastAs(): 'room.created'

ตรวจสอบว่าทุก event ใช้ ShouldBroadcast interface และ import ถูกต้อง
```

---

## 🔐 PHASE 3 — Authorization

### Prompt 3.1 — Channel Authorization

```
กำหนด channel authorization ใน routes/channels.php:

1. Private Channel 'chat.{roomId}':
   - ตรวจสอบว่า auth user เป็นสมาชิกของ room นั้น
   - คืน true/false

2. Presence Channel 'chat.{roomId}':
   - ตรวจสอบ membership เหมือนข้างต้น
   - คืน array ข้อมูล user: { id, name, avatar_url }
   - ใช้สำหรับแสดง "กำลังพิมพ์..." และ "ออนไลน์"

3. Private Channel 'user.{userId}':
   - ตรวจสอบว่า auth()->id() === (int)$userId เท่านั้น

เพิ่ม middleware 'auth:sanctum' ใน BroadcastServiceProvider
```

---

### Prompt 3.2 — Policies

```
สร้าง Laravel Policies:

1. `RoomPolicy`:
   - view: user ต้องเป็นสมาชิกของ room
   - create: user ต้อง authenticate แล้ว
   - update: user ต้องเป็น admin ของ room
   - delete: user ต้องเป็น creator หรือ admin
   - addMember: user ต้องเป็น admin

2. `MessagePolicy`:
   - view: user ต้องเป็นสมาชิกของ room ที่ message อยู่
   - create: user ต้องเป็นสมาชิกของ room
   - delete: user ต้องเป็นเจ้าของ message หรือ room admin

Register policies ใน AuthServiceProvider
```

---

## 🛣️ PHASE 4 — Controllers & Routes

### Prompt 4.1 — API Controllers

```
สร้าง API Controllers ต่อไปนี้ (Resource Controller):

1. `RoomController`:
   - index(): คืน rooms ที่ auth user เป็นสมาชิก (paginate 20)
   - store(): สร้าง room ใหม่ + เพิ่ม creator เป็น admin + broadcast RoomCreated ไปทุก member
   - show(): คืน room พร้อม members และ last message
   - destroy(): soft delete room (admin เท่านั้น)

2. `MessageController`:
   - index(Room $room): คืน messages ของ room (cursor paginate 30, เรียงจากใหม่ไปเก่า)
   - store(Room $room): บันทึก message + broadcast MessageSent
   - destroy(Message $message): soft delete + broadcast MessageDeleted

3. `TypingController`:
   - store(Room $room): broadcast UserTyping event (throttle 1 ครั้ง/วินาที ต่อ user)
   - ไม่ต้อง save ลง database

4. `ReadReceiptController`:
   - store(Room $room): อัพเดต last_read_at ใน pivot + markReadBy สำหรับ messages ที่ยังไม่อ่าน

ทุก Controller ใช้ Form Request สำหรับ validation
ทุก response ใช้ API Resource (JsonResource)
```

---

### Prompt 4.2 — Routes

```
กำหนด API routes ใน routes/api.php:

middleware(['auth:sanctum', 'throttle:api']):

GET    /rooms                     → RoomController@index
POST   /rooms                     → RoomController@store
GET    /rooms/{room}              → RoomController@show
DELETE /rooms/{room}              → RoomController@destroy

GET    /rooms/{room}/messages     → MessageController@index
POST   /rooms/{room}/messages     → MessageController@store
DELETE /messages/{message}        → MessageController@destroy

POST   /rooms/{room}/typing       → TypingController@store
POST   /rooms/{room}/read         → ReadReceiptController@store

POST   /rooms/{room}/members      → RoomMemberController@store   (เพิ่มสมาชิก)
DELETE /rooms/{room}/members/{user} → RoomMemberController@destroy (ลบสมาชิก)

เพิ่ม route model binding และ scoping (room → message)
เพิ่ม rate limiting: messages endpoint ไม่เกิน 60 ครั้ง/นาที
```

---

## 💻 PHASE 5 — Frontend

### Prompt 5.1 — Laravel Echo Setup

```
ตั้งค่า Laravel Echo สำหรับ Reverb ใน resources/js/bootstrap.js:

1. Import laravel-echo และ pusher-js
2. กำหนด window.Echo ด้วย config:
   - broadcaster: 'reverb'
   - key: VITE_REVERB_APP_KEY (จาก .env)
   - wsHost: VITE_REVERB_HOST
   - wsPort: VITE_REVERB_PORT
   - wssPort: VITE_REVERB_PORT
   - forceTLS: false (สำหรับ dev), true (prod)
   - enabledTransports: ['ws', 'wss']
3. เพิ่ม authEndpoint: '/broadcasting/auth'
4. เพิ่ม auth headers: Authorization Bearer token (จาก Sanctum)

สร้าง composable/hook `useChatRoom(roomId)` ที่:
- subscribe channel เมื่อ mount
- unsubscribe เมื่อ unmount
- expose: messages, sendMessage(), startTyping(), typingUsers
```

---

### Prompt 5.2 — Chat UI Component

```
สร้าง Chat UI Component (Vue 3 Composition API หรือ Alpine.js ตามที่ใช้ใน project):

Component: <ChatRoom :room-id="roomId" />

ฟีเจอร์ที่ต้องมี:
1. แสดงรายการข้อความ — scroll อัตโนมัติเมื่อมีข้อความใหม่
2. Load more (cursor pagination) เมื่อ scroll ขึ้นด้านบน
3. กล่องพิมพ์ข้อความ — กด Enter ส่ง, Shift+Enter ขึ้นบรรทัดใหม่
4. แสดง "กำลังพิมพ์..." เมื่อมี user อื่น typing (ซ่อนหลัง 3 วินาที)
5. แสดง read receipt (เครื่องหมายอ่านแล้ว)
6. แสดงข้อความ "ถูกลบแล้ว" สำหรับ soft deleted messages
7. Optimistic UI — แสดงข้อความทันทีก่อน server ยืนยัน

Event listeners:
- Echo.private('chat.{roomId}').listen('.message.sent', handler)
- Echo.private('chat.{roomId}').listen('.message.deleted', handler)
- Echo.join('chat.{roomId}').here(setOnlineUsers).joining(addUser).leaving(removeUser)
- Echo.join('chat.{roomId}').listenForWhisper('typing', showTyping)  // ใช้ whisper แทน event ถ้าต้องการประหยัด server

อย่าลืม cleanup listeners ใน onUnmounted()
```

---

## ⚙️ PHASE 6 — Configuration

### Prompt 6.1 — Queue & Reverb Config

```
ตั้งค่า Queue และ Reverb สำหรับระบบแชท:

1. config/broadcasting.php:
   - ตั้งค่า connections.reverb ด้วยค่าจาก env
   - กำหนด default: env('BROADCAST_CONNECTION', 'reverb')

2. config/queue.php:
   - ใช้ Redis หรือ database เป็น queue driver
   - สร้าง queue ชื่อ 'broadcasting' สำหรับ events โดยเฉพาะ

3. .env ค่าที่ต้องมี:
   BROADCAST_CONNECTION=reverb
   QUEUE_CONNECTION=redis (หรือ database)
   REVERB_APP_ID=
   REVERB_APP_KEY=
   REVERB_APP_SECRET=
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8080
   REVERB_SCHEME=http
   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"

4. สร้าง Supervisor config ใน /etc/supervisor/conf.d/:
   - reverb-server.conf: รัน php artisan reverb:start
   - queue-worker.conf: รัน php artisan queue:work --queue=broadcasting,default

ทดสอบโดยรัน php artisan reverb:start แล้วส่ง test event
```

---

## 🧪 PHASE 7 — Testing

### Prompt 7.1 — Feature Tests

```
สร้าง Feature Tests ครอบคลุมทุก endpoint:

1. `RoomTest`:
   - test_user_can_create_room()
   - test_user_can_list_their_rooms()
   - test_user_cannot_access_room_they_are_not_member_of()
   - test_admin_can_delete_room()
   - test_member_cannot_delete_room()

2. `MessageTest`:
   - test_member_can_send_message()
   - test_message_is_broadcast_on_send() — ใช้ Event::fake() + assertDispatched
   - test_user_can_delete_own_message()
   - test_user_cannot_delete_others_message()
   - test_messages_are_paginated()
   - test_deleted_message_shows_placeholder()

3. `BroadcastTest`:
   - test_message_sent_event_broadcasts_on_correct_channel()
   - test_broadcast_payload_contains_required_fields()
   - test_typing_event_is_throttled()

ใช้ Event::fake(), Queue::fake(), และ RefreshDatabase trait
สร้าง ChatRoomFactory สำหรับ test setup
```

---

## 🔍 PHASE 8 — Final Checklist & Optimization

### Prompt 8.1 — Review & Optimization

```
ตรวจสอบและ optimize ระบบที่สร้างแล้ว:

1. N+1 Query Check:
   - ทุก query ที่ return collection ต้อง eager load relationships ที่จำเป็น
   - ใช้ Laravel Telescope หรือ Debugbar ตรวจสอบ

2. Indexing:
   - เพิ่ม index ใน messages: (room_id, created_at)
   - เพิ่ม index ใน room_user: (user_id, room_id)

3. API Resources:
   - ตรวจสอบว่าไม่มี sensitive data (password, token) หลุดออกไป
   - เพิ่ม conditional loading ด้วย whenLoaded()

4. Security:
   - ตรวจสอบ channel authorization ครอบคลุมทุก channel
   - ตรวจสอบ Policy ถูก apply ทุก controller method
   - ตรวจสอบ rate limiting บน typing และ message endpoints
   - Sanitize message body ป้องกัน XSS

5. Error Handling:
   - ถ้า WebSocket disconnect → แจ้ง user ว่า "การเชื่อมต่อหลุด กำลังเชื่อมต่อใหม่..."
   - Echo จัดการ reconnect อัตโนมัติ — ตรวจสอบว่าเปิดใช้งาน

6. Performance:
   - Message list ใช้ cursor pagination แทน offset pagination
   - Cache room membership ด้วย Redis (TTL 5 นาที)
   - ถ้า room มี member > 50 คน ให้ใช้ PresenceChannel อย่างระมัดระวัง

รายงานสิ่งที่ตรวจพบและแก้ไขทั้งหมด
```

---

## 📁 FILE STRUCTURE — โครงสร้างไฟล์ที่ควรได้

```
app/
├── Events/
│   ├── MessageSent.php
│   ├── MessageDeleted.php
│   ├── UserTyping.php
│   └── RoomCreated.php
├── Http/
│   ├── Controllers/Api/
│   │   ├── RoomController.php
│   │   ├── MessageController.php
│   │   ├── TypingController.php
│   │   ├── ReadReceiptController.php
│   │   └── RoomMemberController.php
│   ├── Requests/
│   │   ├── StoreRoomRequest.php
│   │   └── StoreMessageRequest.php
│   └── Resources/
│       ├── RoomResource.php
│       ├── MessageResource.php
│       └── UserResource.php
├── Models/
│   ├── Room.php
│   ├── Message.php
│   └── (User.php — แก้ไข)
└── Policies/
    ├── RoomPolicy.php
    └── MessagePolicy.php

database/
├── migrations/
│   ├── xxxx_create_rooms_table.php
│   ├── xxxx_create_room_user_table.php
│   └── xxxx_create_messages_table.php
├── factories/
│   ├── RoomFactory.php
│   └── MessageFactory.php
└── seeders/
    └── ChatSeeder.php

resources/js/
├── bootstrap.js          (Echo config)
└── components/
    ├── ChatRoom.vue       (หรือ .js สำหรับ Alpine)
    └── MessageBubble.vue

routes/
├── api.php
└── channels.php

tests/
└── Feature/
    ├── RoomTest.php
    ├── MessageTest.php
    └── BroadcastTest.php

config/
├── broadcasting.php
└── reverb.php (auto-generated)
```

---

## 🚀 EXECUTION ORDER — ลำดับการรัน Prompts

```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5 → Phase 6 → Phase 7 → Phase 8
  ↓           ↓          ↓          ↓          ↓          ↓          ↓         ↓
DB/Model   Events    Auth/Policy  Controller  Frontend   Config    Tests    Review
```

**หมายเหตุ:** รัน `php artisan test` หลังจบแต่ละ Phase เพื่อให้แน่ใจว่าไม่มี regression

---

## ⚠️ CONSTRAINTS — ข้อจำกัดที่ Agent ต้องปฏิบัติตาม

1. **ห้ามแก้ไขไฟล์** ที่อยู่นอก scope โดยไม่แจ้งก่อน
2. **ต้อง commit** ทุก Phase ด้วย conventional commit message
3. **ห้ามใช้ Pusher Cloud** — ใช้ Reverb เท่านั้น
4. **ต้องผ่าน** `php artisan test` ก่อนไปขั้นถัดไป
5. **ห้าม hardcode** credentials ลงในโค้ด — ใช้ env() เสมอ
6. **ต้องใช้** Form Request สำหรับทุก validation
7. **ต้องใช้** API Resource สำหรับทุก JSON response
8. **ทุก route** ต้องอยู่ใน middleware auth:sanctum

---

*Generated for Laravel Reverb Real-time Chat System*
*Stack: Laravel 11+ | Reverb | Laravel Echo | Pusher JS | Redis*
