<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Admin Security Log Controller
 * แสดงรายการ security events ให้ admin ตรวจสอบ
 */
class SecurityLogController extends Controller
{
    /** แสดงรายการ security logs พร้อม filter */
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'เฉพาะ Admin เท่านั้น');

        $query = SecurityLog::with(['user:id,full_name,student_id', 'reviewer:id,full_name'])
            ->orderByDesc('created_at');

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by review status
        if ($request->filled('reviewed')) {
            $query->where('is_reviewed', $request->boolean('reviewed'));
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Filter by IP address
        if ($request->filled('ip')) {
            $query->where('ip_address', 'like', '%' . $request->ip . '%');
        }

        $logs     = $query->paginate(30)->withQueryString();
        $summary  = [
            'total'      => SecurityLog::count(),
            'unreviewed' => SecurityLog::where('is_reviewed', false)->count(),
            'today'      => SecurityLog::whereDate('created_at', today())->count(),
            'multi_acct' => SecurityLog::where('event_type', 'multi_account_login')->count(),
            'suspicious' => SecurityLog::where('event_type', 'suspicious_checkin')->count(),
        ];

        return view('admin.security.index', compact('logs', 'summary'));
    }

    /** ดูรายละเอียด security log */
    public function show(SecurityLog $securityLog)
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'เฉพาะ Admin เท่านั้น');

        $securityLog->load(['user', 'reviewer']);

        // โหลด users ที่เกี่ยวข้อง
        $relatedUsers = collect();
        if (!empty($securityLog->related_user_ids)) {
            $relatedUsers = User::whereIn('id', $securityLog->related_user_ids)
                ->select('id', 'full_name', 'student_id', 'faculty')
                ->get();
        }

        return view('admin.security.show', compact('securityLog', 'relatedUsers'));
    }

    /** Mark security log ว่าตรวจสอบแล้ว */
    public function markReviewed(SecurityLog $securityLog): JsonResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'เฉพาะ Admin เท่านั้น');

        $securityLog->update([
            'is_reviewed' => true,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'ทำเครื่องหมายตรวจสอบแล้ว']);
    }

    /** Mark ทั้งหมดว่าตรวจสอบแล้ว */
    public function markAllReviewed(): JsonResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'เฉพาะ Admin เท่านั้น');

        SecurityLog::where('is_reviewed', false)->update([
            'is_reviewed' => true,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'ทำเครื่องหมายทั้งหมดแล้ว']);
    }
}
