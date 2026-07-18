<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Message $message) {
        $this->message->loadMissing(['room', 'user']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.room.' . $this->message->room_id),
        ];

        $room = $this->message->room;
        $sender = $this->message->user;

        if ($room && $sender) {
            if ($sender->isAdmin() || $sender->isStaff()) {
                // Admin/Staff ส่ง → แจ้งเตือนนักศึกษาผ่าน personal channel
                $student = $room->users()->where('users.role', 'student')->first();
                if ($student) {
                    $channels[] = new PrivateChannel('chat.student.' . $student->id);
                }
            }
            // ทุก message ใน direct room → แจ้งเตือน admin inbox list ด้วย
            // เพื่อให้หน้า inbox index และ sidebar badge อัพเดต real-time
            $channels[] = new PrivateChannel('admin.inbox');
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
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
            'id'      => $this->message->id,
            'room_id' => $this->message->room_id,
            'message' => $this->message->body,
            'room'    => [
                'id'     => $this->message->room_id,
                'job_id' => $this->message->room->job_id ?? null,
            ],
            'user'    => [
                'id'    => $this->message->user_id,
                'name'  => $user?->full_name ?? 'ผู้ใช้',
                'role'  => $user?->role ?? 'system',
                'photo' => $user?->profile_photo ? asset('storage/' . $user->profile_photo) : null,
            ],
            'attachments' => $this->message->attachments ?? [],
            'created_at'  => $this->message->created_at?->toISOString(),
        ];
    }
}
