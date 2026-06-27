<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class StudentAnnouncementController extends Controller
{
    /** หน้ารายการประกาศสำหรับนักศึกษา */
    public function index(Request $request)
    {
        $user = auth()->user();
        $page = $request->get('page', 1);
        
        $announcements = \Illuminate\Support\Facades\Cache::remember("announcements_user_{$user->id}_page_{$page}", 300, function () use ($user) {
            return Announcement::with('creator')
                ->forAudience($user)
                ->orderByDesc('created_at')
                ->paginate(10);
        });

        return view('student.announcements.index', compact('announcements'));
    }

    /** แสดงรายละเอียดประกาศ */
    public function show($id)
    {
        $user = auth()->user();
        $announcement = Announcement::with('creator')
            ->forAudience($user)
            ->findOrFail($id);

        return view('student.announcements.show', compact('announcement'));
    }
}
