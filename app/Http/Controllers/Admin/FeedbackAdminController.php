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
        $query = ActivityFeedback::with(['activity', 'user']);

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
        $activities = Activity::orderByDesc('activity_date')->take(50)->get(['id', 'title', 'activity_date']);

        // สถิติสรุป
        $stats = [
            'total' => ActivityFeedback::count(),
            'average' => round(ActivityFeedback::avg('rating'), 1),
            'rating_5' => ActivityFeedback::where('rating', 5)->count(),
            'rating_4' => ActivityFeedback::where('rating', 4)->count(),
            'rating_3' => ActivityFeedback::where('rating', 3)->count(),
            'rating_2' => ActivityFeedback::where('rating', 2)->count(),
            'rating_1' => ActivityFeedback::where('rating', 1)->count(),
        ];

        return view('admin.feedbacks.index', compact('feedbacks', 'activities', 'stats'));
    }

    /** แสดง feedback ของกิจกรรมเฉพาะ */
    public function show($activityId)
    {
        $activity = Activity::with(['feedbacks.user', 'category'])->findOrFail($activityId);

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
