<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $messageId, public string $roomId, public ?int $studentId = null) {}

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
        return 'MessageDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'room_id' => $this->roomId,
        ];
    }
}
