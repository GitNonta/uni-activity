<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * เซอร์วิสเช็คอิน / บันทึกกิจกรรม
 * จัดการกระบวนการเช็คอินและออกงานภายใต้ ACID Database Transactions เพื่อความสอดคล้องของข้อมูล 100%
 */
class CheckInService
{
    public function __construct(
        private readonly DeviceFingerprintService $fpService,
        private readonly SecurityService $secService,
    ) {}

    /**
     * ดำเนินการเช็คอิน (เข้างาน) หรือ บันทึกกิจกรรม (เลิกงาน) ภายใต้ Database Transaction
     *
     * @param  string      $token      QR token ของกิจกรรม
     * @param  User        $user       ผู้ใช้ที่เช็คอิน
     * @param  string      $method     วิธีเช็คอิน: qr_scan, self, walk_in
     * @param  float|null  $latitude   ละติจูดจากอุปกรณ์ผู้ใช้
     * @param  float|null  $longitude  ลองจิจูดจากอุปกรณ์ผู้ใช้
     * @param  array       $metaData   ข้อมูลเพิ่มเติม (เช่น selfie_photo_path, face_match_score, liveness_passed)
     * @return array{
     *     success: bool,
     *     message?: string,
     *     activity?: Activity,
     *     status?: string,
     *     distance?: float|null,
     *     selfie_required?: bool,
     *     attendance_id?: int|null
     * }
     */
    public function processCheckIn(
        string $token,
        User $user,
        string $method = 'qr_scan',
        ?float $latitude = null,
        ?float $longitude = null,
        array $metaData = []
    ): array {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();
            
        $isCheckoutToken = ($activity->qr_checkout_token === $token);
        $lockKey = "checkin_lock_{$user->id}_{$activity->id}";

        // ── 1. ป้องกัน Race Condition ด้วย Atomic Cache Lock ──
        return Cache::lock($lockKey, 10)->block(5, function () use ($activity, $isCheckoutToken, $user, $method, $latitude, $longitude, $metaData): array {
            // ── 2. ทำงานภายใต้ Database Transaction เพื่อความถูกต้องแบบ All-or-Nothing ──
            return DB::transaction(function () use ($activity, $isCheckoutToken, $user, $method, $latitude, $longitude, $metaData): array {
                $now = now();

                // ตรวจสอบการลงทะเบียน
                $registration = Registration::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->where('status', 'approved')
                    ->first();

                // หากไม่ได้ลงทะเบียนล่วงหน้า
                if (!$registration) {
                    if (!$activity->allow_walkin) {
                        return [
                            'success' => false,
                            'message' => 'คุณไม่ได้ลงทะเบียนกิจกรรมนี้ หรือยังไม่ได้รับการอนุมัติ (กิจกรรมนี้ไม่เปิดรับ Walk-in)',
                        ];
                    }
                    $method = 'walk_in';
                }

                // ค้นหารายการ Attendance เดิม พร้อม Lock Row (lockForUpdate)
                $attendance = Attendance::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->lockForUpdate()
                    ->first();

                // ── กรณีที่ 1: การเช็คอินเข้างาน (Entry) ──
                if (!$isCheckoutToken) {
                    if ($attendance) {
                        if ($attendance->checked_out_at) {
                            return ['success' => false, 'message' => 'คุณได้บันทึกจบกิจกรรมนี้ไปแล้ว'];
                        }
                        return ['success' => false, 'message' => 'คุณเช็คอินไปแล้ว กรุณาสแกน QR สำหรับออกงานเพื่อรับชั่วโมง'];
                    }

                    // ตรวจช่วงเวลาเปิดเช็คอิน
                    if (!$activity->allow_early_checkin && $now < $activity->checkin_open_at) {
                        return ['success' => false, 'message' => 'ยังไม่ถึงเวลาเช็คอินเข้างาน'];
                    }
                    if ($now > $activity->checkin_close_at) {
                        return ['success' => false, 'message' => 'หมดเวลาเช็คอินเข้างานแล้ว'];
                    }

                    // ตรวจสอบพิกัด (บังคับสำหรับ Check-in เข้างาน)
                    $distance = null;
                    if ($activity->hasGeolocation()) {
                        if ($latitude === null || $longitude === null) {
                            return ['success' => false, 'message' => 'กรุณาเปิด GPS เพื่อตรวจสอบว่าคุณอยู่ในพื้นที่กิจกรรม'];
                        }
                        $distance = $this->calculateDistance($activity->latitude, $activity->longitude, $latitude, $longitude);
                        if ($distance > $activity->checkin_radius) {
                            return [
                                'success'  => false,
                                'message'  => "คุณอยู่นอกพื้นที่กิจกรรม (ห่างประมาณ " . number_format($distance, 0) . " ม.)",
                                'distance' => $distance,
                            ];
                        }
                    }

                    // ตรวจสอบความปลอดภัยและคำนวณ Device Fingerprint
                    $fingerprint  = $this->fpService->generate(request());
                    $isSuspicious = $this->secService->checkAndLogSuspiciousCheckIn(
                        request:  request(),
                        userId:   $user->id,
                        activity: $activity,
                    );

                    try {
                        $newAttendance = Attendance::create([
                            'user_id'            => $user->id,
                            'activity_id'        => $activity->id,
                            'checked_in_at'      => $now,
                            'method'             => $method,
                            'status'             => 'pending', // เข้างานแล้วแต่ยังไม่จบกิจกรรม
                            'is_verified'        => true,
                            'ip_address'         => request()->ip(),
                            'device_fingerprint' => $fingerprint,
                            'is_suspicious'      => $isSuspicious,
                            'checkin_latitude'   => $latitude,
                            'checkin_longitude'  => $longitude,
                            'distance_meters'    => $distance,
                            'selfie_photo_path'  => $metaData['selfie_photo_path'] ?? null,
                            'face_match_score'   => $metaData['face_match_score'] ?? null,
                            'face_match_passed'  => $metaData['face_match_passed'] ?? null,
                            'liveness_score'     => $metaData['liveness_score'] ?? null,
                            'liveness_passed'    => $metaData['liveness_passed'] ?? null,
                            'detector_pipeline'  => $metaData['detector_pipeline'] ?? null,
                        ]);
                    } catch (UniqueConstraintViolationException|QueryException $e) {
                        return [
                            'success' => false,
                            'message' => 'คุณได้ทำการเช็คอินกิจกรรมนี้ไปแล้ว',
                        ];
                    }

                    return [
                        'success'         => true,
                        'message'         => 'เช็คอินเข้างานสำเร็จ! อย่าลืมสแกน QR ออกงานเมื่อจบกิจกรรมเพื่อบันทึกชั่วโมง',
                        'activity'        => $activity,
                        'status'          => 'checked_in',
                        'distance'        => $distance,
                        'selfie_required' => (bool) $activity->require_selfie_verification,
                        'attendance_id'   => $newAttendance->id,
                    ];
                }

                // ── กรณีที่ 2: เช็คอินออกงาน (Exit/Finalize) ──
                if (!$attendance) {
                    return ['success' => false, 'message' => 'กรุณาสแกน QR เข้างานก่อนที่จะบันทึกออกงาน'];
                }

                if ($attendance->checked_out_at) {
                    return ['success' => false, 'message' => 'คุณได้บันทึกจบกิจกรรมนี้ไปแล้ว'];
                }

                // ตรวจสอบว่ากิจกรรมเปิดให้บันทึกออกงานหรือยัง
                if ($activity->checkout_open_at && $now < $activity->checkout_open_at) {
                    return ['success' => false, 'message' => 'ยังไม่ถึงเวลาเปิดบันทึกกิจกรรม (ออกงาน)'];
                }
                if ($activity->checkout_close_at && $now > $activity->checkout_close_at) {
                    return ['success' => false, 'message' => 'หมดเวลาบันทึกกิจกรรม (ออกงาน) แล้ว'];
                }

                // ตรวจสอบชั่วโมงขั้นต่ำ
                if ($activity->min_hours_before_checkout > 0 && $attendance->checked_in_at) {
                    $hoursDiff = $attendance->checked_in_at->diffInMinutes($now) / 60;
                    if ($hoursDiff < $activity->min_hours_before_checkout) {
                        return [
                            'success' => false,
                            'message' => 'คุณเพิ่งเช็คอินเข้างาน ต้องเข้าร่วมกิจกรรมอย่างน้อย ' . (float) $activity->min_hours_before_checkout . ' ชั่วโมง จึงจะสามารถบันทึกออกงานได้',
                        ];
                    }
                }

                // คำนวณระยะทางขาออก (ถ้ามี)
                $exitDistance = null;
                if ($activity->hasGeolocation() && $latitude !== null && $longitude !== null) {
                    $exitDistance = $this->calculateDistance($activity->latitude, $activity->longitude, $latitude, $longitude);
                }

                // ตัดสินใจเรื่อง Auto Approve ท้ายกิจกรรม
                $autoApproved = !$activity->require_attendance_approval;
                
                // บันทึกการออกงาน (Finalize) ภายใน Transaction
                $attendance->update([
                    'checked_out_at'             => $now,
                    'checkout_method'            => $method,
                    'checkout_latitude'          => $latitude,
                    'checkout_longitude'         => $longitude,
                    'checkout_distance_meters'   => $exitDistance,
                    'checkout_selfie_photo_path' => $metaData['checkout_selfie_photo_path'] ?? null,
                    'checkout_face_match_score'  => $metaData['checkout_face_match_score'] ?? null,
                    'checkout_face_match_passed' => $metaData['checkout_face_match_passed'] ?? null,
                    'status'                     => $autoApproved ? 'approved' : 'pending',
                ]);

                // ปรับสถานะการลงทะเบียนเป็น completed แบบ Atomic
                if ($autoApproved && $registration) {
                    $registration->markAsCompleted();
                }

                return [
                    'success'       => true,
                    'message'       => $autoApproved ? 'บันทึกกิจกรรมสำเร็จ! ได้รับชั่วโมงกิจกรรมแล้ว' : 'บันทึกกิจกรรมแล้ว รอผู้จัดอนุมัติชั่วโมง',
                    'activity'      => $activity,
                    'status'        => $autoApproved ? 'approved' : 'pending',
                    'distance'      => $exitDistance,
                    'attendance_id' => $attendance->id,
                ];
            });
        });
    }

    /**
     * คำนวณระยะทางระหว่าง 2 จุดบนพื้นโลกด้วยสูตร Haversine
     * @return float ระยะทางหน่วยเมตร
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // รัศมีโลก (เมตร)

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = round($earthRadius * $c, 2);

        // ป้องกันค่าเกินขีดจำกัดฐานข้อมูล (Out of range) 
        return min($distance, 999999.99);
    }
}
