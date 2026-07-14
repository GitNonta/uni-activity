<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;
    public $event;
    public $data;

    public function __construct(string $room, string $event, array $data)
    {
        $this->room = $room;
        $this->event = $event;
        $this->data = $data;
    }

    public function broadcastOn(): array
    {
        // Convert socket.io room format to Laravel channel format
        // socket: chat:student:1 -> private-chat.student.1
        $channel = str_replace(':', '.', $this->room);
        return [
            new PrivateChannel($channel),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageEvent';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
