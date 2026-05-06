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
    public function processCheckIn(string $token, User $user, string $method = 'qr_scan', ?float $latitude = null, ?float $longitude = null): array
    {
        // ค้นหากิจกรรมจาก QR token
        $activity = Activity::where('qr_token', $token)->firstOrFail();
        $now = now();

        // ตรวจช่วงเวลาเช็คอิน (ข้ามได้ถ้าผู้ดูแลเปิด allow_early_checkin)
        if (!$activity->allow_early_checkin) {
            if ($now < $activity->checkin_open_at) {
                return ['success' => false, 'message' => 'ยังไม่ถึงเวลาเช็คอิน'];
            }
            if ($now > $activity->checkin_close_at) {
                return ['success' => false, 'message' => 'หมดเวลาเช็คอินแล้ว'];
            }
        }

        // ตรวจสอบว่าลงทะเบียนและได้รับอนุมัติแล้ว
        $registration = Registration::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->where('status', 'approved')
            ->first();

        if (!$registration) {
            return ['success' => false, 'message' => 'คุณไม่ได้ลงทะเบียนกิจกรรมนี้ หรือยังไม่ได้รับการอนุมัติ'];
        }

        // ตรวจสอบว่าเช็คอินซ้ำหรือไม่
        if (Attendance::where('user_id', $user->id)->where('activity_id', $activity->id)->exists()) {
            return ['success' => false, 'message' => 'คุณเช็คอินไปแล้ว'];
        }

        // คำนวณระยะทางระหว่างนักศึกษากับสถานที่จัดกิจกรรม
        $distance = null;
        $autoApproved = false;

        if ($activity->hasGeolocation() && $latitude !== null && $longitude !== null) {
            $distance = $this->calculateDistance(
                $activity->latitude, $activity->longitude,
                $latitude, $longitude
            );
            // อยู่ในรัศมี → อนุมัติอัตโนมัติ
            $autoApproved = $distance <= $activity->checkin_radius;
        }

        // กำหนดสถานะ: อนุมัติอัตโนมัติถ้าอยู่ในรัศมี หรือรอผู้จัดอนุมัติ
        $status = $autoApproved ? 'approved' : 'pending';

        // สร้างรายการเข้าร่วมกิจกรรม
        Attendance::create([
            'user_id'           => $user->id,
            'activity_id'       => $activity->id,
            'method'            => $method,
            'status'            => $status,
            'is_verified'       => $autoApproved,
            'ip_address'        => request()->ip(),
            'checkin_latitude'  => $latitude,
            'checkin_longitude' => $longitude,
            'distance_meters'   => $distance,
        ]);

        // ถ้าอนุมัติอัตโนมัติ เปลี่ยนสถานะการลงทะเบียนเป็น 'completed'
        if ($autoApproved) {
            $registration->markAsCompleted();
        }

        return [
            'success'  => true,
            'activity' => $activity,
            'status'   => $status,
            'distance' => $distance,
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
