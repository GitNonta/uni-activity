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

        // ตรวจสอบช่วงเวลาลงทะเบียน
        $now = now();
        if ($now < $activity->register_open_at || $now > $activity->register_close_at) {
            return back()->with('error', 'ไม่อยู่ในช่วงเวลาลงทะเบียน');
        }

        // ตรวจสอบจำนวนที่ว่าง (ใช้ transaction ป้องกัน race condition)
        try {
            DB::transaction(function () use ($activity, $user) {
                $existing = Registration::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing && in_array($existing->status, ['pending', 'approved', 'completed', 'waitlisted'], true)) {
                    throw new \Exception('คุณลงทะเบียนกิจกรรมนี้แล้ว');
                }

                // ตรวจสอบเวลาซ้อนทับ (Overlap check)
                $overlapping = Registration::where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->whereHas('activity', function($q) use ($activity) {
                        $q->where('activity_date', $activity->activity_date)
                          ->where(function($q2) use ($activity) {
                              $q2->whereBetween('start_time', [$activity->start_time, $activity->end_time])
                                 ->orWhereBetween('end_time', [$activity->start_time, $activity->end_time])
                                 ->orWhere(function($q3) use ($activity) {
                                     $q3->where('start_time', '<=', $activity->start_time)
                                        ->where('end_time', '>=', $activity->end_time);
                                 });
                          });
                    })
                    ->exists();

                if ($overlapping) {
                    throw new \Exception('คุณมีกิจกรรมอื่นในช่วงเวลานี้แล้ว ไม่สามารถลงทะเบียนเวลาชนกันได้');
                }

                $count = Registration::where('activity_id', $activity->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->lockForUpdate()
                    ->count();

                $statusToSet = 'approved';
                $messageToSet = "คุณลงทะเบียนกิจกรรม \"{$activity->title}\" เรียบร้อยแล้ว";

                if ($activity->max_participants > 0 && $count >= $activity->max_participants) {
                    $statusToSet = 'waitlisted';
                    $messageToSet = "กิจกรรม \"{$activity->title}\" เต็มแล้ว คุณถูกจัดให้อยู่ใน Waitlist";
                }

                if ($existing) {
                    $existing->update([
                        'status' => $statusToSet,
                        'registered_at' => now(),
                        'cancelled_at' => null,
                        'note' => null,
                    ]);
                } else {
                    Registration::create([
                        'user_id'     => $user->id,
                        'activity_id' => $activity->id,
                        'status'      => $statusToSet,
                    ]);
                }

                Notification::create([
                    'user_id' => $user->id,
                    'title'   => $statusToSet === 'waitlisted' ? 'อยู่ใน Waitlist' : 'ลงทะเบียนสำเร็จ',
                    'message' => $messageToSet,
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

        // Auto-promote first waitlisted user
        if (in_array($registration->getOriginal('status'), ['approved', 'pending'])) {
            $firstWaitlisted = Registration::where('activity_id', $activity->id)
                ->where('status', 'waitlisted')
                ->orderBy('registered_at', 'asc')
                ->first();

            if ($firstWaitlisted) {
                $firstWaitlisted->update(['status' => 'approved']);
                Notification::create([
                    'user_id' => $firstWaitlisted->user_id,
                    'title'   => 'เลื่อนคิวสำเร็จ (Waitlist)',
                    'message' => "คุณได้รับการเลื่อนคิวและลงทะเบียนกิจกรรม \"{$activity->title}\" สำเร็จแล้ว",
                    'type'    => 'registration',
                ]);
            }
        }

        return back()->with('success', 'ยกเลิกการลงทะเบียนสำเร็จ');
    }
}
