<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;

/**
 * เซอร์วิสเช็คอิน / บันทึกกิจกรรม
 * ตรวจสอบเวลา, การลงทะเบียน, การเช็คอินซ้ำ และสร้างรายการ attendance
 */
class CheckInService
{
    /**
     * ดำเนินการเช็คอิน
     * ขั้นตอน: ค้นหากิจกรรมจาก token → ตรวจเวลา (ข้ามได้ถ้าเปิด early)
     * → ตรวจลงทะเบียน → ตรวจซ้ำ → ตรวจพิกัด → สร้าง attendance (approved/pending)
     *
     * @param  string      $token     QR token ของกิจกรรม
     * @param  User        $user      ผู้ใช้ที่เช็คอิน
     * @param  string      $method    วิธีเช็คอิน: qr_scan, self
     * @param  float|null  $latitude  ละติจูดจากอุปกรณ์ผู้ใช้
     * @param  float|null  $longitude ลองจิจูดจากอุปกรณ์ผู้ใช้
     * @return array{success: bool, message?: string, activity?: Activity, status?: string}
     */
    /**
     * ดำเนินการเช็คอิน (เข้างาน) หรือ บันทึกกิจกรรม (เลิกงาน)
     * 1. Check-in: ต้องลงทะเบียนแล้ว + อยู่ในพื้นที่กิจกรรม (ถ้ากำหนด)
     * 2. Finalize: ต้องเช็คอินแล้ว + จบกิจกรรม (หรือตามเงื่อนไขผู้จัด)
     */
    public function processCheckIn(string $token, User $user, string $method = 'qr_scan', ?float $latitude = null, ?float $longitude = null): array
    {
        $activity = Activity::where('qr_token', $token)->firstOrFail();
        $now = now();

        // 1. ตรวจสอบการลงทะเบียน
        $registration = Registration::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->where('status', 'approved')
            ->first();

        if (!$registration) {
            return ['success' => false, 'message' => 'คุณไม่ได้ลงทะเบียนกิจกรรมนี้ หรือยังไม่ได้รับการอนุมัติ'];
        }

        // 2. ค้นหารายการ Attendance เดิม
        $attendance = Attendance::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();

        // --- กรณีที่ 1: ยังไม่ได้เช็คอิน (Entry) ---
        if (!$attendance) {
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
                    return ['success' => false, 'message' => "คุณอยู่นอกพื้นที่กิจกรรม (ห่างประมาณ " . number_format($distance, 0) . " ม.)", 'distance' => $distance];
                }
            }

            // บันทึกการเข้างาน (Check-in)
            Attendance::create([
                'user_id'           => $user->id,
                'activity_id'       => $activity->id,
                'checked_in_at'     => $now,
                'method'            => $method,
                'status'            => 'pending', // เข้างานแล้วแต่ยังไม่จบกิจกรรม
                'is_verified'       => true,
                'ip_address'        => request()->ip(),
                'checkin_latitude'  => $latitude,
                'checkin_longitude' => $longitude,
                'distance_meters'   => $distance,
            ]);

            return [
                'success' => true,
                'message' => 'เช็คอินเข้างานสำเร็จ! อย่าลืมสแกนอีกครั้งเมื่อจบกิจกรรมเพื่อบันทึกชั่วโมง',
                'activity' => $activity,
                'status' => 'checked_in',
                'distance' => $distance,
            ];
        }

        // --- กรณีที่ 2: เช็คอินแล้ว จะทำการบันทึกจบกิจกรรม (Exit/Finalize) ---
        if ($attendance->checked_out_at) {
            return ['success' => false, 'message' => 'คุณได้บันทึกจบกิจกรรมนี้ไปแล้ว'];
        }

        // ตรวจสอบว่ากิจกรรมจบหรือยัง (อนุญาตให้ finalize ได้ตั้งแต่เริ่มงาน X นาที หรือตามเวลาปิด)
        // เพื่อความยืดหยุ่น จะเช็คว่าผ่านเวลาเริ่มงานมาแล้วหรือยัง
        if ($now < \Carbon\Carbon::parse($activity->activity_date->format('Y-m-d') . ' ' . $activity->start_time)) {
             return ['success' => false, 'message' => 'ยังไม่สามารถบันทึกจบกิจกรรมได้ จนกว่าจะถึงเวลาเริ่มงาน'];
        }

        // คำนวณระยะทางขาออก (ถ้ามี)
        $exitDistance = null;
        if ($activity->hasGeolocation() && $latitude !== null && $longitude !== null) {
            $exitDistance = $this->calculateDistance($activity->latitude, $activity->longitude, $latitude, $longitude);
        }

        // ตัดสินใจเรื่อง Auto Approve ท้ายกิจกรรม
        $autoApproved = !$activity->require_attendance_approval;
        
        // บันทึกการออกงาน (Finalize)
        $attendance->update([
            'checked_out_at'           => $now,
            'checkout_method'          => $method,
            'checkout_latitude'        => $latitude,
            'checkout_longitude'       => $longitude,
            'checkout_distance_meters' => $exitDistance,
            'status'                   => $autoApproved ? 'approved' : 'pending',
        ]);

        if ($autoApproved) {
            $registration->markAsCompleted();
        }

        return [
            'success'  => true,
            'message'  => $autoApproved ? 'บันทึกกิจกรรมสำเร็จ! ได้รับชั่วโมงกิจกรรมแล้ว' : 'บันทึกกิจกรรมแล้ว รอผู้จัดอนุมัติชั่วโมง',
            'activity' => $activity,
            'status'   => $autoApproved ? 'approved' : 'pending',
            'distance' => $exitDistance,
        ];
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
        // หากระยะทางมากกว่า 999,999 เมตร (ประมาณ 1,000 กม.) ให้ปัดเป็น 999,999.99 เพื่อให้บันทึกได้
        return min($distance, 999999.99);
    }
}
