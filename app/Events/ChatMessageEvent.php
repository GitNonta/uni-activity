<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $room,
        public string $event,
        public array $data
    ) {}

    public function broadcastQueue(): string
    {
        return 'high';
    }

    public function broadcastOn(): array
    {
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
