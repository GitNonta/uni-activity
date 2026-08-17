<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\AttendeeCheckedIn;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * เซอร์วิสเช็คอิน / บันทึกกิจกรรม
 * จัดการกระบวนการเช็คอินและออกงานภายใต้ ACID Database Transactions เพื่อความสอดคล้องของข้อมูล 100%
 */
class CheckInService
{
    public function __construct(
        private readonly DeviceFingerprintService $fpService,
        private readonly SecurityService $secService,
        private readonly FaceVerificationService $faceVerificationService,
    ) {}

    /**
     * ดำเนินการเช็คอิน/ออกงานผ่าน QR Code พร้อมการตรวจสอบใบหน้าบน Server เท่านั้น (Server-Authoritative)
     */
    public function processQrCheckInWithFace(
        Activity $activity,
        User $user,
        string $token,
        ?string $selfieBase64 = null,
        ?float $latitude = null,
        ?float $longitude = null,
    ): array {
        $isCheckoutToken = ($activity->qr_checkout_token === $token);

        $score = null;
        $passed = null;
        $livenessPassed = null;
        $faceResult = null;

        // 1. ตรวจสอบ Face Verification บน Server เมื่อกิจกรรมกำหนดให้ต้องสแกนใบหน้า
        if ($activity->require_face_scan) {
            if (empty($selfieBase64)) {
                return [
                    'success' => false,
                    'message' => 'กิจกรรมนี้กำหนดให้ต้องสแกนใบหน้า กรุณาถ่ายภาพเซลฟี่เพื่อยืนยันตัวตน',
                ];
            }

            // ส่งรูปเซลฟี่ไปตรวจสอบบน Python AI Server โดยตรง (ห้ามเชื่อถือ Client-side Score)
            $faceResult = $this->faceVerificationService->verifyFace($user, (string) $selfieBase64, [
                'mode' => 'python',
            ]);

            $passed         = (bool) ($faceResult['is_match'] ?? false);
            $score          = (float) ($faceResult['score_percentage'] ?? 0);
            $livenessPassed = (bool) ($faceResult['liveness_passed'] ?? ($faceResult['liveness']['passed'] ?? true));

            // หากผู้ใช้มี Face Descriptor ลงทะเบียนไว้และ Server ตรวจไม่ผ่าน หรือติด Liveness Anti-Spoofing -> ปฏิเสธทันที
            if ($user->face_descriptor && (!$passed || !$livenessPassed)) {
                $reason = !$passed 
                    ? "ใบหน้าไม่ตรงกับข้อมูลในระบบ (คะแนนความคล้ายคลึง: " . round($score, 1) . "%)" 
                    : "การตรวจสอบ Liveness ไม่ผ่าน (ตรวจพบรูปถ่าย/ภาพปลอม)";

                Log::warning("Face Verification Rejected for user {$user->id} on activity {$activity->id}: {$reason}");

                return [
                    'success' => false,
                    'message' => "การยืนยันใบหน้าไม่ผ่าน: {$reason} กรุณาสแกนใบหน้าจริงใหม่อีกครั้ง",
                ];
            }
        }

        // 2. จัดเตรียมไฟล์รูปถ่ายเซลฟี่ (ถ้ามี) ก่อนเข้า Transaction
        $metaData = [];
        if (!empty($selfieBase64)) {
            $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $selfieBase64);
            $imageDecoded = base64_decode(str_replace(' ', '+', (string) $imageData));
            $tempFilename = 'selfies/' . ($isCheckoutToken ? 'checkout_' : '') . $user->id . '_' . time() . '.jpg';
            Storage::disk('public')->put($tempFilename, $imageDecoded);

            if ($isCheckoutToken) {
                $metaData['checkout_selfie_photo_path'] = $tempFilename;
                $metaData['checkout_face_match_score']  = $score;
                $metaData['checkout_face_match_passed'] = $passed;
            } else {
                $metaData['selfie_photo_path'] = $tempFilename;
                $metaData['face_match_score']  = $score;
                $metaData['face_match_passed'] = $passed;
                $metaData['liveness_score']    = (float) ($faceResult['liveness_score'] ?? ($faceResult['liveness']['score'] ?? 1.0));
                $metaData['liveness_passed']   = $livenessPassed;
            }
        }

        // 3. ดำเนินการบันทึก Check-in ผ่าน processCheckIn ภายใต้ Database Transaction
        $result = $this->processCheckIn(
            $token,
            $user,
            'qr_scan',
            $latitude,
            $longitude,
            $metaData,
        );

        // 4. เมื่อเช็คเอาท์ผ่านแล้ว ลบรูปชั่วคราวทิ้งตามหลักความเป็นส่วนตัว (PDPA Privacy)
        if ($result['success'] && $isCheckoutToken && $passed && !empty($result['attendance_id'])) {
            $att = Attendance::find($result['attendance_id']);
            if ($att) {
                if ($att->selfie_photo_path && Storage::disk('public')->exists($att->selfie_photo_path)) {
                    Storage::disk('public')->delete($att->selfie_photo_path);
                }
                if ($att->checkout_selfie_photo_path && Storage::disk('public')->exists($att->checkout_selfie_photo_path)) {
                    Storage::disk('public')->delete($att->checkout_selfie_photo_path);
                }
                $att->update([
                    'selfie_photo_path'          => null,
                    'checkout_selfie_photo_path' => null,
                ]);
            }
        }

        return $result;
    }

    /**
     * ดำเนินการ Walk-in Check-in: ค้นหานักศึกษาจากรหัส → บันทึก attendance อัตโนมัติ พร้อม Lock และ Broadcast
     */
    public function processWalkInCheckIn(
        Activity $activity,
        string $studentId,
        string $token,
        ?string $ip = null,
    ): array {
        $now = now();
        if (!$activity->allow_early_checkin && $now->lt($activity->checkin_open_at)) {
            return [
                'success' => false,
                'message' => 'ยังไม่ถึงเวลาเช็คอิน — เปิดเช็คอินเวลา ' . $activity->checkin_open_at->format('d/m/Y H:i'),
            ];
        }
        if ($now->gt($activity->checkin_close_at)) {
            return [
                'success' => false,
                'message' => 'หมดเวลาเช็คอินแล้ว (ปิดเมื่อ ' . $activity->checkin_close_at->format('d/m/Y H:i') . ')',
            ];
        }

        $user = User::where('student_id', $studentId)
            ->where('users.role', 'student')
            ->first();

        if (!$user) {
            return [
                'success' => false,
                'message' => 'ไม่พบรหัสนักศึกษา "' . $studentId . '" ในระบบ',
            ];
        }

        $lockKey = "walkin_lock_{$user->id}_{$activity->id}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($activity, $user, $token, $ip): array {
            return DB::transaction(function () use ($activity, $user, $token, $ip): array {
                if (Attendance::where('user_id', $user->id)->where('activity_id', $activity->id)->lockForUpdate()->exists()) {
                    return [
                        'success' => false,
                        'message' => 'นักศึกษา ' . $user->full_name . ' (' . $user->student_id . ') เช็คอินไปแล้ว',
                    ];
                }

                try {
                    Attendance::create([
                        'user_id'       => $user->id,
                        'activity_id'   => $activity->id,
                        'method'        => 'walk_in',
                        'status'        => 'approved',
                        'is_verified'   => true,
                        'checked_in_at' => now(),
                        'ip_address'    => $ip,
                    ]);
                } catch (UniqueConstraintViolationException|QueryException $e) {
                    return [
                        'success' => false,
                        'message' => 'นักศึกษา ' . $user->full_name . ' (' . $user->student_id . ') เช็คอินไปแล้ว',
                    ];
                }

                broadcast(new AttendeeCheckedIn($token, $user))->toOthers();

                return [
                    'success'            => true,
                    'message'            => 'บันทึกการเข้าร่วมของ ' . $user->full_name . ' (' . $user->student_id . ') สำเร็จ',
                    'checked_in_student' => [
                        'id'             => $user->id,
                        'name'           => $user->full_name,
                        'student_id'     => $user->student_id,
                        'activity_id'    => $activity->id,
                        'activity_title' => $activity->title,
                    ],
                ];
            });
        });
    }

    /**
     * ดึงรายชื่อผู้เข้าร่วมกิจกรรมสำหรับหน้า Walk-in Monitor
     */
    public function getWalkInAttendees(Activity $activity): array
    {
        return Attendance::with('user')
            ->where('activity_id', $activity->id)
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(fn(Attendance $att) => [
                'student_id'    => $att->user->student_id,
                'full_name'     => $att->user->full_name,
                'faculty'       => $att->user->faculty ?? '-',
                'checked_in_at' => $att->checked_in_at?->format('d/m/Y H:i:s') ?? $att->created_at->format('d/m/Y H:i:s'),
                'method'        => $att->method,
            ])
            ->toArray();
    }

    /**
     * ดำเนินการบันทึกภาพถ่าย Selfie + Face Verification ให้ Attendance Record เดิม
     */
    public function saveSelfieForAttendance(Attendance $attendance, User $user, string $selfieBase64, Activity $activity): array
    {
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $selfieBase64);
        $decoded = base64_decode(str_replace(' ', '+', (string) $imageData));

        $filename = 'selfies/' . $user->id . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $decoded);

        // ตรวจสอบผ่าน FaceVerificationService
        $faceResult = $this->faceVerificationService->verifyFace($user, $selfieBase64, [
            'mode' => 'python',
        ]);

        $score            = (float) ($faceResult['score_percentage'] ?? 0);
        $passed           = (bool) ($faceResult['is_match'] ?? false);
        $livenessScore    = $faceResult['liveness_score'] ?? ($faceResult['liveness']['score'] ?? null);
        $livenessPassed   = $faceResult['liveness_passed'] ?? ($faceResult['liveness']['passed'] ?? null);
        $detectorPipeline = $faceResult['detector_used'] ?? ($faceResult['pipeline'] ?? null);

        $attendance->update([
            'selfie_photo_path' => $filename,
            'face_match_score'  => $score,
            'face_match_passed' => $passed,
            'liveness_score'    => $livenessScore,
            'liveness_passed'   => $livenessPassed,
            'detector_pipeline' => $detectorPipeline,
        ]);

        return [
            'score'           => $score,
            'passed'          => $passed,
            'liveness_passed' => $livenessPassed,
        ];
    }

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
                        return [
                            'success' => false,
                            'message' => 'ยังไม่ถึงเวลาเช็คอิน — เปิดเช็คอินเวลา ' . $activity->checkin_open_at->format('d/m/Y H:i'),
                        ];
                    }
                    if ($now > $activity->checkin_close_at) {
                        return [
                            'success' => false,
                            'message' => 'หมดเวลาเช็คอินแล้ว (ปิดเมื่อ ' . $activity->checkin_close_at->format('d/m/Y H:i') . ')',
                        ];
                    }

                    // ตรวจสอบ Geofence (พิกัด GPS)
                    $entryDistance = null;
                    if ($activity->hasGeolocation()) {
                        if ($latitude === null || $longitude === null) {
                            return [
                                'success' => false,
                                'message' => 'กิจกรรมนี้จำเป็นต้องระบุพิกัด GPS กรุณาเปิดการระบุตำแหน่งบนอุปกรณ์ของคุณ',
                            ];
                        }

                        $entryDistance = $this->calculateDistance($activity->latitude, $activity->longitude, $latitude, $longitude);
                        if ($entryDistance > $activity->radius_meters) {
                            return [
                                'success' => false,
                                'message' => 'คุณอยู่นอกพื้นที่กิจกรรม (ห่าง ' . round($entryDistance) . ' ม. กำหนดไว้ไม่เกิน ' . $activity->radius_meters . ' ม.)',
                            ];
                        }
                    }

                    // สร้าง Attendance ใหม่แบบ Atomic
                    try {
                        $att = Attendance::create([
                            'user_id'             => $user->id,
                            'activity_id'         => $activity->id,
                            'method'              => $method,
                            'status'              => 'pending',
                            'latitude'            => $latitude,
                            'longitude'           => $longitude,
                            'distance_meters'     => $entryDistance,
                            'checked_in_at'       => $now,
                            'is_verified'         => true,
                            'ip_address'          => request()->ip(),
                            'selfie_photo_path'   => $metaData['selfie_photo_path'] ?? null,
                            'face_match_score'    => $metaData['face_match_score'] ?? null,
                            'face_match_passed'   => $metaData['face_match_passed'] ?? null,
                            'liveness_score'      => $metaData['liveness_score'] ?? null,
                            'liveness_passed'     => $metaData['liveness_passed'] ?? null,
                        ]);
                    } catch (UniqueConstraintViolationException|QueryException $e) {
                        return ['success' => false, 'message' => 'คุณเช็คอินไปแล้ว'];
                    }

                    // ตรวจจับพฤติกรรมผิดปกติผ่าน Security Service
                    $this->secService->checkSuspiciousCheckIn($user, $att, request());

                    return [
                        'success'         => true,
                        'message'         => 'เช็คอินสำเร็จ!',
                        'activity'        => $activity,
                        'status'          => 'checked_in',
                        'distance'        => $entryDistance,
                        'selfie_required' => (bool) $activity->require_selfie,
                        'attendance_id'   => $att->id,
                    ];
                }

                // ── กรณีที่ 2: การเช็คเอาท์ออกงาน (Checkout / Finalize) ──
                if (!$attendance) {
                    return [
                        'success' => false,
                        'message' => 'ไม่พบข้อมูลการเช็คอินของคุณ กรุณาสแกน QR Code เข้างานก่อน',
                    ];
                }

                if ($attendance->checked_out_at) {
                    return [
                        'success' => false,
                        'message' => 'คุณได้บันทึกกิจกรรมนี้ไปแล้วเมื่อ ' . $attendance->checked_out_at->format('H:i น.'),
                    ];
                }

                // ตรวจสอบเวลาเช็คเอาท์ขั้นต่ำ
                if ($activity->checkout_open_at && $now < $activity->checkout_open_at) {
                    return [
                        'success' => false,
                        'message' => 'ยังไม่ถึงเวลาออกงาน (เปิดให้ออกงานตั้งแต่ ' . $activity->checkout_open_at->format('H:i น.') . ')',
                    ];
                }

                // ตรวจสอบ Geofence ขาออก
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
