<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Events\MessageSent;
use App\Models\JobListing;
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
                // Snapshot of the job creator so the thread stays visible to
                // staff even after the job/announcement is deleted.
                'creator_id' => $jobId !== null ? JobListing::find($jobId)?->created_by : null,
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
        // Only save to DB inside transaction — broadcasting must be outside
        // so a Reverb failure never rolls back the message.
        $message = DB::transaction(function () use ($room, $user, $body, $type, $attachments): Message {
            return $room->messages()->create([
                'user_id'     => $user->id,
                'body'        => $body,
                'type'        => $type,
                'attachments' => $attachments,
            ]);
        });

        // Broadcast ไปยังคนอื่นในห้องผ่าน Reverb WebSocket ทันที
        // Wrapped in try-catch so broadcasting failure never kills the request
        try {
            broadcast(new MessageSent($message->load('user:id,full_name,profile_photo')))->toOthers();
        } catch (\Throwable $e) {}

        // Publish เข้าสู่ Dragonfly PubSub Backbone สำหรับ Microservices / Event Stream
        try {
            app(\App\Services\DragonflyPubSubService::class)->publishChatEvent(
                $room->id,
                'MessageSent',
                [
                    'id'          => $message->id,
                    'room_id'     => $room->id,
                    'user_id'     => $user->id,
                    'user_name'   => $user->full_name,
                    'message'     => $body,
                    'attachments' => $attachments,
                    'created_at'  => $message->created_at?->toISOString(),
                ]
            );
        } catch (\Throwable $e) {}

        return $message;
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
