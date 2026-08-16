<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualCheckInRequest;
use App\Http\Requests\Admin\ReviewSelfieRequest;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use App\Traits\LogsAdminActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AdminAttendanceController extends Controller
{
    use LogsAdminActivity;

    /**
     * แสดงหน้าจอมอนิเตอร์เช็คอิน: ดูสถานะเช็คอินแบบ realtime
     */
    public function monitor(Activity $activity): View
    {
        Gate::authorize('view', $activity);
        $activity->loadMissing(['attendances.user', 'registrations.user']);

        return view('admin.checkin.monitor', compact('activity'));
    }

    /**
     * แสดงรายชื่อผู้ลงทะเบียนกิจกรรม
     */
    public function participants(Activity $activity): View
    {
        Gate::authorize('view', $activity);
        $activity->loadMissing(['registrations.user']);

        return view('admin.activities.participants', compact('activity'));
    }

    /**
     * เช็คอินแบบ manual โดยผู้ดูแล
     */
    public function manualCheckIn(ManualCheckInRequest $request, Activity $activity): RedirectResponse
    {
        Gate::authorize('manualCheckIn', [Attendance::class, $activity]);

        $user = User::where('student_id', $request->validated('student_id'))->first();
        if (!$user) {
            return back()->with('error', 'ไม่พบรหัสนักศึกษา');
        }

        $registration = Registration::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->where('status', 'approved')
            ->first();

        if (!$registration) {
            return back()->with('error', 'นักศึกษาไม่ได้ลงทะเบียนกิจกรรมนี้');
        }

        $exists = $activity->attendances()->where('user_id', $user->id)->exists();
        if ($exists) {
            return back()->with('error', 'นักศึกษาเช็คอินไปแล้ว');
        }

        $att = $activity->attendances()->create([
            'user_id'     => $user->id,
            'method'      => 'manual',
            'status'      => 'approved',
            'verified_by' => Auth::id(),
            'is_verified' => true,
            'ip_address'  => $request->ip(),
        ]);
        $this->auditCreate($att, "เช็คอิน manual: {$user->full_name} ในกิจกรรม \"{$activity->title}\"");

        return back()->with('success', "เช็คอิน {$user->full_name} สำเร็จ");
    }

    /**
     * อนุมัติการเข้าร่วมกิจกรรม (attendance)
     */
    public function approve(Attendance $attendance): RedirectResponse
    {
        $attendance->loadMissing('activity');
        Gate::authorize('approve', $attendance);

        $attendance->update([
            'status'      => 'approved',
            'is_verified' => true,
            'verified_by' => Auth::id(),
        ]);
        $this->auditApprove($attendance, "อนุมัติการเข้าร่วม #{$attendance->id}");

        Notification::create([
            'user_id' => $attendance->user_id,
            'title'   => 'บันทึกกิจกรรมสำเร็จ',
            'message' => "บันทึกการเข้าร่วมกิจกรรม \"{$attendance->activity->title}\" ได้รับการอนุมัติแล้ว คุณได้รับ {$attendance->activity->activity_hours} ชม.",
            'type'    => 'attendance_approved',
        ]);

        // เปลี่ยนสถานะการลงทะเบียนเป็น 'completed' เมื่ออนุมัติการเข้าร่วมแล้ว
        $registration = Registration::where('user_id', $attendance->user_id)
            ->where('activity_id', $attendance->activity_id)
            ->first();
        
        if ($registration && $registration->status === 'approved') {
            $registration->markAsCompleted();
        }

        return back()->with('success', 'อนุมัติการเข้าร่วมสำเร็จ');
    }

    /**
     * ปฏิเสธการเข้าร่วมกิจกรรม (attendance)
     */
    public function reject(Attendance $attendance): RedirectResponse
    {
        $attendance->loadMissing('activity');
        Gate::authorize('reject', $attendance);

        $attendance->update([
            'status'      => 'rejected',
            'verified_by' => Auth::id(),
        ]);
        $this->auditReject($attendance, "ปฏิเสธการเข้าร่วม #{$attendance->id}");

        Notification::create([
            'user_id' => $attendance->user_id,
            'title'   => 'บันทึกกิจกรรมไม่สำเร็จ',
            'message' => "บันทึกการเข้าร่วมกิจกรรม \"{$attendance->activity->title}\" ถูกปฏิเสธ กรุณาติดต่อผู้จัด",
            'type'    => 'attendance_rejected',
        ]);

        return back()->with('success', 'ปฏิเสธการเข้าร่วมสำเร็จ');
    }

    /**
     * ตรวจสอบและอนุมัติ/ปฏิเสธ Selfie
     */
    public function reviewSelfie(ReviewSelfieRequest $request, Attendance $attendance): RedirectResponse
    {
        $attendance->loadMissing('activity');
        Gate::authorize('reviewSelfie', $attendance);

        $action = $request->validated('action');
        
        $attendance->update([
            'selfie_reviewed'      => true,
            'selfie_review_result' => $action,
            'selfie_reviewed_by'   => Auth::id(),
            'status'               => $action === 'approve' ? 'approved' : 'rejected',
            'is_verified'          => $action === 'approve',
        ]);

        $this->auditApprove($attendance, ($action === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ') . " ภาพ Selfie รหัสเข้าร่วม #{$attendance->id}");

        return back()->with('success', 'ตรวจสอบภาพ Selfie เรียบร้อยแล้ว');
    }
}
