<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuickApprovalRequest;
use App\Models\Attendance;
use App\Models\Registration;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminQuickApprovalController extends Controller
{
    use LogsAdminActivity;

    /**
     * AJAX: อนุมัติรายการ (registration หรือ attendance) จาก Dashboard ภายใต้ Transaction
     */
    public function approve(QuickApprovalRequest $request): JsonResponse
    {
        $user = Auth::user();
        $isStaff = $user->isStaff();

        $label = DB::transaction(function () use ($request, $user): string {
            if ($request->validated('type') === 'registration') {
                $item = Registration::with(['user', 'activity'])->findOrFail($request->validated('id'));
                Gate::authorize('approve', $item);

                $item->update(['status' => 'approved']);
                $this->auditApprove($item, "อนุมัติการลงทะเบียน #{$item->id} (Dashboard)");
                return 'ลงทะเบียน';
            }

            $item = Attendance::with(['user', 'activity'])->findOrFail($request->validated('id'));
            Gate::authorize('approve', $item);

            $item->update([
                'status'      => 'approved',
                'is_verified' => true,
                'verified_by' => $user->id,
            ]);
            $this->auditApprove($item, "อนุมัติการเข้าร่วม #{$item->id} (Dashboard)");

            // อัปเดต registration เป็น completed ถ้ามี (Atomic)
            $reg = Registration::where('user_id', $item->user_id)
                ->where('activity_id', $item->activity_id)
                ->first();
            if ($reg && $reg->status === 'approved') {
                $reg->markAsCompleted();
            }
            return 'เช็คอิน';
        });

        // นับ pending ใหม่หลัง approve (เช็ค role)
        if ($isStaff) {
            $pendingCount = Registration::whereHas('activity', fn ($q) => $q->where('created_by', $user->id))->where('status', 'pending')->count()
                          + Attendance::whereHas('activity', fn ($q) => $q->where('created_by', $user->id))->where('status', 'pending')->count();
        } else {
            $pendingCount = Registration::where('status', 'pending')->count()
                          + Attendance::where('status', 'pending')->count();
        }

        return response()->json([
            'ok'            => true,
            'message'       => "อนุมัติ{$label}สำเร็จ",
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * AJAX: ปฏิเสธรายการ (registration หรือ attendance) จาก Dashboard ภายใต้ Transaction
     */
    public function reject(QuickApprovalRequest $request): JsonResponse
    {
        $user = Auth::user();
        $isStaff = $user->isStaff();

        $label = DB::transaction(function () use ($request, $user): string {
            if ($request->validated('type') === 'registration') {
                $item = Registration::with(['user', 'activity'])->findOrFail($request->validated('id'));
                Gate::authorize('reject', $item);

                $item->update(['status' => 'rejected']);
                $this->auditReject($item, "ปฏิเสธการลงทะเบียน #{$item->id} (Dashboard)");
                return 'ลงทะเบียน';
            }

            $item = Attendance::with(['user', 'activity'])->findOrFail($request->validated('id'));
            Gate::authorize('reject', $item);

            $item->update([
                'status'      => 'rejected',
                'verified_by' => $user->id,
            ]);
            $this->auditReject($item, "ปฏิเสธการเข้าร่วม #{$item->id} (Dashboard)");
            return 'เช็คอิน';
        });

        // นับ pending ใหม่หลัง reject
        if ($isStaff) {
            $pendingCount = Registration::whereHas('activity', fn ($q) => $q->where('created_by', $user->id))->where('status', 'pending')->count()
                          + Attendance::whereHas('activity', fn ($q) => $q->where('created_by', $user->id))->where('status', 'pending')->count();
        } else {
            $pendingCount = Registration::where('status', 'pending')->count()
                          + Attendance::where('status', 'pending')->count();
        }

        return response()->json([
            'ok'            => true,
            'message'       => "ปฏิเสธ{$label}เรียบร้อยแล้ว",
            'pending_count' => $pendingCount,
        ]);
    }
}
