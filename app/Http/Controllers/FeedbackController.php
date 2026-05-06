<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityFeedback;
use App\Models\Attendance;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์จัดการการประเมินกิจกรรม (ฝั่งนักศึกษา)
 */
class FeedbackController extends Controller
{
    /** แสดงฟอร์มประเมินกิจกรรม */
    public function create($activityId)
    {
        $activity = Activity::with('category')->findOrFail($activityId);
        $user = auth()->user();

        // ตรวจสอบว่าเข้าร่วมกิจกรรมแล้วหรือไม่
        $attendance = Attendance::where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (!$attendance) {
            return redirect()->route('activities.index')
                ->with('error', 'คุณยังไม่ได้เข้าร่วมกิจกรรมนี้');
        }

        // ตรวจสอบว่าประเมินไปแล้วหรือไม่
        $existingFeedback = ActivityFeedback::where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->first();

        if ($existingFeedback) {
            return redirect()->route('activities.index')
                ->with('error', 'คุณได้ประเมินกิจกรรมนี้ไปแล้ว');
        }

        return view('student.feedback.create', compact('activity'));
    }

    /** บันทึกการประเมิน */
    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $user = auth()->user();

        // ตรวจสอบว่าเข้าร่วมกิจกรรมแล้วหรือไม่
        $attendance = Attendance::where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (!$attendance) {
            return back()->with('error', 'คุณยังไม่ได้เข้าร่วมกิจกรรมนี้');
        }

        // ตรวจสอบว่าประเมินไปแล้วหรือไม่
        $existingFeedback = ActivityFeedback::where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->exists();

        if ($existingFeedback) {
            return back()->with('error', 'คุณได้ประเมินกิจกรรมนี้ไปแล้ว');
        }

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'is_anonymous' => 'boolean',
            'rating_content' => 'nullable|integer|min:1|max:5',
            'rating_speaker' => 'nullable|integer|min:1|max:5',
            'rating_location' => 'nullable|integer|min:1|max:5',
            'rating_organization' => 'nullable|integer|min:1|max:5',
        ]);

        // รวมคะแนนแยกตามหัวข้อ
        $detailedRatings = [];
        if ($request->filled('rating_content')) {
            $detailedRatings['content'] = $request->rating_content;
        }
        if ($request->filled('rating_speaker')) {
            $detailedRatings['speaker'] = $request->rating_speaker;
        }
        if ($request->filled('rating_location')) {
            $detailedRatings['location'] = $request->rating_location;
        }
        if ($request->filled('rating_organization')) {
            $detailedRatings['organization'] = $request->rating_organization;
        }

        ActivityFeedback::create([
            'activity_id' => $activityId,
            'user_id' => $user->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'ratings' => !empty($detailedRatings) ? $detailedRatings : null,
            'is_anonymous' => $request->boolean('is_anonymous', false),
        ]);

        return redirect()->route('activities.index')
            ->with('success', 'ขอบคุณสำหรับการประเมินกิจกรรม!');
    }
}
