<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageEdited implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {
        $this->message->loadMissing(['room', 'user']);
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.room.' . $this->message->room_id),
        ];

        $room = $this->message->room;
        if ($room && $this->message->user) {
            if ($this->message->user->isAdmin() || $this->message->user->isStaff()) {
                $student = $room->users()->where('users.role', 'student')->first();
                if ($student) {
                    $channels[] = new PrivateChannel('chat.student.' . $student->id);
                }
            } else if ($this->message->user->role === 'student') {
                $channels[] = new PrivateChannel('admin.inbox');
            }
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageEdited';
    }

    public function broadcastWith(): array
    {
        return [
            'id'      => $this->message->id,
            'room_id' => $this->message->room_id,
            'message' => $this->message->body,
            'is_edited' => true,
        ];
    }
}
