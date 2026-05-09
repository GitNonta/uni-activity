<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Message $message) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.room.' . $this->message->room_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $user = $this->message->user;
        
        return [
            'id'           => $this->message->id,
            'room_id'      => $this->message->room_id,
            'sender_id'    => $this->message->user_id,
            'sender_role'  => $user?->role ?? 'system',
            'sender_name'  => $user?->full_name ?? 'ผู้ใช้',
            'sender_photo' => $user?->profile_photo ? asset('storage/' . $user->profile_photo) : null,
            'message'      => $this->message->body,
            'attachments'  => $this->message->attachments ?? [],
            'created_at'   => $this->message->created_at?->toISOString(),
        ];
    }
}
