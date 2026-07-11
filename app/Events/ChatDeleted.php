<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $roomId, public ?int $studentId = null) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.room.' . $this->roomId),
            new PrivateChannel('admin.inbox')
        ];

        if ($this->studentId) {
            $channels[] = new PrivateChannel('chat.student.' . $this->studentId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'ChatDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->roomId,
        ];
    }
}
