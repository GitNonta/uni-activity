<?php

namespace App\Services;

use App\Models\Activity;

/**
 * เซอร์วิสคำนวณสถานะกิจกรรมแบบ realtime
 * คำนวณจากเวลาปัจจุบัน จำนวนผู้ลงทะเบียน และช่วงเวลาที่ตั้งไว้
 */
class ActivityStatusService
{
    /**
     * คำนวณสถานะกิจกรรมจากเงื่อนไขปัจจุบัน
     * ลำดับความสำคัญ: cancelled → done → ongoing → full → open → upcoming
     */
    public function computeStatus(Activity $activity): string
    {
        $now = now();
        $registered = $activity->registrations()->whereIn('status', ['pending', 'approved'])->count();

        if ($activity->status === 'cancelled') return 'cancelled'; // ยกเลิกแล้ว
        if ($now > $activity->checkin_close_at)  return 'done';      // เลยเวลาเช็คอิน
        if ($now >= $activity->checkin_open_at)  return 'ongoing';   // อยู่ในช่วงเช็คอิน
        if ($registered >= $activity->max_participants && $activity->max_participants > 0) return 'full'; // เต็มแล้ว
        if ($now >= $activity->register_open_at && $now <= $activity->register_close_at) return 'open';   // เปิดลงทะเบียน
        return 'upcoming'; // ยังไม่เปิดลงทะเบียน
    }

    /**
     * อัปเดตสถานะกิจกรรมในฐานข้อมูล
     * อัปเดตเฉพาะเมื่อสถานะเปลี่ยนไป และไม่ใช่กิจกรรมที่ยกเลิกแล้ว
     */
    public function updateStatus(Activity $activity): void
    {
        $newStatus = $this->computeStatus($activity);
        if ($activity->status !== $newStatus && $activity->status !== 'cancelled') {
            $activity->update(['status' => $newStatus]);
        }
    }
}
