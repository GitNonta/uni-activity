<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * GeoSecurityService
 * ระบบตรวจสอบความถูกต้องของพิกัด GPS และตรวจจับการใช้งาน Mock Location / Fake GPS Apps
 */
class GeoSecurityService
{
    /**
     * รัศมีความแม่นยำขั้นต่ำที่เป็นไปได้จริงของ GPS บนสมาร์ตโฟน (เมตร)
     * พิกัดจากดาวเทียมจริงจะมีค่า Error Radius อย่างน้อย 1.5 - 3.0 เมตรขึ้นไป
     */
    private const MIN_REALISTIC_ACCURACY_METERS = 1.0;

    /**
     * ขีดจำกัดความเร็วสูงสุดที่เป็นไปได้ทางกายภาพ (กม./ชม.) สำหรับการเดินทางทั่วไป
     */
    private const MAX_FEASIBLE_VELOCITY_KMH = 220.0;

    /**
     * ตรวจสอบความถูกต้องและสัญญาณการปลอมแปลงของข้อมูลพิกัดและ Telemetry
     *
     * @param float|null $latitude
     * @param float|null $longitude
     * @param string|null $telemetryJson JSON string จาก Client-side telemetry collector
     * @param User $user
     * @param Activity $activity
     * @return array{
     *     is_mock: bool,
     *     is_suspicious: bool,
     *     reason: string|null,
     *     detail: string|null,
     *     risk_score: int,
     *     telemetry_data: array<string, mixed>
     * }
     */
    public function verify(
        ?float $latitude,
        ?float $longitude,
        ?string $telemetryJson,
        User $user,
        Activity $activity
    ): array {
        if ($latitude === null || $longitude === null) {
            return [
                'is_mock' => false,
                'is_suspicious' => false,
                'reason' => null,
                'detail' => null,
                'risk_score' => 0,
                'telemetry_data' => [],
            ];
        }

        $telemetry = [];
        if (!empty($telemetryJson)) {
            $decoded = json_decode($telemetryJson, true);
            if (is_array($decoded)) {
                $telemetry = $decoded;
            }
        }

        // 1. ตรวจสอบค่าความแม่นยำสังเคราะห์ (Synthetic / Impossible Accuracy)
        $accuracy = isset($telemetry['accuracy']) ? (float) $telemetry['accuracy'] : null;
        if ($accuracy !== null) {
            // แอป Mock GPS มักปล่อยค่า accuracy เป็น 0.0 หรือต่ำกว่า 1.0 เมตร (เป็นไปไม่ได้ทางฟิสิกส์ของสัญญาณดาวเทียมพลเรือน)
            if ($accuracy < self::MIN_REALISTIC_ACCURACY_METERS) {
                Log::warning("Mock GPS detected: Synthetic accuracy {$accuracy}m for user {$user->id}");
                return [
                    'is_mock' => true,
                    'is_suspicious' => true,
                    'reason' => 'synthetic_accuracy',
                    'detail' => 'ตรวจพบค่าความแม่นยำสังเคราะห์ผิดธรรมชาติ (Accuracy ต่ำกว่า 1 ม.)',
                    'risk_score' => 40,
                    'telemetry_data' => $telemetry,
                ];
            }
        }

        // 2. ตรวจสอบสัญญาณ DevTools / Headless Browser / Automation Spoofing
        if (!empty($telemetry['is_webdriver'])) {
            Log::warning("Mock GPS detected: WebDriver/Automation flag active for user {$user->id}");
            return [
                'is_mock' => true,
                'is_suspicious' => true,
                'reason' => 'devtools_emulated',
                'detail' => 'ตรวจพบการจำลองพิกัดผ่านระบบ Automation หรือ Developer Tools',
                'risk_score' => 40,
                'telemetry_data' => $telemetry,
            ];
        }

        if (!empty($telemetry['touch_mismatch'])) {
            Log::warning("Mock GPS detected: Mobile User-Agent without touch support for user {$user->id}");
            return [
                'is_mock' => true,
                'is_suspicious' => true,
                'reason' => 'touch_mismatch',
                'detail' => 'ตรวจพบการจำลองอุปกรณ์มือถือบนคอมพิวเตอร์ (User-Agent mismatch)',
                'risk_score' => 35,
                'telemetry_data' => $telemetry,
            ];
        }

        // 3. ตรวจสอบ Zero Satellite Jitter (พิกัดนิ่งผิดธรรมชาติเมื่อเก็บหลายตัวอย่าง)
        $sampleCount = isset($telemetry['sample_count']) ? (int) $telemetry['sample_count'] : 1;
        $jitter = isset($telemetry['jitter_variance']) ? (float) $telemetry['jitter_variance'] : null;

        if ($sampleCount >= 3 && $jitter !== null && $jitter === 0.0 && ($telemetry['is_stationary_mock'] ?? false)) {
            Log::warning("Mock GPS detected: Absolute zero satellite jitter across {$sampleCount} samples for user {$user->id}");
            return [
                'is_mock' => true,
                'is_suspicious' => true,
                'reason' => 'zero_jitter_mock',
                'detail' => 'ตรวจพบพิกัดนิ่งสนิทผิดธรรมชาติจากแอปจำลองตำแหน่ง (No GPS Doppler Jitter)',
                'risk_score' => 35,
                'telemetry_data' => $telemetry,
            ];
        }

        // 4. ตรวจสอบการเคลื่อนที่ด้วยความเร็วเหนือจริง (Impossible Teleportation Velocity)
        $velocityCheck = $this->checkImpossibleVelocity($user, $latitude, $longitude);
        if ($velocityCheck['is_teleporting']) {
            Log::warning("Mock GPS detected: Teleportation velocity {$velocityCheck['velocity_kmh']} km/h for user {$user->id}");
            return [
                'is_mock' => true,
                'is_suspicious' => true,
                'reason' => 'impossible_velocity',
                'detail' => "ตรวจพบการเปลี่ยนตำแหน่งด้วยความเร็วเหนือจริง ({$velocityCheck['velocity_kmh']} กม./ชม.)",
                'risk_score' => 40,
                'telemetry_data' => $telemetry,
            ];
        }

        // หากผ่านทุกข้อ -> พิกัดมีความน่าเชื่อถือสูง
        return [
            'is_mock' => false,
            'is_suspicious' => false,
            'reason' => null,
            'detail' => null,
            'risk_score' => 0,
            'telemetry_data' => $telemetry,
        ];
    }

    /**
     * ตรวจสอบว่าพิกัดปัจจุบันกระโดดจากพิกัดเช็คอินล่าสุดด้วยความเร็วเกินจริงหรือไม่
     *
     * @param User $user
     * @param float $currentLat
     * @param float $currentLng
     * @return array{is_teleporting: bool, velocity_kmh: float}
     */
    private function checkImpossibleVelocity(User $user, float $currentLat, float $currentLng): array
    {
        $lastAttendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereNotNull('checkin_latitude')
            ->whereNotNull('checkin_longitude')
            ->where('created_at', '>=', now()->subHours(6))
            ->latest('created_at')
            ->first();

        if (!$lastAttendance || !$lastAttendance->checkin_latitude || !$lastAttendance->checkin_longitude) {
            return ['is_teleporting' => false, 'velocity_kmh' => 0.0];
        }

        $prevLat = (float) $lastAttendance->checkin_latitude;
        $prevLng = (float) $lastAttendance->checkin_longitude;
        $timeDiffSeconds = Carbon::parse($lastAttendance->created_at)->diffInSeconds(now());

        // หากเวลาห่างกันน้อยกว่า 10 วินาที หรือมากกว่า 6 ชม. ข้ามการคำนวณ
        if ($timeDiffSeconds < 10) {
            return ['is_teleporting' => false, 'velocity_kmh' => 0.0];
        }

        $distanceKm = $this->haversineKm($prevLat, $prevLng, $currentLat, $currentLng);

        // ระยะทางน้อยกว่า 1 กม. ไม่ถือว่าเป็นการวาร์ป
        if ($distanceKm < 1.0) {
            return ['is_teleporting' => false, 'velocity_kmh' => 0.0];
        }

        $hours = $timeDiffSeconds / 3600.0;
        $velocityKmh = round($distanceKm / $hours, 1);

        if ($velocityKmh > self::MAX_FEASIBLE_VELOCITY_KMH) {
            return [
                'is_teleporting' => true,
                'velocity_kmh' => $velocityKmh,
            ];
        }

        return [
            'is_teleporting' => false,
            'velocity_kmh' => $velocityKmh,
        ];
    }

    /**
     * คำนวณระยะทาง Haversine เป็นกิโลเมตร
     */
    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
