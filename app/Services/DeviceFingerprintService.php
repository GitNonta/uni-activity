<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DeviceFingerprintService
 * สร้างและตรวจสอบ fingerprint ของอุปกรณ์ผู้ใช้
 * ป้องกัน multi-account บนเครื่องเดียวกัน
 */
class DeviceFingerprintService
{
    /**
     * สร้าง fingerprint จาก User-Agent + IP
     * ใช้ SHA-256 เพื่อให้ได้ string ขนาด 64 ตัวอักษร
     */
    public function generate(Request $request): string
    {
        $components = implode('|', [
            $request->ip(),
            $request->userAgent() ?? 'unknown',
        ]);

        return hash('sha256', $components);
    }

    /**
     * ตรวจสอบว่า device fingerprint นี้มีการ login หลาย student account หรือไม่
     * คืนค่า array ของ user_ids ที่ login จาก fingerprint เดียวกันใน X นาทีที่ผ่านมา
     * หรือ null ถ้าไม่พบปัญหา
     *
     * @return array{user_ids: int[], fingerprint: string}|null
     */
    public function detectMultiAccount(Request $request, int $currentUserId, int $windowMinutes = 60): ?array
    {
        $fingerprint = $this->generate($request);
        $cacheKey    = "device_login:{$fingerprint}";

        /** @var array<int> $recentLogins */
        $recentLogins = Cache::get($cacheKey, []);

        // เพิ่ม userId ปัจจุบันเข้า list (ถ้ายังไม่มี)
        if (!in_array($currentUserId, $recentLogins, true)) {
            $recentLogins[] = $currentUserId;
            Cache::put($cacheKey, $recentLogins, now()->addMinutes($windowMinutes));
        }

        // ถ้ามีมากกว่า 1 user → multi-account
        if (count($recentLogins) > 1) {
            return [
                'user_ids'    => $recentLogins,
                'fingerprint' => $fingerprint,
            ];
        }

        return null;
    }

    /**
     * ตรวจสอบว่า fingerprint นี้เคย check-in กิจกรรมเดียวกันในนาม user อื่นหรือไม่
     * ใช้ตรวจการเช็คอินแทนกัน
     *
     * @return array<int>|null รายการ user_ids ที่เช็คอินจาก fingerprint เดียวกัน
     */
    public function detectSuspiciousCheckin(string $fingerprint, int $activityId, int $currentUserId, int $windowMinutes = 10): ?array
    {
        // ค้นหา attendance ที่ fingerprint เดียวกัน กิจกรรมเดียวกัน แต่ user ต่างกัน
        $otherUsers = DB::table('attendances')
            ->where('activity_id', $activityId)
            ->where('device_fingerprint', $fingerprint)
            ->where('user_id', '!=', $currentUserId)
            ->where('checked_in_at', '>=', now()->subMinutes($windowMinutes))
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return count($otherUsers) > 0 ? $otherUsers : null;
    }
}
