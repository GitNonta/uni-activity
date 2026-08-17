<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StudentAnnouncementController extends Controller
{
    /** หน้ารายการประกาศสำหรับนักศึกษา */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $page = $request->get('page', 1);
        $userIdStr = $user ? (string) $user->id : 'guest';
        
        $search = $request->get('search');
        $searchKey = $search ? '_' . md5((string) $search) : '';
        
        $announcements = Cache::remember("announcements_user_{$userIdStr}_page_{$page}{$searchKey}", 300, function () use ($user, $search) {
            $rawSearch = trim((string) $search);
            $cleanSearch = ltrim($rawSearch, '#');

            return Announcement::with('creator')
                ->forAudience($user)
                ->when($search, function ($q) use ($rawSearch, $cleanSearch): void {
                    $q->where(function ($query) use ($rawSearch, $cleanSearch): void {
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
    public function show(Announcement $announcement): View
    {
        $user = auth()->user();
        $announcement->loadMissing('creator');

        // ตรวจสอบว่าประกาศนี้เปิดให้ audience นี้ดูหรือไม่
        $accessible = Announcement::where('id', $announcement->id)
            ->forAudience($user)
            ->exists();

        if (!$accessible) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงประกาศนี้');
        }

        return view('student.announcements.show', compact('announcement'));
    }
}
