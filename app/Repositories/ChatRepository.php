<?php

namespace App\Repositories;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\CassandraService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatRepository
{
    public function __construct(protected CassandraService $cassandra) {}

    /**
     * Create a new room between users.
     */
    public function createRoom(array $userIds, string $type = 'direct', ?string $name = null, ?int $jobId = null): Room
    {
        return DB::transaction(function () use ($userIds, $type, $name, $jobId) {
            $room = Room::create([
                'name' => $name,
                'type' => $type,
                'job_id' => $jobId,
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
        return DB::transaction(function () use ($room, $user, $body, $type, $attachments) {
            $message = $room->messages()->create([
                'user_id' => $user->id,
                'body' => $body,
                'type' => $type,
                'attachments' => $attachments,
            ]);

            // Also write to Cassandra for long-term history
            $this->syncToCassandra($message);

            // Broadcast ไปยังคนอื่นในห้อง
            broadcast(new MessageSent($message->load('user:id,full_name,profile_photo')))->toOthers();

            return $message;
        });
    }

    /**
     * Sync a message to Cassandra.
     */
    public function syncToCassandra(Message $message): void
    {
        // Dispatch to background queue to prevent blocking the HTTP response
        // especially when Cassandra is unreachable or slow
        \App\Jobs\SyncToCassandra::dispatch($message);
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
     * Get historical messages from Cassandra.
     */
    public function getHistoricalMessages(Room $room, $beforeTimestamp = null, int $limit = 50)
    {
        $before = $beforeTimestamp ? new \DateTime($beforeTimestamp) : null;
        return collect($this->cassandra->getHistory($room->id, $limit, $before));
    }
}
