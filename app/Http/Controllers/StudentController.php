<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityFeedback;
use App\Models\Attendance;
use App\Models\Registration;
use App\Services\ActivitySummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * คอนโทรลเลอร์หน้านักศึกษา
 * จัดการหน้ากิจกรรมของฉัน, ประวัติการเข้าร่วม, สรุปชั่วโมง, ปฏิทิน, แจ้งเตือน
 */
class StudentController extends Controller
{
    /** แสดงหน้าโปรไฟล์นักศึกษา: ข้อมูลส่วนตัว + สรุปชั่วโมง + ประวัติล่าสุด */
    public function profile(ActivitySummaryService $summaryService)
    {
        $user    = auth()->user();
        $summary = $summaryService->getSummary($user);

        $recentAttendances = Attendance::with('activity.category')
            ->where('user_id', $user->id)
            ->orderByDesc('checked_in_at')
            ->take(5)
            ->get();

        $totalActivities = Attendance::where('user_id', $user->id)
            ->where('status', 'approved')
            ->count();

        return view('student.profile', [
            'user'              => $user,
            'totalHours'        => $summary['totalHours'],
            'totalRequired'     => $summary['totalRequired'],
            'byCategory'        => $summary['byCategory'],
            'recentAttendances' => $recentAttendances,
            'totalActivities'   => $totalActivities,
        ]);
    }

    /**
     * แสดงกิจกรรมที่ลงทะเบียนไว้ + ภารกิจที่ต้องทำ
     * ส่ง attendanceMap, feedbackDoneIds, todoPending
     */
    public function myActivities()
    {
        $userId = auth()->id();

        $registrations = Registration::with('activity.category')
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'waitlisted'])
            ->orderByDesc('registered_at')
            ->get();

        // Map activity_id → attendance
        $attendanceMap = Attendance::where('user_id', $userId)
            ->get()
            ->keyBy('activity_id');

        // กิจกรรมที่ประเมินแล้ว
        $feedbackDoneIds = ActivityFeedback::where('user_id', $userId)
            ->pluck('activity_id')
            ->toArray();

        // Legacy: checkedInActivityIds (ยังใช้ใน walk-in section)
        $checkedInActivityIds = $attendanceMap
            ->where('status', 'approved')
            ->keys()
            ->toArray();

        // ── คำนวณ "ภารกิจที่ต้องทำ" ──
        $todos = collect();

        // Walk-in attendances (ไม่มี registration)
        $walkInAttendances = Attendance::with('activity.feedbacks')
            ->where('user_id', $userId)
            ->where('method', 'walk_in')
            ->whereNotIn('activity_id', $registrations->pluck('activity_id'))
            ->orderByDesc('created_at')
            ->get();

        foreach ($registrations as $reg) {
            $act = $reg->activity;
            if (!$act) continue;
            $att = $attendanceMap->get($reg->activity_id);
            $status = $act->computed_status;

            // 1. เช็คอินเปิดแล้ว — approved + window เปิด + ยังไม่เช็คอิน
            $checkinOpen = $act->allow_early_checkin ||
                (now() >= $act->checkin_open_at && now() <= $act->checkin_close_at);

            if ($reg->status === 'approved' && !$att && $checkinOpen) {
                $todos->push([
                    'type'       => 'checkin_open',
                    'priority'   => 1,
                    'activity'   => $act,
                    'reg_id'     => $reg->id,
                    'label'      => 'เช็คอินได้แล้ว!',
                    'color'      => '#16a34a',
                    'bg'         => '#f0fdf4',
                    'icon'       => 'check',
                    'action_url' => route('activities.show', $act->id),
                    'action_label' => 'เช็คอิน',
                ]);
                continue;
            }

            // 2. เช็คอินใกล้เปิด (ภายใน 2 ชม.)
            if ($reg->status === 'approved' && !$att &&
                $act->checkin_open_at && now()->diffInMinutes($act->checkin_open_at, false) > 0 &&
                now()->diffInMinutes($act->checkin_open_at, false) <= 120) {
                $todos->push([
                    'type'       => 'checkin_soon',
                    'priority'   => 2,
                    'activity'   => $act,
                    'label'      => 'เช็คอินเปิดใน '.now()->diffForHumans($act->checkin_open_at, true),
                    'color'      => '#d97706',
                    'bg'         => '#fffbeb',
                    'icon'       => 'clock',
                    'action_url' => route('activities.show', $act->id),
                    'action_label' => 'ดูรายละเอียด',
                ]);
            }

            // 3. รอประเมิน — เช็คอิน approved + กิจกรรมจบ + ยังไม่ประเมิน
            if ($att && $att->status === 'approved' &&
                in_array($status, ['done']) &&
                !in_array($act->id, $feedbackDoneIds)) {
                $todos->push([
                    'type'       => 'feedback',
                    'priority'   => 3,
                    'activity'   => $act,
                    'label'      => 'รอประเมิน',
                    'color'      => '#7c3aed',
                    'bg'         => '#faf5ff',
                    'icon'       => 'star',
                    'action_url' => route('feedback.create', $act->id),
                    'action_label' => 'ประเมิน',
                ]);
            }

            // 4. รออนุมัติ
            if ($reg->status === 'pending') {
                $todos->push([
                    'type'       => 'pending',
                    'priority'   => 5,
                    'activity'   => $act,
                    'label'      => 'รออนุมัติการลงทะเบียน',
                    'color'      => '#0369a1',
                    'bg'         => '#f0f9ff',
                    'icon'       => 'pending',
                    'action_url' => route('activities.show', $act->id),
                    'action_label' => 'ดูกิจกรรม',
                ]);
            }
            
            // 5. กำลังเข้าร่วมกิจกรรม (รอสแกนออกงาน)
            if ($att && $att->status === 'pending' && !$att->checked_out_at) {
                $todos->push([
                    'type'       => 'checkout_needed',
                    'priority'   => 1, // High priority
                    'activity'   => $act,
                    'label'      => 'กำลังเข้าร่วมกิจกรรม',
                    'color'      => '#b45309',
                    'bg'         => '#fef3c7',
                    'icon'       => 'clock',
                    'action_url' => route('activities.show', $act->id),
                    'action_label' => 'ดูกิจกรรม (อย่าลืมสแกนออกงาน)',
                ]);
            }
        }

        // walk-in รอประเมิน
        foreach ($walkInAttendances as $att) {
            if ($att->status === 'approved' &&
                $att->activity &&
                in_array($att->activity->computed_status, ['done']) &&
                !in_array($att->activity_id, $feedbackDoneIds)) {
                $todos->push([
                    'type'       => 'feedback',
                    'priority'   => 3,
                    'activity'   => $att->activity,
                    'label'      => 'รอประเมิน',
                    'color'      => '#7c3aed',
                    'bg'         => '#faf5ff',
                    'icon'       => 'star',
                    'action_url' => route('feedback.create', $att->activity_id),
                    'action_label' => 'ประเมิน',
                ]);
            }
        }

        $todos = $todos->sortBy('priority')->values();

        return view('student.my-activities', compact(
            'registrations',
            'checkedInActivityIds',
            'attendanceMap',
            'feedbackDoneIds',
            'walkInAttendances',
            'todos'
        ));
    }

    /** แสดงประวัติการเข้าร่วมกิจกรรมทั้งหมดที่เช็คอินแล้ว */
    public function history(Request $request)
    {
        $attendances = auth()->user()->attendances()
            ->with('activity.category')
            ->orderByDesc('checked_in_at')
            ->get();

        return view('student.history', compact('attendances'));
    }

    /** แสดงหน้าสรุปชั่วโมงกิจกรรม แยกตามหมวดหมู่ */
    public function summary(ActivitySummaryService $summaryService)
    {
        $data = $summaryService->getSummary(auth()->user());

        return view('student.summary', $data);
    }

    /** แสดงหน้าปฏิทินกิจกรรม */
    public function calendar()
    {
        return view('student.calendar');
    }

    /**
     * JSON endpoint: ดึงกิจกรรมสำหรับ FullCalendar
     * รวม: กิจกรรมที่ลงทะเบียน, กิจกรรมทั่วไปที่ยังเปิดรับ
     */
    public function calendarEvents(Request $request)
    {
        $userId = auth()->id();
        $user   = auth()->user();

        // กิจกรรมที่ลงทะเบียนแล้ว
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

        // กิจกรรมทั้งหมดที่เกี่ยวข้อง (ลงทะเบียนแล้ว + ที่เปิดอยู่)
        $activities = Activity::with('category')
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($registeredIds) {
                $q->whereIn('id', $registeredIds)
                  ->orWhereIn('status', ['upcoming', 'open', 'ongoing']);
            })
            ->where('activity_date', '>=', now()->subMonths(1))
            ->where('activity_date', '<=', now()->addMonths(3))
            ->get();

        $events = $activities->map(function ($act) use ($registeredIds, $checkedInIds, $feedbackDoneIds) {
            $isRegistered  = in_array($act->id, $registeredIds);
            $isCheckedIn   = in_array($act->id, $checkedInIds);
            $needsFeedback = $isCheckedIn && in_array($act->computed_status, ['done'])
                             && !in_array($act->id, $feedbackDoneIds);

            // สีตามสถานะ
            if ($isCheckedIn) {
                $color = '#16a34a'; // เขียว = เช็คอินแล้ว
            } elseif ($isRegistered) {
                $color = '#6366f1'; // ม่วง = ลงทะเบียนแล้ว
            } elseif (in_array($act->computed_status, ['open', 'upcoming'])) {
                $color = '#0ea5e9'; // ฟ้า = เปิดรับ
            } else {
                $color = '#94a3b8'; // เทา = อื่นๆ
            }

            return [
                'id'             => $act->id,
                'title'          => $act->title,
                'start'          => $act->activity_date->format('Y-m-d') . 'T' . ($act->start_time ?? '08:00'),
                'end'            => $act->activity_date->format('Y-m-d') . 'T' . ($act->end_time ?? '17:00'),
                'color'          => $color,
                'url'            => route('activities.show', $act->id),
                'extendedProps'  => [
                    'location'      => $act->location,
                    'hours'         => $act->activity_hours,
                    'category'      => $act->category->name ?? '-',
                    'status'        => $act->computed_status,
                    'is_registered' => $isRegistered,
                    'is_checked_in' => $isCheckedIn,
                    'needs_feedback'=> $needsFeedback,
                ],
            ];
        });

        return response()->json($events->values());
    }

    /**
     * JSON endpoint: รายการแจ้งเตือนสำหรับ navbar/banner
     * ส่งกลับ array ของ notifications
     */
    public function notifications(): JsonResponse
    {
        $userId = auth()->id();
        $cacheKey = "user_notifications_{$userId}";

        $alerts = Cache::remember($cacheKey, 60, function() use ($userId) {
            $innerAlerts = collect();

            // 1. ดึงข้อมูลจากฐานข้อมูล (notifications_custom)
            $dbNotifications = \App\Models\Notification::where('user_id', $userId)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($dbNotifications as $dn) {
                $icon = '🔔';
                switch ($dn->type) {
                    case 'registration_approved': $icon = '✅'; break;
                    case 'registration_rejected': $icon = '❌'; break;
                    case 'attendance_approved':   $icon = '🎓'; break;
                    case 'attendance_rejected':   $icon = '⚠️'; break;
                    case 'registration':          $icon = '📝'; break;
                }

                $innerAlerts->push([
                    'id'    => $dn->id,
                    'type'  => $dn->type,
                    'title' => $dn->title,
                    'body'  => $dn->message,
                    'url'   => '#', 
                    'icon'  => $icon,
                    'db'    => true,
                ]);
            }

            // 2. ตรวจสอบสถานะกิจกรรมปัจจุบันเพื่อแจ้งเตือนเช็คอิน
            $registrations = Registration::with('activity')
                ->where('user_id', $userId)
                ->where('status', 'approved')
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
                if (!$act) continue;

                $att = $attendanceMap->get($act->id);

                $checkinOpen = $act->allow_early_checkin ||
                    (now() >= $act->checkin_open_at && now() <= $act->checkin_close_at);

                if (!$att && $checkinOpen) {
                    $innerAlerts->push([
                        'type'    => 'checkin_open',
                        'title'   => 'เช็คอินได้แล้ว!',
                        'body'    => $act->title,
                        'url'     => route('activities.show', $act->id),
                        'icon'    => '🟢',
                    ]);
                } elseif (!$att && $act->checkin_open_at &&
                    now()->diffInMinutes($act->checkin_open_at, false) > 0 &&
                    now()->diffInMinutes($act->checkin_open_at, false) <= 60) {
                    $innerAlerts->push([
                        'type'    => 'checkin_soon',
                        'title'   => 'เช็คอินเปิดใน ' . now()->diffForHumans($act->checkin_open_at, true),
                        'body'    => $act->title,
                        'url'     => route('activities.show', $act->id),
                        'icon'    => '🔔',
                    ]);
                }

                // รอประเมิน
                if ($att && $att->status === 'approved' &&
                    in_array($act->computed_status, ['done']) &&
                    !in_array($act->id, $feedbackDoneIds)) {
                    $innerAlerts->push([
                        'type'  => 'feedback',
                        'title' => 'รอประเมินกิจกรรม',
                        'body'  => $act->title,
                        'url'   => route('feedback.create', $act->id),
                        'icon'  => '⭐',
                    ]);
                }
            }
            return $innerAlerts->values();
        });

        return response()->json(['alerts' => $alerts]);
    }

    /** แสดงหน้า QR Code ส่วนตัวสำหรับให้นักศึกษาแสดงให้เจ้าหน้าที่สแกน */
    public function showMyQr()
    {
        $user = auth()->user();
        return view('student.my-qr', compact('user'));
    }

    /** API สำหรับดึง Dynamic Token สำหรับ QR Code ส่วนตัว (เปลี่ยนทุก 30 วินาที) */
    public function getDynamicQrToken()
    {
        $userId = auth()->id();
        $timeWindow = floor(now()->timestamp / 30);
        
        $payload = $userId . '|' . $timeWindow;
        $signature = hash_hmac('sha256', $payload, config('app.key'));
        
        $token = base64_encode($payload . '|' . $signature);
        
        return response()->json([
            'token' => $token,
            'expires_in' => 30 - (now()->timestamp % 30),
        ]);
    }

    /** หน้าสแกน QR สำหรับนักศึกษา (สแกนเข้าร่วมกิจกรรม/เช็คอิน) */
    public function scanner()
    {
        return view('student.scanner');
    }

    /** ดาวน์โหลด PDF ใบแสดงผลการเข้าร่วมกิจกรรม */
    public function downloadPdf(ActivitySummaryService $summaryService)
    {
        $user = auth()->user();

        $summaryData = $summaryService->getSummary($user);

        $attendances = Attendance::with('activity.category')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->orderBy('checked_in_at')
            ->get();

        // Normalize Unicode สำหรับแก้ปัญหาสระซ้อนใน PDF
        $normalizeText = function($text) {
            if (!$text) return $text;
            return \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        };

        $user->full_name   = $normalizeText($user->full_name);
        $user->faculty     = $normalizeText($user->faculty);
        $user->department  = $normalizeText($user->department);

        foreach ($summaryData['byCategory'] as &$category) {
            $category['name'] = $normalizeText($category['name']);
        }

        foreach ($attendances as $attendance) {
            $attendance->activity->title = $normalizeText($attendance->activity->title);
            if ($attendance->activity->category) {
                $attendance->activity->category->name = $normalizeText($attendance->activity->category->name);
            }
        }

        $pdf = Pdf::loadView('pdf.activity-transcript', [
            'user'          => $user,
            'totalHours'    => $summaryData['totalHours'],
            'totalRequired' => $summaryData['totalRequired'],
            'byCategory'    => $summaryData['byCategory'],
            'attendances'   => $attendances,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'activity_transcript_' . $user->student_id . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }
}
