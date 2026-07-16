<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityFeedback;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์จัดการดูการประเมินกิจกรรม (ฝั่ง Admin)
 */
class FeedbackAdminController extends Controller
{
    /** แสดงรายการ feedback ทั้งหมด */
    public function index(Request $request)
    {
        $query = ActivityFeedback::with(['activity', 'user'])
            ->when(auth()->user()->isStaff(), function ($q) {
                $q->whereHas('activity', function ($aq) {
                    $aq->where('created_by', auth()->id());
                });
            });

        // กรองตามกิจกรรม
        if ($request->filled('activity_id')) {
            $query->where('activity_id', $request->activity_id);
        }

        // กรองตามคะแนน
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // ค้นหาจากความคิดเห็น
        if ($request->filled('search')) {
            $query->where('comment', 'like', "%{$request->search}%");
        }

        $feedbacks = $query->latest()->paginate(20)->withQueryString();

        // รายการกิจกรรมสำหรับ dropdown
        $activitiesQuery = Activity::orderByDesc('activity_date');
        if (auth()->user()->isStaff()) {
            $activitiesQuery->where('created_by', auth()->id());
        }
        $activities = $activitiesQuery->take(50)->get(['id', 'title', 'activity_date']);

        // สถิติสรุป
        if (auth()->user()->isStaff()) {
            $baseStatsQuery = ActivityFeedback::whereHas('activity', fn($q) => $q->where('created_by', auth()->id()));
            $stats = [
                'total' => (clone $baseStatsQuery)->count(),
                'average' => round((float)(clone $baseStatsQuery)->avg('rating'), 1),
                'rating_5' => (clone $baseStatsQuery)->where('rating', 5)->count(),
                'rating_4' => (clone $baseStatsQuery)->where('rating', 4)->count(),
                'rating_3' => (clone $baseStatsQuery)->where('rating', 3)->count(),
                'rating_2' => (clone $baseStatsQuery)->where('rating', 2)->count(),
                'rating_1' => (clone $baseStatsQuery)->where('rating', 1)->count(),
            ];
        } else {
            $stats = [
                'total' => ActivityFeedback::count(),
                'average' => round((float)ActivityFeedback::avg('rating'), 1),
                'rating_5' => ActivityFeedback::where('rating', 5)->count(),
                'rating_4' => ActivityFeedback::where('rating', 4)->count(),
                'rating_3' => ActivityFeedback::where('rating', 3)->count(),
                'rating_2' => ActivityFeedback::where('rating', 2)->count(),
                'rating_1' => ActivityFeedback::where('rating', 1)->count(),
            ];
        }

        return view('admin.feedbacks.index', compact('feedbacks', 'activities', 'stats'));
    }

    /** แสดง feedback ของกิจกรรมเฉพาะ */
    public function show($activityId)
    {
        $activity = Activity::with(['feedbacks.user', 'category'])->findOrFail($activityId);
        if (auth()->user()->isStaff() && $activity->created_by !== auth()->id()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงผลประเมินนี้');
        }

        // สถิติของกิจกรรมนี้
        $stats = [
            'total' => $activity->feedbacks->count(),
            'average' => $activity->average_rating,
            'rating_5' => $activity->feedbacks->where('rating', 5)->count(),
            'rating_4' => $activity->feedbacks->where('rating', 4)->count(),
            'rating_3' => $activity->feedbacks->where('rating', 3)->count(),
            'rating_2' => $activity->feedbacks->where('rating', 2)->count(),
            'rating_1' => $activity->feedbacks->where('rating', 1)->count(),
        ];

        // คะแนนเฉลี่ยแยกตามหัวข้อ
        $detailedAvg = [
            'content' => 0,
            'speaker' => 0,
            'location' => 0,
            'organization' => 0,
        ];

        $feedbacksWithRatings = $activity->feedbacks->filter(fn($f) => !empty($f->ratings));
        if ($feedbacksWithRatings->count() > 0) {
            foreach (['content', 'speaker', 'location', 'organization'] as $key) {
                $values = $feedbacksWithRatings->pluck('ratings')->pluck($key)->filter();
                $detailedAvg[$key] = $values->count() > 0 ? round($values->avg(), 1) : 0;
            }
        }

        return view('admin.feedbacks.show', compact('activity', 'stats', 'detailedAvg'));
    }
}
