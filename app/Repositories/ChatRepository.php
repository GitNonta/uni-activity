<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatRepository
{
    /**
     * Create a new room between users.
     */
    public function createRoom(array $userIds, string $type = 'direct', ?string $name = null, ?int $jobId = null): Room
    {
        return DB::transaction(function () use ($userIds, $type, $name, $jobId): Room {
            $room = Room::create([
                'name'       => $name,
                'type'       => $type,
                'job_id'     => $jobId,
                'created_by' => auth()->id() ?? $userIds[0],
            ]);

            $room->users()->attach($userIds);

            return $room;
        });
    }

    /**
     * Send a message in a room.
     */
    public function sendMessage(Room $room, User $user, string $body, string $type = 'text', array $attachments = []): Message
    {
        return DB::transaction(function () use ($room, $user, $body, $type, $attachments): Message {
            $message = $room->messages()->create([
                'user_id'     => $user->id,
                'body'        => $body,
                'type'        => $type,
                'attachments' => $attachments,
            ]);

            // Broadcast ไปยังคนอื่นในห้อง
            broadcast(new MessageSent($message->load('user:id,full_name,profile_photo')))->toOthers();

            return $message;
        });
    }

    /**
     * Get recent messages for a room.
     */
    public function getRecentMessages(Room $room, int $limit = 50): Collection
    {
        return $room->messages()
            ->with('user:id,full_name,profile_photo')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Get historical messages from PostgreSQL.
     */
    public function getHistoricalMessages(Room $room, ?string $beforeTimestamp = null, int $limit = 50): Collection
    {
        return $room->messages()
            ->with('user:id,full_name,profile_photo')
            ->when($beforeTimestamp, fn($q) => $q->where('created_at', '<', $beforeTimestamp))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }
}
