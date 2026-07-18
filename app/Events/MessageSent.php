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

        // ถ้าผู้ส่งเป็น Admin/Staff ให้ส่งไปที่ Channel ของนักศึกษาเจ้าของห้องด้วย
        // เพื่อให้ตัว Floating Widget ที่หน้าอื่นๆ ได้รับการแจ้งเตือน
        $room = $this->message->room;
        if ($room && $this->message->user) {
            if ($this->message->user->isAdmin() || $this->message->user->isStaff()) {
                // หา ID นักศึกษาในห้องนี้ (โดยปกติห้องแชทงานจะมีนักศึกษา 1 คน)
                $student = $room->users()->where('users.role', 'student')->first();
                if ($student) {
                    $channels[] = new PrivateChannel('chat.student.' . $student->id);
                }
            } else if ($this->message->user->role === 'student') {
                // แจ้งเตือนแอดมิน เพื่ออัปเดตหน้า Inbox List แบบเรียวไทม์
                $channels[] = new PrivateChannel('admin.inbox');
            }
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
