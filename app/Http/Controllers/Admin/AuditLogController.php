<?php
declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminAuditLogResource;
use App\Http\Requests\Admin\AuditLogFilterRequest;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Repositories\AdminAuditLogRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AdminAuditLogRepository $auditLogRepo
    ) {}

    /**
     * Display the audit log UI with stats, filters, and paginated logs.
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $perPage = (int) $request->input('per_page', 20);

        // Build query with eager loading
        $query = AdminAuditLog::with('user');

        // Apply filters
        if ($request->filled('search')) {
            $query->where('description', 'ilike', '%' . $request->input('search') . '%');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        // Compute summary statistics
        $stats = [
            'total'   => AdminAuditLog::count(),
            'today'   => AdminAuditLog::whereDate('created_at', today())->count(),
            'creates' => AdminAuditLog::where('action', 'create')->count(),
            'updates' => AdminAuditLog::where('action', 'update')->count(),
            'deletes' => AdminAuditLog::where('action', 'delete')->count(),
            'logins'  => AdminAuditLog::where('action', 'login')->count(),
        ];

        // Admin users for the filter dropdown
        $admins = User::whereIn('role', ['admin', 'super-admin', 'staff'])
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return View::make('admin.audit-logs.index', compact('logs', 'stats', 'admins'));
    }

    /**
     * Return filtered audit logs as JSON (API).
     */
    public function filter(AuditLogFilterRequest $request): \Illuminate\Http\JsonResponse
    {
        $filters = $request->only(['user_id', 'action', 'date_from', 'date_to']);
        $perPage = $request->input('per_page', 20);
         $query = $this->auditLogRepo->model()::with('user');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $paginated = $query->orderByDesc('created_at')->paginate($perPage);

        return AdminAuditLogResource::collection($paginated);
    }

    /**
     * Show a single audit log entry detail page.
     */
    public function show(int $id): \Illuminate\View\View
    {
        $log = AdminAuditLog::with('user')->findOrFail($id);

        return View::make('admin.audit-logs.show', compact('log'));
    }
}
