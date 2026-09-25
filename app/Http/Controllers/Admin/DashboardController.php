<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityFeedback;
use App\Models\AdminAuditLog;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\JobListing;
use App\Models\Message;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        // 3. Trend comparison (this month vs last month) for KPI trend indicators
        $trendKey = $isStaff ? "dashboard_trend_user_{$userId}" : "dashboard_trend_global";
        $trend = Cache::remember($trendKey, $cacheTtl, function () use ($isStaff, $userId): array {
            $thisMonthStart = now()->startOfMonth();
            $lastMonthStart = now()->subMonth()->startOfMonth();
            $lastMonthEnd   = now()->subMonth()->endOfMonth();

            $actQ  = $isStaff ? Activity::where('created_by', $userId) : Activity::query();
            $regQ  = $isStaff ? Registration::whereHas('activity', fn($q) => $q->where('created_by', $userId)) : Registration::query();
            $attQ  = $isStaff ? Attendance::whereHas('activity', fn($q) => $q->where('created_by', $userId)) : Attendance::query();

            $thisAct  = (clone $actQ)->where('created_at', '>=', $thisMonthStart)->count();
            $lastAct  = (clone $actQ)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
            $thisReg  = (clone $regQ)->where('created_at', '>=', $thisMonthStart)->count();
            $lastReg  = (clone $regQ)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();
            $thisAtt  = (clone $attQ)->where('created_at', '>=', $thisMonthStart)->count();
            $lastAtt  = (clone $attQ)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

            $pct = static fn(int $curr, int $prev): int =>
                $prev > 0 ? (int) round((($curr - $prev) / $prev) * 100) : ($curr > 0 ? 100 : 0);

            return [
                'activitiesPct'     => $pct($thisAct, $lastAct),
                'registrationsPct'  => $pct($thisReg, $lastReg),
                'attendancesPct'    => $pct($thisAtt, $lastAtt),
            ];
        });

        // 4. Activity status breakdown for progress bars
        $activityBreakdown = Cache::remember("activity_breakdown_{$cacheKey}", $cacheTtl, function () use ($isStaff, $userId): array {
            $q = $isStaff ? Activity::where('created_by', $userId) : Activity::query();
            $total    = (clone $q)->count();
            $open     = (clone $q)->whereIn('status', ['open', 'upcoming'])->count();
            $ongoing  = (clone $q)->where('status', 'ongoing')->count();
            $closed   = (clone $q)->whereIn('status', ['closed', 'completed', 'cancelled'])->count();
            return compact('total', 'open', 'ongoing', 'closed');
        });

        // 5. Approval rate this month
        $approvalRate = Cache::remember("approval_rate_{$cacheKey}", $cacheTtl, function () use ($isStaff, $userId): int {
            $q     = $isStaff ? Registration::whereHas('activity', fn($q) => $q->where('created_by', $userId)) : Registration::query();
            $total = (clone $q)->whereIn('status', ['approved', 'rejected'])->where('created_at', '>=', now()->startOfMonth())->count();
            $appr  = (clone $q)->where('status', 'approved')->where('created_at', '>=', now()->startOfMonth())->count();
            return $total > 0 ? (int) round(($appr / $total) * 100) : 0;
        });

        // 6. Recent activity listings
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

        // 7. Recent job listings (latest 5, with applicant counts — no N+1)
        $recentJobsQuery = JobListing::query()->withCount('applications')->orderByDesc('created_at');
        if ($isStaff) {
            $recentJobsQuery->where('created_by', $userId);
        }
        $recentJobs = $recentJobsQuery
            ->take(5)
            ->get(['id', 'title', 'position', 'job_type', 'status', 'image_path', 'quota', 'start_date', 'created_at']);

        // 8. Recent announcements (latest 5)
        $recentAnnouncementsQuery = Announcement::query()->orderByDesc('created_at');
        if ($isStaff) {
            $recentAnnouncementsQuery->where('created_by', $userId);
        }
        $recentAnnouncements = $recentAnnouncementsQuery
            ->take(5)
            ->get(['id', 'title', 'type', 'target_faculty', 'image_path', 'is_active', 'published_at', 'created_at']);

        return view('admin.dashboard', compact(
            'stats',
            'trend',
            'activityBreakdown',
            'approvalRate',
            'recentActivities',
            'pendingRegistrations',
            'pendingAttendances',
            'categories',
            'recentAuditLogs',
            'recentJobs',
            'recentAnnouncements'
        ));
    }
}
