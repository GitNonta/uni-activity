<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Activity;
use App\Models\ActivityFeedback;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * เติมข้อมูลรีวิว/คะแนนให้หน้ารายละเอียดกิจกรรม (activities.show)
 *
 * - avgRating    : คะแนนเฉลี่ยจาก rating (cache 5 นาที)
 * - reviewsCount : จำนวนรีวิวรวม
 * - latestReviews: 5 รีวิวล่าสุด (คุมการแสดงชื่อด้วย is_anonymous)
 * - canReview    : เข้าร่วมกิจกรรมแล้ว (attendance approved) + กิจกรรมจบ + ยังไม่เคยประเมิน
 */
class ActivityFeedbackComposer
{
    public function compose(View $view): void
    {
        $activity = $view->getData()['activity'] ?? null;

        if (!$activity instanceof Activity) {
            return;
        }

        $stats = Cache::remember(
            "activity_feedback_stats_v1_{$activity->id}",
            300,
            fn (): object => ActivityFeedback::query()
                ->where('activity_id', $activity->id)
                ->selectRaw('COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as total')
                ->first()
        );

        $latest = Cache::remember(
            "activity_feedback_latest_v1_{$activity->id}",
            300,
            fn (): object => ActivityFeedback::query()
                ->where('activity_id', $activity->id)
                ->with('user:id,full_name,username')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
        );

        $canReview = false;
        $userId = Auth::id();

        if ($userId !== null && $activity->status === 'done') {
            $joined = Attendance::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->exists();

            $alreadyReviewed = $joined && ActivityFeedback::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $userId)
                ->exists();

            $canReview = $alreadyReviewed === false;
        }

        $view->with('avgRating', round((float) $stats->avg_rating, 1));
        $view->with('reviewsCount', (int) $stats->total);
        $view->with('latestReviews', $latest);
        $view->with('canReview', $canReview);
    }
}
