<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์แสดง Audit Log: ประวัติการกระทำของ Admin ทั้งหมด
 */
class AuditLogController extends Controller
{
    /** แสดงรายการ Audit Log พร้อมตัวกรอง */
    public function index(Request $request)
    {
        $query = AdminAuditLog::with('user')->latest();

        // กรองตาม admin
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // กรองตามประเภทการกระทำ
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // กรองตามประเภท model
        if ($request->filled('model_type')) {
            $query->where('model_type', 'like', '%' . $request->model_type);
        }

        // ค้นหาจากคำอธิบาย
        if ($request->filled('search')) {
            $query->where('description', 'like', "%{$request->search}%");
        }

        // กรองตามวันที่
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(25)->withQueryString();

        // รายชื่อ admin สำหรับ dropdown กรอง
        $admins = User::where('role', 'staff')->orderBy('full_name')->get(['id', 'full_name']);

        // สถิติสรุป
        $stats = [
            'total'   => AdminAuditLog::count(),
            'today'   => AdminAuditLog::whereDate('created_at', today())->count(),
            'creates' => AdminAuditLog::where('action', 'create')->count(),
            'updates' => AdminAuditLog::where('action', 'update')->count(),
            'deletes' => AdminAuditLog::where('action', 'delete')->count(),
        ];

        return view('admin.audit-logs.index', compact('logs', 'admins', 'stats'));
    }

    /** แสดงรายละเอียด log */
    public function show($id)
    {
        $log = AdminAuditLog::with('user')->findOrFail($id);
        return view('admin.audit-logs.show', compact('log'));
    }
}
