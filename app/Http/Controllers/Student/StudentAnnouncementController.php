<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\ListCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentAnnouncementController extends Controller
{
    /** หน้ารายการประกาศสำหรับนักศึกษา */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $page = $request->get('page', 1);

        $search = $request->get('search');
        $searchKey = $search ? '_' . md5((string) $search) : '';

        // Cache key is scoped by audience (faculty) so students can never be
        // served another faculty's cached list. The per-user id was removed:
        // students with the same faculty see identical data, and posting a new
        // announcement bumps the group version so new posts appear instantly.
        $audienceKey = ($user && $user->role === 'student') ? ($user->faculty ?? 'student') : 'guest';

        $announcements = ListCache::remember(
            ListCache::GROUP_ANNOUNCEMENTS,
            "audience_{$audienceKey}_page_{$page}{$searchKey}",
            300,
            function () use ($user, $search) {
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
            }
        );

        return view('student.announcements.index', compact('announcements'));
    }

    /** แสดงรายละเอียดประกาศ */
    public function show(Announcement $announcement): View
    {
        Gate::authorize('view', $announcement);

        $announcement->loadMissing('creator');

        return view('student.announcements.show', compact('announcement'));
    }
}
