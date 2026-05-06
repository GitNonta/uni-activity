<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class StudentAnnouncementController extends Controller
{
    /** หน้ารายการประกาศสำหรับนักศึกษา */
    public function index()
    {
        $user = auth()->user();
        $announcements = Announcement::with('creator')
            ->forAudience($user)
            ->orderByDesc('created_at')
            ->paginate(10);

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
