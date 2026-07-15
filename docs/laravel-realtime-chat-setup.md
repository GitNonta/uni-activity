# คู่มือ Setup ระบบแชทเรียลไทม์ด้วย Laravel 12 + Reverb + Redis + PostgreSQL

> ⚠️ หมายเหตุ: Laravel 12 ต้องใช้ **PHP 8.2 หรือสูงกว่า** (แนะนำ 8.3) — PHP 5 ใช้ไม่ได้ เพราะ Laravel เลิกรองรับมาตั้งแต่เวอร์ชัน 5.x ของ Laravel เองแล้ว คู่มือนี้จะตั้งค่าด้วย PHP 8.3

---

## 0. สิ่งที่ต้องมีในเครื่อง/เซิร์ฟเวอร์

- PHP 8.3 + extensions: `pdo_pgsql`, `redis` (phpredis) หรือใช้ `predis/predis` แทนก็ได้, `mbstring`, `bcmath`
- Composer 2.x
- Node.js 20+ และ npm
- PostgreSQL 15+
- Redis 7+
- Supervisor (สำหรับรัน queue worker + reverb แบบถาวรใน production)

ตรวจสอบเวอร์ชัน:
```bash
php -v
composer -V
node -v
psql --version
redis-server --version
```

---

## 1. สร้างโปรเจกต์ Laravel 12

```bash
composer create-project laravel/laravel realtime-chat "^12.0"
cd realtime-chat
```

---

## 2. ติดตั้ง Package ที่จำเป็น

```bash
# Reverb (WebSocket server ของ Laravel เอง — เร็วและไม่ต้องพึ่ง Pusher)
php artisan install:broadcasting

# ถ้าไม่ได้ auto-install reverb ให้ทำเอง
composer require laravel/reverb

# Redis client (เลือกอย่างใดอย่างหนึ่ง)
composer require predis/predis
# หรือถ้าติดตั้ง phpredis extension ในเครื่องแล้ว ไม่ต้องลง predis ก็ได้ ให้ตั้ง REDIS_CLIENT=phpredis

# Auth API (ถ้า frontend เป็น SPA/Mobile)
composer require laravel/sanctum
```

คำสั่ง `php artisan install:broadcasting` จะ:
- ติดตั้ง Reverb ให้อัตโนมัติ
- สร้างไฟล์ `routes/channels.php`
- เพิ่ม config `config/reverb.php`
- ถามให้ยืนยันตั้งค่า `.env` ให้เลย (ตอบ yes)

---

## 3. ตั้งค่า `.env`

```env
APP_NAME="Realtime Chat"
APP_ENV=production
APP_KEY=base64:xxxxx
APP_DEBUG=false
APP_URL=https://chat.yourdomain.com

# ---------- Database (PostgreSQL) ----------
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=realtime_chat
DB_USERNAME=chat_user
DB_PASSWORD=strong_password_here

# ---------- Redis ----------
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# ---------- Cache / Session ใช้ Redis เพื่อความไว ----------
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default

# ---------- Queue ใช้ Redis (สำคัญมากสำหรับ broadcast) ----------
QUEUE_CONNECTION=redis

# ---------- Broadcasting ใช้ Reverb ----------
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=chat-app
REVERB_APP_KEY=chatkey123
REVERB_APP_SECRET=chatsecret123
REVERB_HOST="chat.yourdomain.com"
REVERB_PORT=443
REVERB_SCHEME=https

# ค่าที่ Reverb server เอง (ไม่ใช่ client) ใช้ bind
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# ---------- ตัวแปรที่ frontend (Echo) จะใช้ผ่าน Vite ----------
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

> ถ้าเทสในเครื่อง localhost: ตั้ง `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http` และ `VITE_*` ให้ตรงกัน

สร้างฐานข้อมูล PostgreSQL:
```bash
sudo -u postgres psql -c "CREATE DATABASE realtime_chat;"
sudo -u postgres psql -c "CREATE USER chat_user WITH ENCRYPTED PASSWORD 'strong_password_here';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE realtime_chat TO chat_user;"
```

---

## 4. Database Schema (Migrations)

```bash
php artisan make:model Conversation -m
php artisan make:model Message -m
php artisan make:migration create_conversation_user_table
```

**`database/migrations/xxxx_create_conversations_table.php`**
```php
public function up(): void
{
    Schema::create('conversations', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable(); // null = แชทส่วนตัว 1:1
        $table->boolean('is_group')->default(false);
        $table->timestamps();
    });
}
```

**`database/migrations/xxxx_create_conversation_user_table.php`**
```php
public function up(): void
{
    Schema::create('conversation_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->timestamp('last_read_at')->nullable();
        $table->timestamps();

        $table->unique(['conversation_id', 'user_id']);
    });
}
```

**`database/migrations/xxxx_create_messages_table.php`**
```php
public function up(): void
{
    Schema::create('messages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->text('body');
        $table->timestamps();

        // index สำคัญมากสำหรับ query แชทให้เร็วบน PostgreSQL
        $table->index(['conversation_id', 'created_at']);
    });
}
```

```bash
php artisan migrate
```

**Model ตัวอย่าง `app/Models/Message.php`**
```php
class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
```

---

## 5. Event สำหรับ Broadcast (สำคัญที่สุด)

```bash
php artisan make:event MessageSent
```

**`app/Events/MessageSent.php`**
```php
<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// ใช้ ShouldBroadcastNow เฉพาะกรณีไม่ผ่าน queue (ไม่แนะนำสำหรับโปรดักชันที่มีโหลดสูง)
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Message $message)
    {
        // eager load ป้องกัน N+1 ตอน serialize
        $this->message->load('user:id,name,avatar');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'conversation_id' => $this->message->conversation_id,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
            ],
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

> **ทำไมต้องใช้ `ShouldBroadcast` (ไม่ใช่ `ShouldBroadcastNow`)?**
> เพราะ `ShouldBroadcast` จะส่ง event เข้า **Queue** (Redis) ก่อน แล้วให้ Queue Worker ยิงออกไปที่ Reverb แยกจาก HTTP request — ทำให้ผู้ใช้ที่ส่งข้อความได้ response กลับเร็วมาก ไม่ต้องรอ broadcast เสร็จ

---

## 6. Channel Authorization

**`routes/channels.php`**
```php
use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::find($conversationId)
        ?->users()
        ->where('user_id', $user->id)
        ->exists();
});

// ตัวอย่าง presence channel สำหรับแสดงว่าใคร online / กำลังพิมพ์
Broadcast::channel('presence.conversation.{conversationId}', function ($user, $conversationId) {
    $isMember = Conversation::find($conversationId)
        ?->users()
        ->where('user_id', $user->id)
        ->exists();

    return $isMember ? ['id' => $user->id, 'name' => $user->name] : false;
});
```

ต้องมี relation `users()` ใน `Conversation` model:
```php
public function users()
{
    return $this->belongsToMany(User::class)->withPivot('last_read_at')->withTimestamps();
}
```

---

## 7. Controller ยิงข้อความ

```bash
php artisan make:controller MessageController
```

**`app/Http/Controllers/MessageController.php`**
```php
public function store(Request $request, Conversation $conversation)
{
    $request->validate(['body' => 'required|string|max:5000']);

    $message = $conversation->messages()->create([
        'user_id' => $request->user()->id,
        'body' => $request->body,
    ]);

    broadcast(new MessageSent($message))->toOthers();

    return response()->json($message->load('user:id,name'), 201);
}
```

`->toOthers()` ป้องกันไม่ให้ event ยิงกลับไปหาคนที่ส่งข้อความเอง (ฝั่ง client จะ append ข้อความของตัวเองจาก response ตรงๆ อยู่แล้ว ไวกว่ารอ websocket)

**`routes/api.php`**
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
});
```

---

## 8. Frontend — Laravel Echo + Reverb

```bash
npm install --save-dev laravel-echo pusher-js
```

**`resources/js/echo.js`**
```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth', // ต้อง auth ผ่าน Sanctum session/cookie
});
```

**ตัวอย่างการฟังข้อความใหม่ (Vanilla/Vue/React ปรับได้)**
```js
Echo.private(`conversation.${conversationId}`)
    .listen('.message.sent', (e) => {
        appendMessageToChatBox(e);
    });

// presence channel — ใคร online / กำลังพิมพ์
Echo.join(`presence.conversation.${conversationId}`)
    .here((users) => setOnlineUsers(users))
    .joining((user) => addOnlineUser(user))
    .leaving((user) => removeOnlineUser(user))
    .listenForWhisper('typing', (e) => showTypingIndicator(e));
```

**ส่งสถานะ "กำลังพิมพ์" แบบไม่ผ่าน server (whisper — เร็วมาก ไม่กิน queue)**
```js
Echo.join(`presence.conversation.${conversationId}`)
    .whisper('typing', { userId: currentUser.id });
```

อย่าลืม import ใน `resources/js/app.js`:
```js
import './echo.js';
```

---

## 9. รันตอน Dev (เทสในเครื่อง)

เปิด 3 terminal:

```bash
# Terminal 1 — Laravel app server
php artisan serve

# Terminal 2 — Reverb (WebSocket server)
php artisan reverb:start

# Terminal 3 — Queue worker (ยิง broadcast event ออกจาก Redis queue)
php artisan queue:work redis --queue=default,broadcasts --tries=3

# Terminal 4 (ถ้าใช้ Vite)
npm run dev
```

---

## 10. Production Setup — ให้ "ไว" และมั่นคง

### 10.1 ใช้ Supervisor คุม Queue Worker + Reverb ให้รันตลอดเวลา

`/etc/supervisor/conf.d/chat-queue-worker.conf`
```ini
[program:chat-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/realtime-chat/artisan queue:work redis --sleep=0.1 --tries=3 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/realtime-chat/storage/logs/queue-worker.log
stopwaitsecs=3600
```

> `numprocs=4` = รัน worker พร้อมกัน 4 ตัว เพื่อประมวลผล broadcast event แบบขนาน (ปรับตามโหลด/core ของเซิร์ฟเวอร์)

`/etc/supervisor/conf.d/chat-reverb.conf`
```ini
[program:chat-reverb]
command=php /var/www/realtime-chat/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/realtime-chat/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start chat-queue-worker:*
sudo supervisorctl start chat-reverb:*
```

### 10.2 Reverb ผ่าน Nginx (SSL + WebSocket proxy)

```nginx
server {
    listen 443 ssl;
    server_name chat.yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/chat.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/chat.yourdomain.com/privkey.pem;

    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 60s;
    }

    location / {
        root /var/www/realtime-chat/public;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 10.3 Performance Checklist

| จุด | วิธีทำให้ไว |
|---|---|
| Broadcasting | ใช้ `ShouldBroadcast` (ผ่าน queue) ไม่ใช่ `ShouldBroadcastNow` |
| Queue | ใช้ Redis driver, รัน worker หลายตัวด้วย Supervisor |
| DB query | index `conversation_id + created_at`, ใช้ `select()`/eager load ป้องกัน N+1 |
| Cache/Session | ใช้ Redis (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`) ไม่ใช้ file driver |
| OPcache | เปิด OPcache ใน `php.ini`: `opcache.enable=1`, `opcache.validate_timestamps=0` (prod) |
| Config/Route cache | `php artisan config:cache && php artisan route:cache && php artisan event:cache` |
| Reverb scaling | ถ้าโหลดสูงมาก ใช้ Reverb แบบ multi-process/multi-server + Redis pub/sub เป็นตัวกลาง (`REVERB_SCALING_ENABLED=true` ใน `.env` และตั้ง `REVERB_SCALING_CHANNEL`) |
| Presence/typing | ใช้ **whisper** แทนการยิง event ผ่าน server เพื่อลดโหลด queue |
| PostgreSQL | เปิด connection pooling ด้วย PgBouncer ถ้ามี concurrent user สูง |

**ตั้งค่า Reverb scaling (หลาย instance)** ใน `.env`:
```env
REVERB_SCALING_ENABLED=true
```
Reverb จะใช้ Redis pub/sub เชื่อม instance หลายตัวให้ broadcast ถึงกันอัตโนมัติ — ใช้ Redis ตัวเดียวกับที่ตั้งไว้แล้วได้เลย

---

## 11. คำสั่งสรุปหลัง deploy ทุกครั้ง

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan queue:restart      # บอก worker เก่าให้รีสตาร์ทรับโค้ดใหม่
sudo supervisorctl restart chat-queue-worker:*
sudo supervisorctl restart chat-reverb:*
npm run build
```

---

## สรุปโฟลว์การทำงาน

```
User ส่งข้อความ (HTTP POST)
      ↓
Controller บันทึกลง PostgreSQL (เร็ว)
      ↓
broadcast(MessageSent)->toOthers()  →  เข้า Redis Queue ทันที (ไม่บล็อก response)
      ↓
Response กลับ user ผู้ส่งทันที (UI แสดงข้อความตัวเองจาก response)
      ↓
Queue Worker (Supervisor) ดึง job จาก Redis
      ↓
ยิงออกไปที่ Reverb WebSocket Server
      ↓
Client อื่นๆ ที่ subscribe channel นั้นอยู่ ได้รับข้อความแบบเรียลไทม์ผ่าน Laravel Echo
```

โครงสร้างนี้เร็วเพราะ **แยก DB write, HTTP response, และ broadcast ออกจากกันด้วย queue** — ผู้ส่งไม่ต้องรอ WebSocket ส่งเสร็จ และระบบรองรับผู้ใช้พร้อมกันจำนวนมากได้ดีเพราะ Redis + Reverb ทำงานเป็น pub/sub แยกจาก PHP-FPM process หลัก
