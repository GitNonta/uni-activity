# Laravel Patterns Reference

## Repository Pattern

### Interface
```php
// app/Repositories/Contracts/ChatRepositoryInterface.php
namespace App\Repositories\Contracts;

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;

interface ChatRepositoryInterface
{
    public function createRoom(array $userIds, string $type, ?string $name, ?int $jobId): Room;
    public function sendMessage(Room $room, User $user, string $body, string $type): Message;
    public function getRecentMessages(Room $room, int $limit): Collection;
}
```

### Binding ใน ServiceProvider
```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        \App\Repositories\Contracts\ChatRepositoryInterface::class,
        \App\Repositories\ChatRepository::class,
    );
}
```

---

## Service Pattern

ใช้เมื่อ logic ซับซ้อนเกินกว่าจะอยู่ใน Repository หรือต้องใช้หลาย Repository

```php
// app/Services/ChatService.php
namespace App\Services;

class ChatService
{
    public function __construct(
        private readonly ChatRepository $chat,
        private readonly NotificationService $notification,
    ) {}

    public function processMessage(Room $room, User $sender, string $body): Message
    {
        $message = $this->chat->sendMessage($room, $sender, $body);

        // cross-cutting concerns อยู่ที่นี่
        $this->notification->notifyRoomMembers($room, $message, $sender);

        return $message;
    }
}
```

---

## DTO (Data Transfer Object)

ใช้แทน array เมื่อต้องการ type safety

```php
// app/DTOs/MessageData.php
readonly class MessageData
{
    public function __construct(
        public string $body,
        public string $type = 'text',
    ) {}

    public static function fromRequest(StoreMessageRequest $request): self
    {
        return new self(
            body: $request->validated('body'),
            type: $request->validated('type', 'text'),
        );
    }
}
```

---

## API Resource

```php
// app/Http/Resources/MessageResource.php
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'body'       => $this->body,
            'type'       => $this->type,
            'created_at' => $this->created_at->toISOString(),
            'user'       => new UserResource($this->whenLoaded('user')),
            'room_id'    => $this->room_id,
            // ฟิลด์ที่ต้องการเงื่อนไข
            'is_mine'    => $this->when(
                $request->user()?->id === $this->user_id,
                true,
                false
            ),
        ];
    }
}
```

---

## Form Request

```php
// app/Http/Requests/StoreMessageRequest.php
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ใช้ Policy
        return $this->user()->can('create', $this->route('room'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'type' => ['sometimes', 'in:text,system'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'กรุณาพิมพ์ข้อความ',
            'body.max'      => 'ข้อความยาวเกิน 2,000 ตัวอักษร',
        ];
    }
}
```
