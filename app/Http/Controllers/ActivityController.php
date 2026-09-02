<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Services\ActivityStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(private ActivityStatusService $statusService)
    {
    }

    public function index(Request $request): View
    {
        $sort = $request->input('sort', 'recommended');
        $user = auth()->user();
        $userId = $user?->id;
        $userFaculty = $user?->faculty ?? '';
        $userDept = $user?->department ?? '';

        // Cache categories (rarely changes, 1 hour)
        $categories = Cache::remember('activities:categories', 3600, fn() =>
            ActivityCategory::query()->orderBy('name')->get()
        );

        // Build cache key based on request params
        $cacheKey = 'activities:list:' . md5(serialize([
            'sort' => $sort,
            'status' => $request->input('status'),
            'category' => $request->input('category'),
            'mandatory' => $request->input('mandatory'),
            'search' => $request->input('search'),
            'scope' => $request->input('scope'),
            'page' => $request->input('page', 1),
            'faculty' => $userFaculty,
            'dept' => $userDept,
        ]));

        // Cache main activity list (5 minutes)
        $activities = Cache::remember($cacheKey, 300, function () use ($sort, $request, $userFaculty, $userDept) {
            $nowStr = now()->toDateTimeString();
            $threeDaysLaterStr = now()->addDays(3)->toDateTimeString();
            $todayStr = now()->toDateString();
            $sevenDaysLaterStr = now()->addDays(7)->toDateString();

            $query = Activity::query()
                ->with('category')
                ->withCount([
                    'registrations as registered_count' => fn($q) => $q->whereIn('status', ['pending', 'approved']),
                ])
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->when($request->category, fn($q) => $q->where('category_id', $request->category))
                ->when($request->mandatory, fn($q) => $q->where('is_mandatory', true))
                ->when($request->search, fn($q) => $q->where(function ($sq) use ($request) {
                    $rawSearch = trim((string) $request->search);
                    $cleanSearch = ltrim($rawSearch, '#');
                    $sq->where('title', 'like', "%{$rawSearch}%")
                       ->orWhere('title', 'like', "%{$cleanSearch}%")
                       ->orWhere('description', 'like', "%{$rawSearch}%")
                       ->orWhere('description', 'like', "%{$cleanSearch}%")
                       ->orWhere('location', 'like', "%{$cleanSearch}%");
                }))
                ->when($request->scope, fn($q) => $q->where('scope', $request->scope))
                ->where('status', '!=', 'cancelled')
                ->when($sort !== 'completed', fn($q) => $q->active());

            if ($sort === 'completed') {
                $query->where('status', 'done')
                      ->where('activity_date', '<', now()->subDays(7)->toDateString())
                      ->orderByDesc('activity_date');
            } elseif ($sort === 'closing_soon') {
                $query->orderByRaw("CASE WHEN register_close_at IS NOT NULL AND register_close_at >= ? THEN 0 ELSE 1 END ASC", [$nowStr])
                      ->orderBy('register_close_at', 'asc')
                      ->orderBy('activity_date', 'asc');
            } elseif ($sort === 'popular') {
                $query->orderBy('registered_count', 'desc')
                      ->orderBy('activity_date', 'asc');
            } elseif ($sort === 'upcoming') {
                $query->orderByRaw("CASE WHEN activity_date >= ? THEN 0 ELSE 1 END ASC", [$todayStr])
                      ->orderBy('activity_date', 'asc');
            } elseif ($sort === 'latest') {
                $query->orderBy('created_at', 'desc');
            } else {
                $scopeClauses = [];
                $scopeBindings = [];

                if ($userDept !== '') {
                    $scopeClauses[] = "WHEN scope = 'department' AND department = ? THEN 45";
                    $scopeBindings[] = $userDept;
                }
                if ($userFaculty !== '') {
                    $scopeClauses[] = "WHEN scope = 'faculty' AND faculty = ? THEN 35";
                    $scopeBindings[] = $userFaculty;
                }
                $scopeSqlPart = implode("\n", $scopeClauses);

                $scoreSql = "(
                    CASE
                        WHEN status = 'open' THEN 100
                        WHEN status = 'in_progress' THEN 80
                        WHEN status = 'closed' THEN 20
                        ELSE 0
                    END
                    + CASE WHEN is_mandatory = true THEN 50 ELSE 0 END
                    + CASE
                        {$scopeSqlPart}
                        WHEN scope = 'university' THEN 25
                        ELSE 0
                    END
                    + CASE WHEN register_close_at IS NOT NULL AND register_close_at >= ? AND register_close_at <= ? THEN 35 ELSE 0 END
                    + CASE WHEN activity_date >= ? AND activity_date <= ? THEN 25 ELSE 0 END
                )";

                $scoreBindings = array_merge(
                    $scopeBindings,
                    [$nowStr, $threeDaysLaterStr, $todayStr, $sevenDaysLaterStr]
                );

                $query->orderByRaw("{$scoreSql} DESC", $scoreBindings)
                      ->orderByRaw("CASE WHEN activity_date >= ? THEN 0 ELSE 1 END ASC", [$todayStr])
                      ->orderBy('activity_date', 'asc');
            }

            return $query->paginate(12)->withQueryString();
        });

        // User-specific data (cached 2 min per user)
        $registeredActivityIds = [];
        $attendedActivityIds = [];
        if ($userId) {
            $registeredActivityIds = Cache::remember("user:{$userId}:registered_ids", 120, fn() =>
                Registration::where('user_id', $userId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->pluck('activity_id')
                    ->toArray()
            );
            $attendedActivityIds = Cache::remember("user:{$userId}:attended_ids", 120, fn() =>
                Attendance::where('user_id', $userId)
                    ->where('status', 'approved')
                    ->pluck('activity_id')
                    ->toArray()
            );
        }

        // Cache geo activities (10 minutes)
        $geoActivities = Cache::remember('activities:geo:map', 600, fn() =>
            Activity::query()
                ->select(['id', 'title', 'location', 'latitude', 'longitude', 'activity_date', 'start_time', 'end_time', 'activity_hours', 'image_path'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereIn('status', ['upcoming', 'open', 'ongoing'])
                ->whereBetween('activity_date', [now()->subMonth()->toDateString(), now()->addMonths(3)->toDateString()])
                ->orderBy('activity_date')
                ->limit(200)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id, 'title' => $a->title, 'location' => $a->location,
                    'lat' => (float) $a->latitude, 'lng' => (float) $a->longitude,
                    'date' => $a->activity_date->format('d/m/Y'),
                    'start' => $a->start_time, 'end' => $a->end_time, 'hours' => $a->activity_hours,
                    'image' => $a->image_path ? asset('storage/' . $a->image_path) : null,
                    'url' => route('activities.show', $a->id),
                ])
        );

        // Cache completed activities (5 minutes per page)
        $completedPage = $request->input('completed_page', 1);
        $completedActivities = Cache::remember("activities:completed:{$completedPage}", 300, fn() =>
            Activity::query()
                ->with('category')
                ->oldCompleted()
                ->orderByDesc('activity_date')
                ->paginate(12, ['*'], 'completed_page')
                ->withQueryString()
        );

        return view('activities.index', compact('activities', 'categories', 'registeredActivityIds', 'attendedActivityIds', 'geoActivities', 'completedActivities'));
    }

    public function show(Activity $activity): View
    {
        $activity->loadMissing(['category', 'creator'])
            ->loadCount([
                'registrations as registered_count' => fn($query) => $query->whereIn('status', ['pending', 'approved']),
            ]);
        $this->statusService->updateStatus($activity);

        $user = auth()->user();

        if ($activity->status === 'cancelled' && (!$user || (!$user->isStaff() && !$user->isAdmin()))) {
            abort(403, 'กิจกรรมนี้ถูกยกเลิกแล้ว');
        }

        if ($user && $user->role === 'student') {
            if ($activity->scope === 'faculty' && $activity->faculty !== $user->faculty) {
                abort(403, 'กิจกรรมนี้สงวนสิทธิ์เฉพาะนักศึกษาคณะ ' . $activity->faculty . ' เท่านั้น');
            }
            if ($activity->scope === 'department' && $activity->department !== $user->department) {
                abort(403, 'กิจกรรมนี้สงวนสิทธิ์เฉพาะนักศึกษาสาขา ' . $activity->department . ' เท่านั้น');
            }
        }

        $userRegistration = null;
        $userAttendance = null;
        if ($user) {
            $userRegistration = $activity->registrations()->where('user_id', auth()->id())->first();
            $userAttendance = $activity->attendances()->where('user_id', auth()->id())->first();
        }

        return view('activities.show', compact('activity', 'userRegistration', 'userAttendance'));
    }
}
