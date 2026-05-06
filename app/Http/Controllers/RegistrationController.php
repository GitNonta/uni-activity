<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Registration;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * คอนโทรลเลอร์การลงทะเบียนกิจกรรม
 * จัดการลงทะเบียน และยกเลิกการลงทะเบียนของนักศึกษา
 */
class RegistrationController extends Controller
{
    /**
     * ลงทะเบียนกิจกรรม
     * ตรวจสอบซ้ำ → ตรวจช่วงเวลา → ตรวจที่ว่าง → สร้างการลงทะเบียน + แจ้งเตือน
     */
    public function store(Request $request, $id)
    {
        $activity = Activity::findOrFail($id);
        $user = auth()->user();

        // ตรวจสอบว่าลงทะเบียนซ้ำหรือไม่
        $existing = Registration::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return back()->with('error', 'คุณลงทะเบียนกิจกรรมนี้แล้ว');
        }

        // ตรวจสอบช่วงเวลาลงทะเบียน
        $now = now();
        if ($now < $activity->register_open_at || $now > $activity->register_close_at) {
            return back()->with('error', 'ไม่อยู่ในช่วงเวลาลงทะเบียน');
        }

        // ตรวจสอบจำนวนที่ว่าง (ใช้ transaction ป้องกัน race condition)
        try {
            DB::transaction(function () use ($activity, $user) {
                $count = Registration::where('activity_id', $activity->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->count();

                if ($count >= $activity->max_participants) {
                    throw new \Exception('กิจกรรมนี้เต็มแล้ว');
                }

                Registration::create([
                    'user_id'     => $user->id,
                    'activity_id' => $activity->id,
                    'status'      => 'approved',
                ]);

                Notification::create([
                    'user_id' => $user->id,
                    'title'   => 'ลงทะเบียนสำเร็จ',
                    'message' => "คุณลงทะเบียนกิจกรรม \"{$activity->title}\" เรียบร้อยแล้ว",
                    'type'    => 'registration',
                ]);
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ลงทะเบียนกิจกรรมสำเร็จ!');
    }

    /**
     * ยกเลิกการลงทะเบียน
     * อนุญาตยกเลิกเฉพาะก่อนเวลาเช็คอินเปิด + สร้างแจ้งเตือน
     */
    public function destroy($id)
    {
        $registration = Registration::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $activity = $registration->activity;

        // อนุญาตยกเลิกเฉพาะก่อนกิจกรรมเริ่ม
        if (now() > $activity->checkin_open_at) {
            return back()->with('error', 'ไม่สามารถยกเลิกได้ กิจกรรมเริ่มแล้ว');
        }

        $registration->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);

        Notification::create([
            'user_id' => auth()->id(),
            'title'   => 'ยกเลิกการลงทะเบียน',
            'message' => "คุณยกเลิกการลงทะเบียนกิจกรรม \"{$activity->title}\"",
            'type'    => 'registration',
        ]);

        return back()->with('success', 'ยกเลิกการลงทะเบียนสำเร็จ');
    }
}
