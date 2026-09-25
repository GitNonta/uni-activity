<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * บริการประเมินและดำเนินการอนุมัติชั่วโมงกิจกรรมอัตโนมัติ (Smart Auto-Approval Service)
 * สำหรับลดภาระงานของเจ้าหน้าที่ในการตรวจทานกิจกรรมที่มีผู้เข้าร่วมจำนวนมาก
 */
class AutoApprovalService
{
    public const DEFAULT_MIN_FACE_SCORE = 80.0;
    public const DEFAULT_MAX_DISTANCE_METERS = 50.0;

    public function __construct(
        private readonly SecurityService $securityService
    ) {}

    /**
     * ตรวจสอบว่าระบบเปิดใช้งานกฎอนุมัติอัตโนมัติหรือไม่
     */
    public function isEnabled(): bool
    {
        $val = Setting::get('auto_approve_enabled', '1');
        return in_array($val, [true, 1, '1', 'true', 'yes'], true);
    }

    /**
     * ดึงเกณฑ์คะแนนความเหมือนใบหน้าขั้นต่ำ (%)
     */
    public function getMinFaceScore(): float
    {
        $val = Setting::get('auto_approve_min_face_score', self::DEFAULT_MIN_FACE_SCORE);
        return is_numeric($val) ? (float) $val : self::DEFAULT_MIN_FACE_SCORE;
    }

    /**
     * ดึงเกณฑ์ระยะทาง GPS สูงสุดที่อนุมัติได้ (เมตร)
     */
    public function getMaxDistanceMeters(): float
    {
        $val = Setting::get('auto_approve_max_distance', self::DEFAULT_MAX_DISTANCE_METERS);
        return is_numeric($val) ? (float) $val : self::DEFAULT_MAX_DISTANCE_METERS;
    }

    /**
     * ตรวจสอบว่าจำเป็นต้องผ่าน Liveness Anti-Spoofing หรือไม่
     */
    public function isLivenessRequired(): bool
    {
        $val = Setting::get('auto_approve_require_liveness', '1');
        return in_array($val, [true, 1, '1', 'true', 'yes'], true);
    }

    /**
     * ตรวจสอบว่าต้องปฏิเสธการอนุมัติอัตโนมัติเมื่อพบอุปกรณ์ซ้ำหรือไม่
     */
    public function isPreventSharedDevice(): bool
    {
        $val = Setting::get('auto_approve_prevent_shared_device', '1');
        return in_array($val, [true, 1, '1', 'true', 'yes'], true);
    }

    /**
     * ประเมินเงื่อนไขการอนุมัติอัตโนมัติ
     *
     * @param Activity $activity กิจกรรมที่เกี่ยวข้อง
     * @param array{
     *     face_match_passed?: bool|null,
     *     face_match_score?: float|null,
     *     liveness_passed?: bool|null,
     *     distance_meters?: float|null,
     *     is_mock_location?: bool,
     *     is_suspicious?: bool,
     *     is_shared_device?: bool,
     * } $data ข้อมูล telemetry และ verification จากการเช็คอิน
     * @return array{
     *     approved: bool,
     *     reasons: array<int, string>,
     *     metrics: array<string, mixed>,
     * }
     */
    public function evaluate(Activity $activity, array $data): array
    {
        $reasons = [];
        $metrics = [
            'auto_approve_enabled' => $this->isEnabled(),
            'min_face_score'       => $this->getMinFaceScore(),
            'max_distance'         => $this->getMaxDistanceMeters(),
            'require_liveness'     => $this->isLivenessRequired(),
            'prevent_shared'       => $this->isPreventSharedDevice(),
            'actual_face_score'    => $data['face_match_score'] ?? null,
            'actual_distance'      => $data['distance_meters'] ?? null,
            'liveness_passed'      => $data['liveness_passed'] ?? null,
            'is_suspicious'        => $data['is_suspicious'] ?? false,
            'is_shared_device'     => $data['is_shared_device'] ?? false,
            'is_mock_location'     => $data['is_mock_location'] ?? false,
        ];

        // 1. หากกิจกรรมไม่ได้เปิดระบบขออนุมัติเข้าร่วม (require_attendance_approval = false)
        // แสดงว่าผู้สร้างตั้งใจให้อนุมัติอยู่แล้ว
        if (!$activity->require_attendance_approval) {
            return [
                'approved' => true,
                'reasons'  => [],
                'metrics'  => $metrics,
            ];
        }

        // 2. หากปิดระบบ Smart Auto-Approval ในการตั้งค่าระดับมหาวิทยาลัย
        if (!$this->isEnabled()) {
            return [
                'approved' => false,
                'reasons'  => ['ระบบกฎอนุมัติอัตโนมัติส่วนกลางถูกปิดใช้งาน'],
                'metrics'  => $metrics,
            ];
        }

        // 3. ตรวจสอบการสแกนใบหน้า (Face Match & Liveness) เมื่อกิจกรรมกำหนดไว้
        if ($activity->require_face_scan) {
            $minScore = $this->getMinFaceScore();
            $faceScore = isset($data['face_match_score']) ? (float) $data['face_match_score'] : null;
            $facePassed = (bool) ($data['face_match_passed'] ?? false);

            if ($faceScore === null || $faceScore < $minScore) {
                $scoreText = $faceScore !== null ? round($faceScore, 1) . '%' : 'ไม่มีข้อมูล';
                $reasons[] = "คะแนนความคล้ายคลึงใบหน้าต่ำกว่าเกณฑ์ ({$scoreText} / เกณฑ์ ≥ {$minScore}%)";
            }

            if (!$facePassed) {
                $reasons[] = 'ผลการเปรียบเทียบใบหน้าไม่ตรงกับข้อมูลในระบบ';
            }

            if ($this->isLivenessRequired()) {
                $livenessPassed = $data['liveness_passed'] ?? null;
                if ($livenessPassed === false) {
                    $reasons[] = 'การตรวจสอบบุคคลจริง (Liveness Anti-Spoofing) ไม่ผ่าน';
                }
            }
        }

        // 4. ตรวจสอบระยะทาง GPS (Geofence Distance) เมื่อกิจกรรมมีพิกัด
        if ($activity->hasGeolocation()) {
            $maxDistance = $this->getMaxDistanceMeters();
            $distance = isset($data['distance_meters']) ? (float) $data['distance_meters'] : null;

            if ($distance === null) {
                $reasons[] = 'ไม่พบพิกัดตำแหน่ง GPS ขณะเช็คอิน';
            } elseif ($distance > $maxDistance) {
                $distText = round($distance, 1) . ' ม.';
                $reasons[] = "ระยะห่าง GPS ไกลกว่าเกณฑ์อนุมัติอัตโนมัติ ({$distText} / เกณฑ์ ≤ {$maxDistance} ม.)";
            }

            if (!empty($data['is_mock_location'])) {
                $reasons[] = 'ตรวจพบการใช้พิกัดจำลอง (Mock Location / Fake GPS)';
            }
        }

        // 5. ตรวจสอบความปลอดภัยและพฤติกรรมน่าสงสัย (Device & Risk Signals)
        if ($this->isPreventSharedDevice() && !empty($data['is_shared_device'])) {
            $reasons[] = 'ตรวจพบการใช้อุปกรณ์ร่วมกันหลายบัญชี (Shared Device Fingerprint)';
        }

        if (!empty($data['is_suspicious'])) {
            $reasons[] = 'ตรวจพบความเสี่ยงด้านความปลอดภัยในระบบ (Flagged as Suspicious)';
        }

        $isApproved = empty($reasons);

        return [
            'approved' => $isApproved,
            'reasons'  => $reasons,
            'metrics'  => $metrics,
        ];
    }

    /**
     * ดำเนินการบันทึกการอนุมัติอัตโนมัติอย่างสมบูรณ์แบบ Atomic
     */
    public function executeApproval(Attendance $attendance, ?Registration $registration = null): void
    {
        DB::transaction(function () use ($attendance, $registration): void {
            $attendance->loadMissing('activity');

            $attendance->update([
                'status'      => 'approved',
                'is_verified' => true,
            ]);

            // อัปเดตสถานะการลงทะเบียนเป็น completed หากมี
            $reg = $registration ?? Registration::where('user_id', $attendance->user_id)
                ->where('activity_id', $attendance->activity_id)
                ->first();

            if ($reg && $reg->status === 'approved') {
                $reg->markAsCompleted();
            }

            // แจ้งเตือนนักศึกษา
            $hours = $attendance->activity?->activity_hours ?? 0;
            $title = $attendance->activity?->title ?? 'กิจกรรม';

            Notification::create([
                'user_id' => $attendance->user_id,
                'title'   => 'อนุมัติชั่วโมงกิจกรรมอัตโนมัติ',
                'message' => "บันทึกการเข้าร่วมกิจกรรม \"{$title}\" ผ่านเกณฑ์การตรวจสอบเรียบร้อยแล้ว ได้รับอนุมัติ {$hours} ชม.",
                'type'    => 'attendance_approved',
            ]);

            Log::info("Smart Auto-Approval granted for Attendance #{$attendance->id}, User #{$attendance->user_id}, Activity #{$attendance->activity_id}");
        });
    }
}
