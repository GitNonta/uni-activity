<?php

declare(strict_types=1);

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
    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['room', 'user']);
    }

    /**
     * The name of the queue on which to place the broadcasting job.
     */
    public function broadcastQueue(): string
    {
        return 'high';
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
            // แจ้งเตือนนักศึกษาทุกคนในห้องผ่าน personal channel
            $students = $room->users()->where('users.role', 'student')->get();
            foreach ($students as $student) {
                $channels[] = new PrivateChannel('chat.student.' . $student->id);
            }
            // ทุก message ใน direct room → แจ้งเตือน admin inbox list ด้วย
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
                'photo' => $user?->profile_photo ? '/storage/' . $user->profile_photo : null,
            ],
            'attachments' => $this->message->attachments ?? [],
            'created_at'  => $this->message->created_at?->toISOString(),
        ];
    }
}
