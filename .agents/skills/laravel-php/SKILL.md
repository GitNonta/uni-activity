---
name: laravel-php
description: >
  ใช้ skill นี้ทุกครั้งที่งานเกี่ยวข้องกับ Laravel หรือ PHP — ไม่ว่าจะเป็น
  การสร้างหรือแก้ไข Controller, Model, Migration, Event, Job, Middleware,
  Policy, Route, Blade template, API Resource, Repository pattern, Service class,
  Broadcasting/Reverb, Queue, หรือโครงสร้างโปรเจกต์ Laravel ใดก็ตาม
  รวมถึงการ debug, refactor, เพิ่มฟีเจอร์ใหม่, ออกแบบ architecture,
  หรือตรวจสอบโค้ดที่มีอยู่ให้ถูกต้องตาม Laravel best practices (Laravel 10/11).
  Trigger นี้เมื่อเห็น: artisan, eloquent, blade, middleware, broadcast,
  reverb, sanctum, livewire, inertia, queue, job, event, listener, policy,
  repository, service provider, facade, หรือโค้ด PHP ที่ต้องการบริบท Laravel.
---

# Laravel PHP Skill

คู่มือนี้กำหนดมาตรฐานและขั้นตอนที่ต้องปฏิบัติตามเมื่อทำงานกับโปรเจกต์ Laravel
อ่านทั้งหมดก่อนเขียนโค้ดทุกครั้ง

---

## 1. หลักการพื้นฐาน (อ่านทุกครั้ง)

### สิ่งที่ต้องทำเสมอ
- ใช้ **PHP 8.2+** syntax (readonly, enums, fibers, named args, match expression)
- ใช้ **strict typing** — เพิ่ม `declare(strict_types=1);` ทุกไฟล์
- ทุก method มี **type hints** ทั้ง parameter และ return type
- ตรวจสอบว่า import `use` statement ครบทุกตัวที่ใช้
- ใช้ **Form Request** สำหรับ validation ทุกครั้ง — ห้าม validate ใน Controller โดยตรง
- ใช้ **API Resource** (`JsonResource`) สำหรับทุก JSON response
- ใช้ **Repository Pattern** แยก database logic ออกจาก Controller
- ใช้ `DB::transaction()` ทุกครั้งที่มีการเขียนข้อมูลหลายขั้นตอน
- ใช้ **env()** เสมอ — ห้าม hardcode credentials, keys, หรือ URLs
- Eager load relationships ด้วย `with()` เพื่อป้องกัน N+1 query

### สิ่งที่ห้ามทำ
- ห้าม validate ใน Controller โดยตรง (`$request->validate(...)` ใน method)
- ห้าม return raw array จาก API — ใช้ Resource เสมอ
- ห้ามใช้ `->get()` โดยไม่มี limit บน collection ขนาดใหญ่
- ห้ามใช้ `Auth::user()` แล้วไม่เช็ค null
- ห้าม `@php` ใน Blade ถ้าสามารถทำใน Controller/ViewModel ได้

---

## 2. โครงสร้างไฟล์มาตรฐาน

```
app/
├── Events/              # Broadcasting events (ShouldBroadcast)
├── Http/
│   ├── Controllers/     # บาง ไม่มี business logic
│   │   └── Api/         # API controllers แยกต่างหาก
│   ├── Middleware/
│   ├── Requests/        # Form Request validation
│   └── Resources/       # API Resources (JsonResource)
├── Jobs/                # Queue jobs
├── Listeners/           # Event listeners
├── Models/              # Eloquent models
├── Policies/            # Authorization policies
├── Repositories/        # Database abstraction layer
│   └── Contracts/       # Repository interfaces
└── Services/            # Business logic services
```

---

## 3. Conventions ตามประเภทไฟล์

### Controller
```php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Repositories\ChatRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MessageController extends Controller
{
    public function __construct(
        private readonly ChatRepository $chat
    ) {}

    public function index(Room $room): AnonymousResourceCollection
    {
        $this->authorize('view', $room);
        $messages = $this->chat->getRecentMessages($room);
        return MessageResource::collection($messages);
    }

    public function store(StoreMessageRequest $request, Room $room): MessageResource
    {
        $this->authorize('create', $room);
        $message = $this->chat->sendMessage($room, $request->user(), $request->validated('body'));
        return new MessageResource($message);
    }
}
```

### Model
```php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = ['name', 'type', 'job_id', 'created_by'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships ก่อนเสมอ จากนั้น scopes แล้ว methods
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope ตั้งชื่อด้วย camelCase, prefix "scope"
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('users', fn($q) => $q->where('users.id', $userId));
    }
}
```

### Broadcasting Event
```php
declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Message $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.room.' . $this->message->room_id),
        ];
    }

    // ถ้าต้องการชื่อ event custom ให้ override broadcastAs()
    public function broadcastAs(): string
    {
        return 'message.sent'; // frontend ใช้ .listen('.message.sent', ...)
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->message->id,
            'body'       => $this->message->body,
            'type'       => $this->message->type,
            'room_id'    => $this->message->room_id,
            'created_at' => $this->message->created_at->toISOString(),
            'user' => [
                'id'    => $this->message->user->id,
                'name'  => $this->message->user->name,
            ],
        ];
    }
}
```

### Repository
```php
declare(strict_types=1);

namespace App\Repositories;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatRepository
{
    public function sendMessage(
        Room $room,
        User $user,
        string $body,
        string $type = 'text'
    ): Message {
        return DB::transaction(function () use ($room, $user, $body, $type): Message {
            $message = $room->messages()->create([
                'user_id' => $user->id,
                'body'    => $body,
                'type'    => $type,
            ]);

            // Broadcast — toOthers() เพื่อไม่ส่งกลับหา sender
            broadcast(new MessageSent($message->load('user')))->toOthers();

            return $message;
        });
    }

    public function getRecentMessages(Room $room, int $limit = 50): Collection
    {
        return $room->messages()
            ->with('user:id,name')          // eager load เฉพาะ field ที่ต้องการ
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
```

---

## 4. Real-time Broadcasting (Reverb)

รายละเอียดเพิ่มเติมอยู่ใน `references/reverb.md`

### Quick checklist
- [ ] `BROADCAST_CONNECTION=reverb` ใน `.env`
- [ ] `VITE_REVERB_*` variables ครบ (KEY, HOST, PORT, SCHEME)
- [ ] Event implement `ShouldBroadcast`
- [ ] Channel authorization กำหนดใน `routes/channels.php`
- [ ] Frontend ใช้ `import.meta.env.VITE_REVERB_*` ไม่ใช่ hardcode
- [ ] `.listen('.EventClassName', ...)` หรือ `.listen('.broadcast.as.name', ...)` ถูกต้อง
- [ ] `laravel-echo` และ `pusher-js` อยู่ใน `dependencies` (ไม่ใช่ devDependencies)
- [ ] `broadcasting/auth` route ถูก register (มี `Broadcast::routes()`)

---

## 5. Authorization

```php
// routes/channels.php
Broadcast::channel('chat.room.{roomId}', function (User $user, string $roomId): bool|array {
    $room = Room::find($roomId);
    if (!$room) return false;

    $isMember = $room->users()->where('users.id', $user->id)->exists();
    $isAdmin  = $user->hasRole('admin');

    return $isMember || $isAdmin;
});
```

```php
// Policy — register ใน AuthServiceProvider
class RoomPolicy
{
    public function view(User $user, Room $room): bool
    {
        return $room->users()->where('users.id', $user->id)->exists()
            || $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return true; // authenticated user สร้างได้
    }
}
```

---

## 6. Database & Migration

```php
// Migration มาตรฐาน
public function up(): void
{
    Schema::create('messages', function (Blueprint $table): void {
        $table->ulid('id')->primary();
        $table->foreignUlid('room_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        $table->text('body');
        $table->enum('type', ['text', 'system'])->default('text');
        $table->json('read_by')->default('[]');
        $table->timestamps();
        $table->softDeletes();

        // Index สำคัญสำหรับ chat
        $table->index(['room_id', 'created_at']);
        $table->index('user_id');
    });
}
```

---

## 7. Queue & Jobs

```php
// Job สำหรับ async tasks
class ArchiveOldMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds

    public function handle(CassandraService $cassandra): void
    {
        // idempotent — ทำซ้ำได้โดยไม่เกิด duplicate
        Message::where('created_at', '<', now()->subDays(config('chat.archive_after_days', 30)))
            ->where('is_archived', false)
            ->chunkById(100, function (Collection $messages) use ($cassandra): void {
                foreach ($messages as $message) {
                    $cassandra->logMessage(...);
                    $message->update(['is_archived' => true]);
                }
            });
    }
}
```

---

## 8. Testing

```php
// Feature test มาตรฐาน
class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_send_message(): void
    {
        Event::fake([MessageSent::class]);

        $room = Room::factory()->create();
        $user = User::factory()->create();
        $room->users()->attach($user->id);

        $response = $this->actingAs($user)
            ->postJson(route('api.rooms.messages.store', $room), [
                'body' => 'Hello world',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Hello world');

        $this->assertDatabaseHas('messages', ['body' => 'Hello world']);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_non_member_cannot_send_message(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create(); // ไม่ได้เป็น member

        $this->actingAs($user)
            ->postJson(route('api.rooms.messages.store', $room), ['body' => 'test'])
            ->assertForbidden();
    }
}
```

---

## 9. Error Handling Pattern

```php
// ใน Service/Repository — throw exceptions ที่มีความหมาย
public function findRoom(string $roomId): Room
{
    return Room::findOrFail($roomId); // จะ throw ModelNotFoundException อัตโนมัติ
}

// ใน Handler (app/Exceptions/Handler.php) — จัดการ centrally
public function register(): void
{
    $this->renderable(function (ModelNotFoundException $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Resource not found'], 404);
        }
    });
}
```

---

## 10. Reference Files

อ่านเพิ่มเติมเมื่อต้องการรายละเอียดเฉพาะส่วน:

| ไฟล์ | ใช้เมื่อ |
|---|---|
| `references/reverb.md` | งานเกี่ยวกับ Broadcasting, WebSocket, Echo |
| `references/patterns.md` | Repository, Service, DTO patterns |
| `references/docker.md` | Docker compose, Supervisor config |
| `references/cassandra.md` | Hot/Cold storage, Cassandra CQL |
