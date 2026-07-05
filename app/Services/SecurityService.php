<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SecurityService
 * บันทึกเหตุการณ์ความปลอดภัยและตรวจสอบ suspicious activity
 */
class SecurityService
{
    public function __construct(
        private readonly DeviceFingerprintService $fingerprint
    ) {}

    /**
     * บันทึก security event ลง security_logs table
     *
     * @param  string      $eventType    ประเภทเหตุการณ์
     * @param  int|null    $userId       user หลักที่เกี่ยวข้อง
     * @param  Request     $request      HTTP request object
     * @param  array       $details      ข้อมูลเพิ่มเติม
     * @param  array<int>  $relatedIds   user_ids อื่นที่เกี่ยวข้อง
     */
    public function logEvent(
        string $eventType,
        ?int $userId,
        Request $request,
        array $details = [],
        array $relatedIds = [],
    ): void {
        try {
            $fp = $this->fingerprint->generate($request);

            SecurityLog::create([
                'user_id'           => $userId,
                'event_type'        => $eventType,
                'ip_address'        => $request->ip(),
                'device_fingerprint'=> $fp,
                'related_user_ids'  => $relatedIds,
                'details'           => array_merge($details, [
                    'user_agent' => $request->userAgent(),
                    'url'        => $request->fullUrl(),
                    'method'     => $request->method(),
                    'timestamp'  => now()->toISOString(),
                ]),
            ]);
        } catch (\Throwable $e) {
            // ไม่ให้ security logging ทำให้ระบบหลักล้มเหลว
            Log::error('SecurityService::logEvent failed', [
                'error'      => $e->getMessage(),
                'event_type' => $eventType,
                'user_id'    => $userId,
            ]);
        }
    }

    /**
     * ตรวจสอบและ log การ login multi-account
     * คืน true ถ้าตรวจพบพฤติกรรมน่าสงสัย
     */
    public function checkAndLogMultiAccountLogin(Request $request, int $userId): bool
    {
        $result = $this->fingerprint->detectMultiAccount($request, $userId);

        if ($result !== null) {
            $otherIds = array_filter($result['user_ids'], fn ($id) => $id !== $userId);

            $this->logEvent(
                eventType:  'multi_account_login',
                userId:     $userId,
                request:    $request,
                details:    [
                    'message'        => 'พบการ login หลาย student account จาก device/IP เดียวกัน',
                    'total_accounts' => count($result['user_ids']),
                ],
                relatedIds: array_values($otherIds),
            );

            return true;
        }

        return false;
    }

    /**
     * ตรวจสอบและ flag การเช็คอินน่าสงสัย
     * คืน true ถ้าตรวจพบพฤติกรรมน่าสงสัย
     */
    public function checkAndLogSuspiciousCheckIn(
        Request $request,
        int $userId,
        Activity $activity,
    ): bool {
        $fp = $this->fingerprint->generate($request);
        $otherUsers = $this->fingerprint->detectSuspiciousCheckin(
            fingerprint: $fp,
            activityId:  $activity->id,
            currentUserId: $userId,
        );

        if ($otherUsers !== null) {
            $this->logEvent(
                eventType:  'suspicious_checkin',
                userId:     $userId,
                request:    $request,
                details:    [
                    'message'     => 'เช็คอินกิจกรรมเดียวกันจาก device/IP เดียวกัน ภายใน 10 นาที',
                    'activity_id' => $activity->id,
                    'activity'    => $activity->title,
                ],
                relatedIds: $otherUsers,
            );

            return true;
        }

        return false;
    }

    /**
     * นับ security events ที่ยังไม่ได้ตรวจสอบ (สำหรับ badge ใน admin menu)
     */
    public function countUnreviewed(): int
    {
        return SecurityLog::where('is_reviewed', false)->count();
    }
}
