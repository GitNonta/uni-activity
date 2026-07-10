<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message \) {}

    public function broadcastOn(): array
    {
        \ = [
            new PrivateChannel('chat.room.' . \->message->room_id),
        ];

        \ = \->message->room;
        if (\ && \->message->user) {
            if (\->message->user->isAdmin() || \->message->user->isStaff()) {
                \ = \->users()->where('users.role', 'student')->first();
                if (\) {
                    \[] = new PrivateChannel('chat.student.' . \->id);
                }
            } else if (\->message->user->role === 'student') {
                \[] = new PrivateChannel('admin.inbox');
            }
        }

        return \;
    }

    public function broadcastAs(): string
    {
        return 'MessageEdited';
    }

    public function broadcastWith(): array
    {
        return [
            'id'      => \->message->id,
            'room_id' => \->message->room_id,
            'message' => \->message->body,
            'is_edited' => true,
        ];
    }
}
