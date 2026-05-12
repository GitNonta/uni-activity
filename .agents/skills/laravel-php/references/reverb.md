# Reverb & Broadcasting Reference

## การติดตั้ง

```bash
composer require laravel/reverb
php artisan reverb:install
npm install laravel-echo pusher-js
```

---

## .env ที่ต้องมี

```env
# Backend
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend (Vite)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="127.0.0.1"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## echo.js (ต้องใช้ import.meta.env เสมอ — ห้าม hardcode)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: parseInt(import.meta.env.VITE_REVERB_PORT),
    wssPort: parseInt(import.meta.env.VITE_REVERB_PORT),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

---

## การตั้งชื่อ Event และ .listen()

| มี broadcastAs()? | ชื่อที่ใช้ใน .listen() |
|---|---|
| ไม่มี | `.listen('.MessageSent', ...)` (ชื่อ class มี dot นำหน้า) |
| มี → คืน `'message.sent'` | `.listen('.message.sent', ...)` |

---

## Channel Types

```php
// Public — ไม่ต้อง auth
new Channel('news');

// Private — ต้อง auth, user เท่านั้น
new PrivateChannel('chat.room.' . $roomId);

// Presence — auth + ส่ง user info, ใช้สำหรับ "online users"
new PresenceChannel('chat.room.' . $roomId);
```

---

## Frontend Listeners

```javascript
// Private channel
Echo.private(`chat.room.${roomId}`)
    .listen('.message.sent', (e) => {
        console.log(e); // payload จาก broadcastWith()
    })
    .listen('.message.deleted', (e) => { ... });

// Presence channel — รู้ว่าใครออนไลน์
Echo.join(`chat.room.${roomId}`)
    .here((users) => setOnlineUsers(users))
    .joining((user) => addOnlineUser(user))
    .leaving((user) => removeOnlineUser(user))
    .listenForWhisper('typing', (e) => showTyping(e.name));

// Whisper — ส่งจาก frontend โดยไม่ผ่าน server (ใช้สำหรับ typing indicator)
Echo.private(`chat.room.${roomId}`)
    .whisper('typing', { name: userName });

// หยุดฟัง (สำคัญ — ต้องทำเมื่อออกจากหน้า)
Echo.leave(`chat.room.${roomId}`);
```

---

## routes/channels.php มาตรฐาน

```php
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.room.{roomId}', function (User $user, string $roomId): bool {
    $room = Room::find($roomId);
    if (!$room) return false;

    return $room->users()->where('users.id', $user->id)->exists()
        || $user->hasRole('admin');
});

Broadcast::channel('user.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
```

---

## ต้องมี Broadcast::routes() ใน RouteServiceProvider หรือ bootstrap/app.php

```php
// bootstrap/app.php (Laravel 11)
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    channels: __DIR__.'/../routes/channels.php', // ← ต้องมีบรรทัดนี้
)
```

---

## Supervisord config สำหรับ Production

```ini
[program:reverb]
command=php /var/www/html/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/reverb.out.log
stderr_logfile=/var/log/reverb.err.log

[program:queue]
command=php /var/www/html/artisan queue:work redis --queue=broadcasting,default --sleep=3 --tries=3
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/queue.out.log
```

---

## Debug Checklist เมื่อ WebSocket ไม่เชื่อม

1. เปิด DevTools → Network → filter "WS" → ดูว่ามี connection ไหม
2. ถ้าไม่มี connection เลย:
   - `echo.js` ถูก import ใน `app.js` ไหม?
   - `app.js` ถูก include ใน blade ด้วย `@vite` ไหม?
   - รัน `npm run build` หลังแก้ไขแล้วหรือยัง?
3. ถ้า connection ขึ้นแต่ error 403:
   - `Broadcast::routes()` register แล้วหรือยัง?
   - Channel authorization ใน `channels.php` return `true` ไหม?
   - User ผ่าน `auth:sanctum` หรือ `auth` middleware แล้วหรือยัง?
4. ถ้า connect ได้แต่ไม่ได้รับข้อความ:
   - `broadcast()` ถูก call จริงไหม? ลอง `php artisan tinker` broadcast manual
   - Queue worker รันอยู่ไหม? (`php artisan queue:work`)
   - ชื่อ event ใน `.listen()` ตรงกับ `broadcastAs()` ไหม?
