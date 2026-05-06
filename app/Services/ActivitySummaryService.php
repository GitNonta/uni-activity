<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;

/**
 * เซอร์วิสสรุปชั่วโมงกิจกรรมของนักศึกษา
 * รวบรวมชั่วโมงที่เข้าร่วม แยกตามหมวดหมู่ เทียบกับชั่วโมงขั้นต่ำที่กำหนด
 */
class ActivitySummaryService
{
    /**
     * ดึงข้อมูลสรุปกิจกรรมของนักศึกษา
     * @return array{totalHours: float, totalRequired: float, byCategory: Collection}
     */
    public function getSummary(User $user): array
    {
        // ดึงการเข้าร่วมทั้งหมดพร้อมข้อมูลกิจกรรมและหมวดหมู่
        $attendances = Attendance::with('activity.category')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->get();

        // รวมชั่วโมงทั้งหมด
        $totalHours = $attendances->sum(fn($a) => (float) $a->activity->activity_hours);

        // แยกชั่วโมงตามหมวดหมู่
        $hoursByCategory = $attendances->groupBy(fn($a) => $a->activity->category_id)
            ->map(fn($group) => $group->sum(fn($a) => (float) $a->activity->activity_hours));

        // ดึงหมวดหมู่ทั้งหมด + คำนวณชั่วโมงขั้นต่ำรวม
        $categories = ActivityCategory::all();
        $categorySum  = (float) $categories->sum('required_hours');
        // ใช้ค่า override จาก admin ถ้ามีการตั้งค่า ไม่เช่นนั้นใช้ผลรวมจากหมวดหมู่
        $override     = Setting::get('total_required_hours');
        $totalRequired = ($override !== null) ? (float) $override : $categorySum;

        // สร้าง array ข้อมูลแต่ละหมวด: ชื่อ, ชั่วโมงที่ทำ, ชั่วโมงที่ต้องทำ
        $byCategory = $categories->map(fn($cat) => [
            'name'     => $cat->name,
            'hours'    => (float) ($hoursByCategory[$cat->id] ?? 0),
            'required' => (float) $cat->required_hours,
        ]);

        return [
            'totalHours'    => $totalHours,
            'totalRequired' => $totalRequired,
            'byCategory'    => $byCategory,
        ];
    }
}
