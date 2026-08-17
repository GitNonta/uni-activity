<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityFeedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * คอนโทรลเลอร์จัดการดูการประเมินกิจกรรม (ฝั่ง Admin)
 */
class FeedbackAdminController extends Controller
{
    /** แสดงรายการ feedback ทั้งหมด */
    public function index(Request $request): View
    {
        $query = ActivityFeedback::with(['activity', 'user'])
            ->when(auth()->user()->isStaff(), function ($q): void {
                $q->whereHas('activity', function ($aq): void {
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
                'total'    => (clone $baseStatsQuery)->count(),
                'average'  => round((float) (clone $baseStatsQuery)->avg('rating'), 1),
                'rating_5' => (clone $baseStatsQuery)->where('rating', 5)->count(),
                'rating_4' => (clone $baseStatsQuery)->where('rating', 4)->count(),
                'rating_3' => (clone $baseStatsQuery)->where('rating', 3)->count(),
                'rating_2' => (clone $baseStatsQuery)->where('rating', 2)->count(),
                'rating_1' => (clone $baseStatsQuery)->where('rating', 1)->count(),
            ];
        } else {
            $stats = [
                'total'    => ActivityFeedback::count(),
                'average'  => round((float) ActivityFeedback::avg('rating'), 1),
                'rating_5' => ActivityFeedback::where('rating', 5)->count(),
                'rating_4' => ActivityFeedback::where('rating', 4)->count(),
                'rating_3' => ActivityFeedback::where('rating', 3)->count(),
                'rating_2' => ActivityFeedback::where('rating', 2)->count(),
                'rating_1' => ActivityFeedback::where('rating', 1)->count(),
            ];
        }

        return view('admin.feedbacks.index', compact('feedbacks', 'activities', 'stats'));
    }

    /** แสดง feedback ของกิจกรรมเฉพาะแบบ Google Forms Analytics */
    public function show(Activity $activity): View
    {
        if (auth()->user()->isStaff() && $activity->created_by !== auth()->id()) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงผลประเมินนี้');
        }

        $activity->loadMissing('category');
        $feedbacks = $activity->feedbacks()->with('user')->orderBy('created_at', 'asc')->get();
        $totalFeedbacks = $feedbacks->count();

        $totalAttended = \App\Models\Attendance::where('activity_id', $activity->id)
            ->where('status', 'approved')
            ->count();

        // Ratings breakdown for overall
        $ratingsList = $feedbacks->pluck('rating')->filter()->values();
        $avgRating = $ratingsList->count() > 0 ? round((float) $ratingsList->avg(), 2) : 0.0;
        
        // Median calculation
        $median = 0.0;
        if ($ratingsList->count() > 0) {
            $sorted = $ratingsList->sort()->values();
            $count = $sorted->count();
            $middle = (int) floor($count / 2);
            if ($count % 2 === 0) {
                $median = round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 1);
            } else {
                $median = (float) $sorted[$middle];
            }
        }

        $stats = [
            'total'         => $totalFeedbacks,
            'totalAttended' => $totalAttended,
            'responseRate'  => $totalAttended > 0 ? round(($totalFeedbacks / $totalAttended) * 100, 1) : ($totalFeedbacks > 0 ? 100 : 0),
            'average'       => $avgRating,
            'median'        => $median,
            'rating_5'      => $feedbacks->where('rating', 5)->count(),
            'rating_4'      => $feedbacks->where('rating', 4)->count(),
            'rating_3'      => $feedbacks->where('rating', 3)->count(),
            'rating_2'      => $feedbacks->where('rating', 2)->count(),
            'rating_1'      => $feedbacks->where('rating', 1)->count(),
            'anonymous'     => $feedbacks->where('is_anonymous', true)->count(),
            'identified'    => $feedbacks->where('is_anonymous', false)->count(),
        ];

        // Detailed Topics Breakdown (Counts for 5, 4, 3, 2, 1 and average)
        $topics = [
            'content'      => 'เนื้อหากิจกรรมและประโยชน์ที่ได้รับ',
            'speaker'      => 'วิทยากร / ผู้บรรยาย / ผู้ดำเนินกิจกรรม',
            'location'     => 'สถานที่ / โสตทัศนูปกรณ์ / ระบบดิจิทัล',
            'organization' => 'การบริหารจัดการและการประสานงาน',
        ];

        $topicStats = [];
        foreach ($topics as $key => $label) {
            $vals = $feedbacks->pluck('ratings')->pluck($key)->filter()->map(fn($v) => (int)$v);
            $c = $vals->count();
            $topicStats[$key] = [
                'label'    => $label,
                'count'    => $c,
                'average'  => $c > 0 ? round((float) $vals->avg(), 2) : 0.0,
                'rating_5' => $vals->filter(fn($v) => $v === 5)->count(),
                'rating_4' => $vals->filter(fn($v) => $v === 4)->count(),
                'rating_3' => $vals->filter(fn($v) => $v === 3)->count(),
                'rating_2' => $vals->filter(fn($v) => $v === 2)->count(),
                'rating_1' => $vals->filter(fn($v) => $v === 1)->count(),
            ];
        }

        $clientFeedbacks = $feedbacks->map(fn($f) => [
            'id'           => $f->id,
            'user_name'    => $f->is_anonymous ? 'ไม่ระบุตัวตน (Anonymous)' : ($f->user->full_name ?? 'ผู้เข้าร่วม'),
            'student_code' => $f->is_anonymous ? '-' : ($f->user->student_code ?? $f->user->email ?? '-'),
            'is_anonymous' => (bool) $f->is_anonymous,
            'rating'       => (int) $f->rating,
            'ratings'      => $f->ratings ?? [],
            'comment'      => $f->comment ?? '',
            'time_thai'    => $f->created_at->translatedFormat('d M Y H:i น.'),
        ])->values()->all();

        return view('admin.feedbacks.show', compact('activity', 'feedbacks', 'clientFeedbacks', 'stats', 'topicStats'));
    }
}
