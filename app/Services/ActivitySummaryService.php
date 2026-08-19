<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\ActivityFeedback;
use App\Models\Attendance;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * เซอร์วิสสรุปชั่วโมงกิจกรรมและจัดการข้อมูลหน้านักศึกษา
 * รวบรวมชั่วโมงที่เข้าร่วม, ภารกิจ (todos), ปฏิทิน, แจ้งเตือน, และการออก PDF transcript
 */
class ActivitySummaryService
{
    /**
     * ดึงข้อมูลสรุปกิจกรรมของนักศึกษา
     *
     * @return array{totalHours: float, totalRequired: float, byCategory: Collection<int, array{name: string, hours: float, required: float}>}
     */
    public function getSummary(User $user): array
    {
        $attendances = Attendance::with('activity.category')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->get();

        $totalHours = (float) $attendances->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0));

        $hoursByCategory = $attendances->groupBy(fn(Attendance $a) => $a->activity?->category_id)
            ->map(fn(Collection $group) => (float) $group->sum(fn(Attendance $a) => (float) ($a->activity?->activity_hours ?? 0)));

        $categories = ActivityCategory::all();
        $categorySum = (float) $categories->sum('required_hours');
        $override = Setting::get('total_required_hours');
        $totalRequired = ($override !== null) ? (float) $override : $categorySum;

        $byCategory = $categories->map(fn(ActivityCategory $cat) => [
            'name'     => $cat->name,
            'hours'    => (float) ($hoursByCategory[$cat->id] ?? 0),
            'required' => (float) $cat->required_hours,
        ]);

        return [
            'totalHours'    => $totalHours,
            'totalRequired' => $totalRequired,
            'byCategory'    => $byCategory,
        ];
    }

    /**
     * ดึงข้อมูลหน้าโปรไฟล์นักศึกษา พร้อมระบบแปลชื่อภาษาอังกฤษอัตโนมัติ (ถ้ายังไม่มี)
     *
     * @return array<string, mixed>
     */
    public function getProfileData(User $user): array
    {
        $this->ensureEnglishNameTranslated($user);

        $summary = $this->getSummary($user);

        $recentAttendances = Attendance::with('activity.category')
            ->where('user_id', $user->id)
            ->orderByDesc('checked_in_at')
            ->take(5)
            ->get();

        $totalActivities = Attendance::where('user_id', $user->id)
            ->where('status', 'approved')
            ->count();

        return [
            'user'              => $user,
            'totalHours'        => $summary['totalHours'],
            'totalRequired'     => $summary['totalRequired'],
            'byCategory'        => $summary['byCategory'],
            'recentAttendances' => $recentAttendances,
            'totalActivities'   => $totalActivities,
        ];
    }

    /**
     * รวบรวมข้อมูลสำหรับหน้า "กิจกรรมของฉัน" (My Activities) และคำนวณภารกิจที่ต้องทำ (Todos)
     *
     * @return array<string, mixed>
     */
    public function getMyActivitiesData(User $user): array
    {
        // 1. Mark unread DB notifications as read
        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        Cache::forget("user_notifications_{$user->id}");

        // 2. Fetch active registrations
        $registrations = Registration::with('activity.category')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'waitlisted'])
            ->whereHas('activity', function ($q) {
                $q->where('status', '!=', 'cancelled');
            })
            ->orderByDesc('registered_at')
            ->get();

        // 3. Map activity_id → attendance
        $attendanceMap = Attendance::where('user_id', $user->id)
            ->get()
            ->keyBy('activity_id');

        $feedbackDoneIds = ActivityFeedback::where('user_id', $user->id)
            ->pluck('activity_id')
            ->toArray();

        $checkedInActivityIds = $attendanceMap
            ->where('status', 'approved')
            ->keys()
            ->toArray();

        // 4. Walk-in attendances
        $walkInAttendances = Attendance::with('activity.feedbacks')
            ->where('user_id', $user->id)
            ->where('method', 'walk_in')
            ->whereNotIn('activity_id', $registrations->pluck('activity_id'))
            ->orderByDesc('created_at')
            ->get();

        // 5. Compute "ภารกิจที่ต้องทำ" (Todos)
        $todos = collect();

        foreach ($registrations as $reg) {
            $act = $reg->activity;
            if (!$act) {
                continue;
            }

            $att = $attendanceMap->get($reg->activity_id);
            $status = $act->computed_status;

            $checkinOpen = $act->allow_early_checkin ||
                (now() >= $act->checkin_open_at && now() <= $act->checkin_close_at);

            // เช็คอินเปิดแล้ว
            if ($reg->status === 'approved' && !$att && $checkinOpen) {
                $todos->push([
                    'type'         => 'checkin_open',
                    'priority'     => 1,
                    'activity'     => $act,
                    'reg_id'       => $reg->id,
                    'label'        => 'เช็คอินได้แล้ว!',
                    'color'        => '#16a34a',
                    'bg'           => '#f0fdf4',
                    'icon'         => 'check',
                    'action_url'   => route('activities.show', $act->id),
                    'action_label' => 'เช็คอิน',
                ]);
                continue;
            }

            // เช็คอินใกล้เปิด (ภายใน 2 ชม.)
            if (
                $reg->status === 'approved' && !$att &&
                $act->checkin_open_at && now()->diffInMinutes($act->checkin_open_at, false) > 0 &&
                now()->diffInMinutes($act->checkin_open_at, false) <= 120
            ) {
                $todos->push([
                    'type'         => 'checkin_soon',
                    'priority'     => 2,
                    'activity'     => $act,
                    'label'        => 'เช็คอินเปิดใน ' . now()->diffForHumans($act->checkin_open_at, true),
                    'color'        => '#d97706',
                    'bg'           => '#fffbeb',
                    'icon'         => 'clock',
                    'action_url'   => route('activities.show', $act->id),
                    'action_label' => 'ดูรายละเอียด',
                ]);
            }

            // รอประเมิน
            if (
                $att && $att->status === 'approved' &&
                in_array($status, ['done'], true) &&
                !in_array($act->id, $feedbackDoneIds, true)
            ) {
                $todos->push([
                    'type'         => 'feedback',
                    'priority'     => 3,
                    'activity'     => $act,
                    'label'        => 'รอประเมิน',
                    'color'        => '#7c3aed',
                    'bg'           => '#faf5ff',
                    'icon'         => 'star',
                    'action_url'   => route('feedback.create', $act->id),
                    'action_label' => 'ประเมิน',
                ]);
            }

            // รออนุมัติการลงทะเบียน
            if ($reg->status === 'pending') {
                $todos->push([
                    'type'         => 'pending',
                    'priority'     => 5,
                    'activity'     => $act,
                    'label'        => 'รออนุมัติการลงทะเบียน',
                    'color'        => '#0369a1',
                    'bg'           => '#f0f9ff',
                    'icon'         => 'pending',
                    'action_url'   => route('activities.show', $act->id),
                    'action_label' => 'ดูกิจกรรม',
                ]);
            }

            // กำลังเข้าร่วมกิจกรรม (รอสแกนออกงาน)
            if ($att && $att->status === 'pending' && !$att->checked_out_at) {
                $todos->push([
                    'type'         => 'checkout_needed',
                    'priority'     => 1,
                    'activity'     => $act,
                    'label'        => 'กำลังเข้าร่วมกิจกรรม',
                    'color'        => '#b45309',
                    'bg'           => '#fef3c7',
                    'icon'         => 'clock',
                    'action_url'   => route('activities.show', $act->id),
                    'action_label' => 'ดูกิจกรรม (อย่าลืมสแกนออกงาน)',
                ]);
            }
        }

        // Walk-in รอประเมิน
        foreach ($walkInAttendances as $att) {
            if (
                $att->status === 'approved' &&
                $att->activity &&
                in_array($att->activity->computed_status, ['done'], true) &&
                !in_array($att->activity_id, $feedbackDoneIds, true)
            ) {
                $todos->push([
                    'type'         => 'feedback',
                    'priority'     => 3,
                    'activity'     => $att->activity,
                    'label'        => 'รอประเมิน',
                    'color'        => '#7c3aed',
                    'bg'           => '#faf5ff',
                    'icon'         => 'star',
                    'action_url'   => route('feedback.create', $att->activity_id),
                    'action_label' => 'ประเมิน',
                ]);
            }
        }

        $sortedTodos = $todos->sortBy('priority')->values();

        return [
            'registrations'        => $registrations,
            'checkedInActivityIds' => $checkedInActivityIds,
            'attendanceMap'        => $attendanceMap,
            'feedbackDoneIds'      => $feedbackDoneIds,
            'walkInAttendances'    => $walkInAttendances,
            'todos'                => $sortedTodos,
        ];
    }

    /**
     * ดึงประวัติการเข้าร่วมกิจกรรมทั้งหมดของนักศึกษา
     *
     * @return Collection<int, Attendance>
     */
    public function getHistory(User $user): Collection
    {
        return $user->attendances()
            ->with('activity.category')
            ->orderByDesc('checked_in_at')
            ->get();
    }

    /**
     * ดึงกิจกรรมสำหรับแสดงผลบน FullCalendar
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCalendarEvents(User $user): Collection
    {
        $userId = $user->id;

        $registeredIds = Registration::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'waitlisted'])
            ->pluck('activity_id')
            ->toArray();

        $checkedInIds = Attendance::where('user_id', $userId)
            ->where('status', 'approved')
            ->pluck('activity_id')
            ->toArray();

        $feedbackDoneIds = ActivityFeedback::where('user_id', $userId)
            ->pluck('activity_id')
            ->toArray();

        $activities = Activity::with('category')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($registeredIds) {
                $q->whereIn('id', $registeredIds)
                    ->orWhereIn('status', ['upcoming', 'open', 'ongoing']);
            })
            ->where('activity_date', '>=', now()->subMonths(1))
            ->where('activity_date', '<=', now()->addMonths(3))
            ->get();

        return $activities->map(function (Activity $act) use ($registeredIds, $checkedInIds, $feedbackDoneIds) {
            $isRegistered  = in_array($act->id, $registeredIds, true);
            $isCheckedIn   = in_array($act->id, $checkedInIds, true);
            $needsFeedback = $isCheckedIn && in_array($act->computed_status, ['done'], true)
                && !in_array($act->id, $feedbackDoneIds, true);

            if ($isCheckedIn) {
                $color = '#16a34a'; // เขียว = เช็คอินแล้ว
            } elseif ($isRegistered) {
                $color = '#6366f1'; // ม่วง = ลงทะเบียนแล้ว
            } elseif (in_array($act->computed_status, ['open', 'upcoming'], true)) {
                $color = '#0ea5e9'; // ฟ้า = เปิดรับ
            } else {
                $color = '#94a3b8'; // เทา = อื่นๆ
            }

            $dateStr = $act->activity_date?->format('Y-m-d') ?? now()->format('Y-m-d');

            return [
                'id'            => $act->id,
                'title'         => $act->title,
                'start'         => $dateStr . 'T' . ($act->start_time ?? '08:00'),
                'end'           => $dateStr . 'T' . ($act->end_time ?? '17:00'),
                'color'         => $color,
                'url'           => route('activities.show', $act->id),
                'extendedProps' => [
                    'location'       => $act->location,
                    'hours'          => $act->activity_hours,
                    'category'       => $act->category?->name ?? '-',
                    'status'         => $act->computed_status,
                    'is_registered'  => $isRegistered,
                    'is_checked_in'  => $isCheckedIn,
                    'needs_feedback' => $needsFeedback,
                ],
            ];
        })->values();
    }

    /**
     * ดึงรายการแจ้งเตือนสำหรับ navbar / banner (พร้อม Cache 60 วินาที)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getNotifications(User $user): Collection
    {
        $userId = $user->id;
        $cacheKey = "user_notifications_{$userId}";

        return Cache::remember($cacheKey, 60, function () use ($userId) {
            $alerts = collect();

            // 1. ดึงข้อมูลจากฐานข้อมูล (Notification model)
            $dbNotifications = Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            foreach ($dbNotifications as $dn) {
                $icon = match ($dn->type) {
                    'registration_approved' => '✅',
                    'registration_rejected' => '❌',
                    'attendance_approved'   => '🎓',
                    'attendance_rejected'   => '⚠️',
                    'registration'          => '📝',
                    default                 => '🔔',
                };

                $alerts->push([
                    'id'    => $dn->id,
                    'type'  => $dn->type,
                    'title' => $dn->title,
                    'body'  => $dn->message,
                    'url'   => '#',
                    'icon'  => $icon,
                    'db'    => true,
                ]);
            }

            // 2. ตรวจสอบสถานะกิจกรรมปัจจุบันเพื่อแจ้งเตือนเช็คอิน/ประเมิน
            $registrations = Registration::with('activity')
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->whereHas('activity', function ($q) {
                    $q->where('status', '!=', 'cancelled');
                })
                ->orderByDesc('registered_at')
                ->limit(100)
                ->get();

            $feedbackDoneIds = ActivityFeedback::where('user_id', $userId)
                ->pluck('activity_id')
                ->toArray();

            $attendanceMap = Attendance::where('user_id', $userId)
                ->whereIn('activity_id', $registrations->pluck('activity_id'))
                ->get()
                ->keyBy('activity_id');

            foreach ($registrations as $reg) {
                $act = $reg->activity;
                if (!$act) {
                    continue;
                }

                $att = $attendanceMap->get($act->id);

                $checkinOpen = $act->allow_early_checkin ||
                    (now() >= $act->checkin_open_at && now() <= $act->checkin_close_at);

                if (!$att && $checkinOpen) {
                    $alerts->push([
                        'type'  => 'checkin_open',
                        'title' => 'เช็คอินได้แล้ว!',
                        'body'  => $act->title,
                        'url'   => route('activities.show', $act->id),
                        'icon'  => '🟢',
                    ]);
                } elseif (
                    !$att && $act->checkin_open_at &&
                    now()->diffInMinutes($act->checkin_open_at, false) > 0 &&
                    now()->diffInMinutes($act->checkin_open_at, false) <= 60
                ) {
                    $alerts->push([
                        'type'  => 'checkin_soon',
                        'title' => 'เช็คอินเปิดใน ' . now()->diffForHumans($act->checkin_open_at, true),
                        'body'  => $act->title,
                        'url'   => route('activities.show', $act->id),
                        'icon'  => '🔔',
                    ]);
                }

                // รอประเมิน
                if (
                    $att && $att->status === 'approved' &&
                    in_array($act->computed_status, ['done'], true) &&
                    !in_array($act->id, $feedbackDoneIds, true)
                ) {
                    $alerts->push([
                        'type'  => 'feedback',
                        'title' => 'รอประเมินกิจกรรม',
                        'body'  => $act->title,
                        'url'   => route('feedback.create', $act->id),
                        'icon'  => '⭐',
                    ]);
                }
            }

            return $alerts->values();
        });
    }

    /**
     * อัปเดตชื่อภาษาอังกฤษของนักศึกษา
     */
    public function updateEnglishName(User $user, string $englishName): void
    {
        $user->english_name = $englishName;
        $user->save();
    }

    /**
     * สร้าง PDF เอกสารใบแสดงผลการเข้าร่วมกิจกรรม (Activity Transcript) พร้อม Unicode Normalization
     */
    public function generateTranscriptPdf(User $user): DomPdfWrapper
    {
        $summaryData = $this->getSummary($user);

        $attendances = Attendance::with('activity.category')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->orderBy('checked_in_at')
            ->get();

        $normalizeText = function (?string $text): ?string {
            if (!$text) {
                return $text;
            }
            if (class_exists(\Normalizer::class)) {
                return \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
            }
            return $text;
        };

        $user->full_name  = $normalizeText($user->full_name);
        $user->faculty    = $normalizeText($user->faculty);
        $user->department = $normalizeText($user->department);

        $byCategory = $summaryData['byCategory']->map(function (array $cat) use ($normalizeText) {
            $cat['name'] = $normalizeText($cat['name']);
            return $cat;
        });

        foreach ($attendances as $attendance) {
            if ($attendance->activity) {
                $attendance->activity->title = $normalizeText($attendance->activity->title);
                if ($attendance->activity->category) {
                    $attendance->activity->category->name = $normalizeText($attendance->activity->category->name);
                }
            }
        }

        /** @var DomPdfWrapper $pdf */
        $pdf = Pdf::loadView('pdf.activity-transcript', [
            'user'          => $user,
            'totalHours'    => $summaryData['totalHours'],
            'totalRequired' => $summaryData['totalRequired'],
            'byCategory'    => $byCategory,
            'attendances'   => $attendances,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    /**
     * ตัวช่วยแปลชื่อภาษาอังกฤษอัตโนมัติหากยังไม่มีค่า
     */
    private function ensureEnglishNameTranslated(User $user): void
    {
        if (!empty($user->english_name) || empty($user->full_name)) {
            return;
        }

        try {
            $cleanName = str_replace(['นาย ', 'นางสาว ', 'นาง '], '', $user->full_name);
            // N6 fix: Use Laravel HTTP client instead of file_get_contents (prevents SSRF)
            $response = Http::timeout(5)
                ->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => 'gtx',
                    'sl'     => 'th',
                    'tl'     => 'en',
                    'dt'     => 't',
                    'q'      => $cleanName,
                ]);
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data[0][0][0])) {
                    $user->english_name = ucwords(strtolower(trim((string) $data[0][0][0])));
                    $user->save();
                }
            }
        } catch (\Throwable) {
            // Non-blocking fallback
        }
    }
}
