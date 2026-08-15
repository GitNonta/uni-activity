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
        $userIdStr = $user ? (string) $user->id : 'guest';
        
        $search = $request->get('search');
        $searchKey = $search ? '_' . md5((string) $search) : '';
        
        $announcements = \Illuminate\Support\Facades\Cache::remember("announcements_user_{$userIdStr}_page_{$page}{$searchKey}", 300, function () use ($user, $search) {
            $rawSearch = trim((string) $search);
            $cleanSearch = ltrim($rawSearch, '#');

            return Announcement::with('creator')
                ->forAudience($user)
                ->when($search, function ($q) use ($rawSearch, $cleanSearch) {
                    $q->where(function ($query) use ($rawSearch, $cleanSearch) {
                        $query->where('title', 'like', "%{$rawSearch}%")
                              ->orWhere('title', 'like', "%{$cleanSearch}%")
                              ->orWhere('content', 'like', "%{$rawSearch}%")
                              ->orWhere('content', 'like', "%{$cleanSearch}%");
                    });
                })
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString();
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
