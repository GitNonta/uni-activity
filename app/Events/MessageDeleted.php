<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string \, public string \, public ?int \ = null) {}

    public function broadcastOn(): array
    {
        \ = [
            new PrivateChannel('chat.room.' . \->roomId),
        ];

        if (\->studentId) {
            \[] = new PrivateChannel('chat.student.' . \->studentId);
        }
        
        \[] = new PrivateChannel('admin.inbox');

        return \;
    }

    public function broadcastAs(): string
    {
        return 'MessageDeleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => \->messageId,
            'room_id' => \->roomId,
        ];
    }
}
