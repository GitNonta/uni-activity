<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Dragonfly/Redis PubSub High-Performance Backbone Service
 * บริหารจัดการ Real-time PubSub Backbone ความเร็วสูง (Sub-millisecond latency)
 */
class DragonflyPubSubService
{
    /**
     * Publish ข้อความไปยัง Dragonfly/Redis PubSub Channel
     *
     * @param  string  $channel
     * @param  array<string, mixed>|string  $payload
     * @return int จำนวน subscriber ที่ได้รับข้อความ (หรือ 0 หากไม่มี)
     */
    public function publish(string $channel, array|string $payload): int
    {
        try {
            $message = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : (string) $payload;
            
            // ใช้ Redis connection 'default'
            return (int) Redis::publish($channel, $message);
        } catch (Throwable $e) {
            Log::warning('Dragonfly PubSub publish fallback', [
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Publish Event ข้อความแชทสดเข้าสู่ Backbone Channel
     *
     * @param  string|int  $roomId
     * @param  string  $event
     * @param  array<string, mixed>  $data
     */
    public function publishChatEvent(string|int $roomId, string $event, array $data): int
    {
        return $this->publish("chat.room.{$roomId}", [
            'event'     => $event,
            'room_id'   => (string) $roomId,
            'payload'   => $data,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Publish สถานะการออนไลน์ (Presence) ของผู้ใช้
     *
     * @param  string|int  $userId
     * @param  string  $status  'online'|'offline'|'away'
     * @param  array<string, mixed>  $metadata
     */
    public function publishPresence(string|int $userId, string $status, array $metadata = []): int
    {
        return $this->publish('presence.updates', [
            'user_id'   => (string) $userId,
            'status'    => $status,
            'metadata'  => $metadata,
            'timestamp' => microtime(true),
        ]);
    }

    /**
     * Publish ข้อมูล Telemetry เช็คอินแบบเรียลไทม์ (Live Dashboard Counter & Geo Stream)
     *
     * @param  array<string, mixed>  $telemetryData
     */
    public function publishCheckinTelemetry(array $telemetryData): int
    {
        return $this->publish('telemetry.checkin', array_merge($telemetryData, [
            'timestamp' => microtime(true),
        ]));
    }
}
