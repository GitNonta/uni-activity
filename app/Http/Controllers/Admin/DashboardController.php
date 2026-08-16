<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityFeedback;
use App\Models\AdminAuditLog;
use App\Models\Attendance;
use App\Models\JobListing;
use App\Models\Message;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Display the Admin Dashboard with cached statistics, recent activities, and pending lists.
     */
    public function index(): View
    {
        $user = Auth::user();
        $userId = $user->id;
        $isStaff = $user->isStaff();
        $cacheTtl = 300; // 5 minutes
        
        $cacheKey = $isStaff ? "admin_dashboard_stats_user_{$userId}" : "admin_dashboard_stats_global";

        // 1. Fetch main stats with caching
        $stats = Cache::remember($cacheKey, $cacheTtl, function () use ($isStaff, $userId): array {
            if ($isStaff) {
                return [
                    'totalActivities'      => Activity::where('created_by', $userId)->count(),
                    'upcomingActivities'   => Activity::where('created_by', $userId)->whereIn('status', ['upcoming', 'open'])->count(),
                    'totalStudents'        => User::where('role', 'student')->whereHas('registrations.activity', fn($q) => $q->where('created_by', $userId))->distinct()->count(),
                    'totalRegistrations'   => Registration::whereHas('activity', fn($q) => $q->where('created_by', $userId))->whereIn('status', ['pending', 'approved'])->count(),
                    'pendingRegistrations' => Registration::whereHas('activity', fn($q) => $q->where('created_by', $userId))->where('status', 'pending')->count(),
                    'pendingAttendances'   => Attendance::whereHas('activity', fn($q) => $q->where('created_by', $userId))->where('status', 'pending')->count(),
                    'upcomingThisWeek'     => Activity::where('created_by', $userId)
                        ->whereBetween('activity_date', [now()->startOfWeek(), now()->endOfWeek()])
                        ->whereIn('status', ['upcoming', 'open', 'ongoing'])
                        ->count(),
                    'totalJobs'            => JobListing::where('created_by', $userId)->count(),
                    'totalFeedbacks'       => ActivityFeedback::whereHas('activity', fn($q) => $q->where('created_by', $userId))->count(),
                ];
            }

            return [
                'totalActivities'      => Activity::count(),
                'upcomingActivities'   => Activity::whereIn('status', ['upcoming', 'open'])->count(),
                'totalStudents'        => User::where('role', 'student')->count(),
                'totalRegistrations'   => Registration::whereIn('status', ['pending', 'approved'])->count(),
                'pendingRegistrations' => Registration::where('status', 'pending')->count(),
                'pendingAttendances'   => Attendance::where('status', 'pending')->count(),
                'upcomingThisWeek'     => Activity::whereBetween('activity_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->whereIn('status', ['upcoming', 'open', 'ongoing'])
                    ->count(),
                'totalJobs'            => JobListing::count(),
                'totalFeedbacks'       => ActivityFeedback::count(),
            ];
        });

        // 2. Personal unread messages statistic
        $stats['unreadMessages'] = Cache::remember("user_{$userId}_unread_msgs", 60, function () use ($userId): int {
            return Message::whereHas('room', function ($q) use ($userId): void {
                $q->whereHas('users', function ($u) use ($userId): void {
                    $u->where('users.id', $userId);
                });
            })->where('user_id', '!=', $userId)
              ->where('created_at', '>', function ($subQuery) use ($userId): void {
                  $subQuery->select('last_read_at')
                      ->from('room_user')
                      ->whereColumn('room_user.room_id', 'messages.room_id')
                      ->where('room_user.user_id', $userId);
              })
              ->count();
        });

        // 3. Recent activity listings
        $recentActivitiesQuery = Activity::with('category')->orderByDesc('created_at');
        if ($isStaff) {
            $recentActivitiesQuery->where('created_by', $userId);
        }
        $recentActivities = $recentActivitiesQuery->take(5)->get();

        $pendingRegistrationsQuery = Registration::with(['user', 'activity'])->where('status', 'pending')->latest();
        if ($isStaff) {
            $pendingRegistrationsQuery->whereHas('activity', fn($q) => $q->where('created_by', $userId));
        }
        $pendingRegistrations = $pendingRegistrationsQuery->take(8)->get();

        $pendingAttendancesQuery = Attendance::with(['user', 'activity'])->where('status', 'pending')->latest();
        if ($isStaff) {
            $pendingAttendancesQuery->whereHas('activity', fn($q) => $q->where('created_by', $userId));
        }
        $pendingAttendances = $pendingAttendancesQuery->take(8)->get();

        $categories = ActivityCategory::all();
        
        $recentAuditLogsQuery = AdminAuditLog::with('user')->orderByDesc('created_at');
        if ($isStaff) {
            $recentAuditLogsQuery->where('user_id', $userId);
        }
        $recentAuditLogs = $recentAuditLogsQuery->take(6)->get();
        
        return view('admin.dashboard', compact(
            'stats',
            'recentActivities',
            'pendingRegistrations',
            'pendingAttendances',
            'categories',
            'recentAuditLogs'
        ));
    }
}
