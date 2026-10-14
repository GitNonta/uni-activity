<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    public function toggle(Request $request, Activity $activity): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $existing = ActivityBookmark::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();
            $bookmarked = false;
        } else {
            ActivityBookmark::create(['user_id' => $user->id, 'activity_id' => $activity->id]);
            $bookmarked = true;
        }

        if ($request->expectsJson()) {
            return response()->json(['bookmarked' => $bookmarked]);
        }

        return back()->with('success', $bookmarked
            ? "บันทึกกิจกรรม \"{$activity->title}\" ไว้ในบุ๊กมาร์กแล้ว"
            : "ยกเลิกบุ๊กมาร์กกิจกรรม \"{$activity->title}\" แล้ว");
    }
}
