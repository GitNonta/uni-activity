<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityFeedback;
use App\Models\Attendance;
use App\Models\JobListing;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecomputeActivityStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'stats';
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(
        public readonly ?int $staffUserId = null
    ) {}

    public function handle(): void
    {
        $cacheTtl = 600; // 10 minutes

        if ($this->staffUserId !== null) {
            $userId = $this->staffUserId;
            $stats = [
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

            Cache::put("admin_dashboard_stats_user_{$userId}", $stats, $cacheTtl);
            Log::info("RecomputeActivityStatisticsJob: Pre-warmed statistics for staff #{$userId}");
            return;
        }

        // Global Statistics
        $globalStats = [
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

        Cache::put('admin_dashboard_stats_global', $globalStats, $cacheTtl);
        Log::info('RecomputeActivityStatisticsJob: Pre-warmed global admin dashboard statistics.');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('RecomputeActivityStatisticsJob failed', [
            'staff_user_id' => $this->staffUserId,
            'error'         => $exception->getMessage(),
        ]);
    }
}
