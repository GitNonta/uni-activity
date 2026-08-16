<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AdminRegistrationController extends Controller
{
    use LogsAdminActivity;

    /**
     * ดึงรายการคำขออนุมัติ (Pending) สำหรับแสดงใน Popup
     */
    public function pendingRequests(Activity $activity): JsonResponse
    {
        Gate::authorize('view', $activity);

        $pendingRegs = Registration::with('user')
            ->where('activity_id', $activity->id)
            ->where('status', 'pending')
            ->get()
            ->map(fn (Registration $r): array => [
                'id'         => $r->id,
                'type'       => 'registration',
                'student_id' => $r->user->student_id ?? '',
                'name'       => $r->user->full_name ?? '',
                'faculty'    => $r->user->faculty ?? '',
                'time'       => $r->created_at?->format('d/m H:i') ?? '',
                'details'    => 'ลงทะเบียนขอเข้าร่วม',
            ]);

        $pendingAtts = Attendance::with('user')
            ->where('activity_id', $activity->id)
            ->where('status', 'pending')
            ->get()
            ->map(fn (Attendance $a): array => [
                'id'         => $a->id,
                'type'       => 'attendance',
                'student_id' => $a->user->student_id ?? '',
                'name'       => $a->user->full_name ?? '',
                'faculty'    => $a->user->faculty ?? '',
                'time'       => $a->created_at?->format('d/m H:i') ?? '',
                'details'    => ($a->distance_meters ? "เช็คอินห่าง " . number_format($a->distance_meters, 0) . "ม." : "บันทึกกิจกรรมด้วยตนเอง"),
            ]);

        return response()->json([
            'activity_title' => $activity->title,
            'items'          => $pendingRegs->concat($pendingAtts)->sortByDesc('time')->values(),
        ]);
    }

    /**
     * อนุมัติการลงทะเบียนของนักศึกษา
     */
    public function approveRegistration(Registration $registration): RedirectResponse
    {
        $registration->loadMissing('activity');
        Gate::authorize('approve', $registration);

        $registration->update(['status' => 'approved']);
        $this->auditApprove($registration, "อนุมัติการลงทะเบียน #{$registration->id}");

        Notification::create([
            'user_id' => $registration->user_id,
            'title'   => 'การลงทะเบียนได้รับการอนุมัติ',
            'message' => "การขอเข้าร่วมกิจกรรม \"{$registration->activity->title}\" ได้รับการอนุมัติแล้ว",
            'type'    => 'registration_approved',
        ]);

        return back()->with('success', 'อนุมัติการลงทะเบียนสำเร็จ');
    }

    /**
     * ปฏิเสธการลงทะเบียนของนักศึกษา
     */
    public function rejectRegistration(Registration $registration): RedirectResponse
    {
        $registration->loadMissing('activity');
        Gate::authorize('reject', $registration);

        $registration->update(['status' => 'rejected']);
        $this->auditReject($registration, "ปฏิเสธการลงทะเบียน #{$registration->id}");

        Notification::create([
            'user_id' => $registration->user_id,
            'title'   => 'การลงทะเบียนถูกปฏิเสธ',
            'message' => "การขอเข้าร่วมกิจกรรม \"{$registration->activity->title}\" ไม่ได้รับการอนุมัติ",
            'type'    => 'registration_rejected',
        ]);

        return back()->with('success', 'ปฏิเสธการลงทะเบียนสำเร็จ');
    }
}
