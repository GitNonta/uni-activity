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
use Illuminate\Support\Facades\Gate;

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
                    throw new \Exception(__('notify.err_not_in_window'));
                }

                $existing = Registration::where('user_id', $user->id)
                    ->where('activity_id', $activityLocked->id)
                    ->first();

                if ($existing && in_array($existing->status, ['pending', 'approved', 'completed', 'waitlisted'], true)) {
                    throw new \Exception(__('notify.err_already_registered'));
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
                    throw new \Exception(__('notify.err_time_conflict'));
                }

                $count = Registration::where('activity_id', $activityLocked->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->count();

                $statusToSet = 'approved';
                $messageToSet = __('notify.registered_msg', ['activity' => $activityLocked->title]);

                if ($activityLocked->max_participants > 0 && $count >= $activityLocked->max_participants) {
                    $statusToSet = 'waitlisted';
                    $messageToSet = __('notify.waitlisted_msg', ['activity' => $activityLocked->title]);
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
                    'title'   => $statusToSet === 'waitlisted' ? __('notify.waitlisted_title') : __('notify.registered_title'),
                    'message' => $messageToSet,
                    'type'    => 'registration',
                ]);

                // Clear notification cache
                Cache::forget("user_notifications_{$user->id}");
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('notify.flash_reg_success'));
    }

    /**
     * ยกเลิกการลงทะเบียน
     * อนุญาตยกเลิกเฉพาะก่อนเวลาเช็คอินเปิด + สร้างแจ้งเตือน
     */
    public function destroy(Registration $registration): RedirectResponse
    {
        Gate::authorize('delete', $registration);

        $activity = $registration->activity;

        // อนุญาตยกเลิกเฉพาะก่อนกิจกรรมเริ่ม
        if ($activity->checkin_open_at && now() > $activity->checkin_open_at) {
            return back()->with('error', __('notify.flash_cancel_too_late'));
        }

        DB::transaction(function () use ($registration, $activity): void {
            $prevStatus = $registration->status;

            $registration->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            Notification::create([
                'user_id' => auth()->id(),
                'title'   => __('notify.notify_cancel_title'),
                'message' => __('notify.notify_cancel_msg', ['activity' => $activity->title]),
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
                        'title'   => __('notify.waitlist_promoted_title'),
                        'message' => __('notify.waitlist_promoted_msg', ['activity' => $activity->title]),
                        'type'    => 'registration',
                    ]);
                }
            }
        });

        return back()->with('success', __('notify.flash_reg_cancel'));
    }
}
