<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event ส่งสัญญาณเมื่อมีผู้ใช้เปิดอ่านข้อความในห้องแชท (Real-time Read Receipt 1ms)
 */
class MessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public int $readerId,
        public ?string $readAt = null,
        public ?int $studentId = null
    ) {
        $this->readAt = $this->readAt ?? now()->toISOString();
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.room.' . $this->roomId),
        ];

        if ($this->studentId) {
            $channels[] = new PrivateChannel('chat.student.' . $this->studentId);
        }

        $channels[] = new PrivateChannel('admin.inbox');

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessagesRead';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'room_id'     => $this->roomId,
            'reader_id'   => $this->readerId,
            'read_at'     => $this->readAt,
            'read_status' => 'เพิ่งอ่าน',
        ];
    }
}
