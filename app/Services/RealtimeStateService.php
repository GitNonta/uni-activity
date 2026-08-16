<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Facades\Octane;

class RealtimeStateService
{
    /**
     * บันทึกสถานะผู้ใช้ออนไลน์ (In-Memory Shared Table / Cache Fallback)
     */
    public function recordUserPresence(int|string $userId, string $name, string $role, string $channel = 'global'): void
    {
        $key = "user_{$userId}";
        $now = time();

        if ($this->hasOctaneTable('active_users')) {
            Octane::table('active_users')->set($key, [
                'user_id'   => (string) $userId,
                'name'      => mb_substr($name, 0, 95),
                'role'      => $role,
                'channel'   => $channel,
                'last_seen' => $now,
            ]);
            return;
        }

        Cache::put("presence_{$channel}_{$userId}", [
            'user_id'   => (string) $userId,
            'name'      => $name,
            'role'      => $role,
            'channel'   => $channel,
            'last_seen' => $now,
        ], now()->addMinutes(5));
    }

    /**
     * ดึงรายชื่อผู้ใช้ออนไลน์ทั้งหมดใน Channel
     */
    public function getActiveUsers(string $channel = 'global', int $ttlSeconds = 300): array
    {
        $cutoff = time() - $ttlSeconds;
        $active = [];

        if ($this->hasOctaneTable('active_users')) {
            $table = Octane::table('active_users');
            foreach ($table as $key => $row) {
                if ($row['last_seen'] >= $cutoff && ($channel === 'global' || $row['channel'] === $channel)) {
                    $active[] = $row;
                }
            }
            return $active;
        }

        return Cache::get("presence_channel_list_{$channel}", []);
    }

    /**
     * ดึงหรือเพิ่มตัวนับ Real-time แบบอะตอมมิค (เช่น จำนวนผู้ลงทะเบียนสด)
     */
    public function incrementCounter(string $counterKey, int $amount = 1): int
    {
        if ($this->hasOctaneTable('realtime_counters')) {
            $table = Octane::table('realtime_counters');
            $row   = $table->get($counterKey) ?: ['count' => 0];
            $new   = $row['count'] + $amount;
            $table->set($counterKey, [
                'key'        => $counterKey,
                'count'      => $new,
                'updated_at' => time(),
            ]);
            return $new;
        }

        return (int) Cache::increment("rt_counter_{$counterKey}", $amount);
    }

    /**
     * ดึงค่าตัวนับสด
     */
    public function getCounter(string $counterKey, int $default = 0): int
    {
        if ($this->hasOctaneTable('realtime_counters')) {
            $row = Octane::table('realtime_counters')->get($counterKey);
            return $row ? (int) $row['count'] : $default;
        }

        return (int) Cache::get("rt_counter_{$counterKey}", $default);
    }

    /**
     * ตรวจสอบว่า Octane Table พร้อมใช้งานหรือไม่
     */
    private function hasOctaneTable(string $tableName): bool
    {
        try {
            return class_exists(Octane::class) && Octane::table($tableName) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
