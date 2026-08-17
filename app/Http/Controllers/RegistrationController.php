<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Notification;
use App\Models\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * คอนโทรลเลอร์การลงทะเบียนกิจกรรม
 * จัดการลงทะเบียน และยกเลิกการลงทะเบียนของนักศึกษา
 */
class RegistrationController extends Controller
{
    /**
     * ลงทะเบียนกิจกรรม
     * ตรวจสอบซ้ำ → ตรวจช่วงเวลา → ตรวจที่ว่าง → สร้างการลงทะเบียน + แจ้งเตือน ภายใต้ Transaction
     */
    public function store(Request $request, Activity $activity): RedirectResponse
    {
        $user = auth()->user();

        // ตรวจสอบจำนวนที่ว่าง (ใช้ transaction ป้องกัน race condition)
        try {
            DB::transaction(function () use ($activity, $user): void {
                // ล็อค Activity record เพื่อป้องกัน concurrency
                $activityLocked = Activity::where('id', $activity->id)->lockForUpdate()->firstOrFail();

                // ตรวจสอบช่วงเวลาลงทะเบียน
                $now = now();
                if ($now < $activityLocked->register_open_at || $now > $activityLocked->register_close_at) {
                    throw new \Exception('ไม่อยู่ในช่วงเวลาลงทะเบียน');
                }

                $existing = Registration::where('user_id', $user->id)
                    ->where('activity_id', $activityLocked->id)
                    ->first();

                if ($existing && in_array($existing->status, ['pending', 'approved', 'completed', 'waitlisted'], true)) {
                    throw new \Exception('คุณลงทะเบียนกิจกรรมนี้แล้ว');
                }

                // ตรวจสอบเวลาซ้อนทับ (Overlap check)
                $overlapping = Registration::where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'approved', 'completed'])
                    ->whereHas('activity', function ($q) use ($activityLocked): void {
                        $q->where('activity_date', $activityLocked->activity_date)
                          ->where(function ($q2) use ($activityLocked): void {
                              $q2->whereBetween('start_time', [$activityLocked->start_time, $activityLocked->end_time])
                                 ->orWhereBetween('end_time', [$activityLocked->start_time, $activityLocked->end_time])
                                 ->orWhere(function ($q3) use ($activityLocked): void {
                                     $q3->where('start_time', '<=', $activityLocked->start_time)
                                        ->where('end_time', '>=', $activityLocked->end_time);
                                 });
                          });
                    })
                    ->exists();

                if ($overlapping) {
                    throw new \Exception('คุณมีกิจกรรมอื่นในช่วงเวลานี้แล้ว ไม่สามารถลงทะเบียนเวลาชนกันได้');
                }

                $count = Registration::where('activity_id', $activityLocked->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                $statusToSet = 'approved';
                $messageToSet = "คุณลงทะเบียนกิจกรรม \"{$activityLocked->title}\" เรียบร้อยแล้ว";

                if ($activityLocked->max_participants > 0 && $count >= $activityLocked->max_participants) {
                    $statusToSet = 'waitlisted';
                    $messageToSet = "กิจกรรม \"{$activityLocked->title}\" เต็มแล้ว คุณถูกจัดให้อยู่ใน Waitlist";
                }

                if ($existing) {
                    $existing->update([
                        'status'        => $statusToSet,
                        'registered_at' => now(),
                        'cancelled_at'  => null,
                        'note'          => null,
                    ]);
                } else {
                    Registration::create([
                        'user_id'     => $user->id,
                        'activity_id' => $activityLocked->id,
                        'status'      => $statusToSet,
                    ]);
                }

                Notification::create([
                    'user_id' => $user->id,
                    'title'   => $statusToSet === 'waitlisted' ? 'อยู่ใน Waitlist' : 'ลงทะเบียนสำเร็จ',
                    'message' => $messageToSet,
                    'type'    => 'registration',
                ]);

                // Clear notification cache
                Cache::forget("user_notifications_{$user->id}");
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
    public function destroy(Registration $registration): RedirectResponse
    {
        if ($registration->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403, 'ไม่มีสิทธิ์ยกเลิกการลงทะเบียนนี้');
        }

        $activity = $registration->activity;

        // อนุญาตยกเลิกเฉพาะก่อนกิจกรรมเริ่ม
        if ($activity->checkin_open_at && now() > $activity->checkin_open_at) {
            return back()->with('error', 'ไม่สามารถยกเลิกได้ กิจกรรมเริ่มแล้ว');
        }

        DB::transaction(function () use ($registration, $activity): void {
            $prevStatus = $registration->status;

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

            // Clear notification cache
            Cache::forget("user_notifications_" . auth()->id());

            // Auto-promote first waitlisted user
            if (in_array($prevStatus, ['approved', 'pending'], true)) {
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
        });

        return back()->with('success', 'ยกเลิกการลงทะเบียนสำเร็จ');
    }
}
