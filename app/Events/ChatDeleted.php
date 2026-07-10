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

    public function __construct(public string \, public ?int \ = null) {}

    public function broadcastOn(): array
    {
        \ = [
            new PrivateChannel('chat.room.' . \->roomId),
            new PrivateChannel('admin.inbox')
        ];

        if (\->studentId) {
            \[] = new PrivateChannel('chat.student.' . \->studentId);
        }

        return \;
    }

    public function broadcastAs(): string
    {
        return 'ChatDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'room_id' => \->roomId,
        ];
    }
}
