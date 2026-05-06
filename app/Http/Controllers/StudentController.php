<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Registration;
use App\Services\ActivitySummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * คอนโทรลเลอร์หน้านักศึกษา
 * จัดการหน้ากิจกรรมของฉัน, ประวัติการเข้าร่วม, สรุปชั่วโมง
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

    /** แสดงกิจกรรมที่ลงทะเบียนไว้ พร้อมสถานะการบันทึก (เช็คอิน) */
    public function myActivities()
    {
        $registrations = Registration::with('activity.category')
            ->where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'approved'])
            ->orderByDesc('registered_at')
            ->get();

        $checkedInActivityIds = Attendance::where('user_id', auth()->id())
            ->where('status', 'approved')
            ->pluck('activity_id')
            ->toArray();

        return view('student.my-activities', compact('registrations', 'checkedInActivityIds'));
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
            // แปลง Unicode เป็น NFC (Canonical Decomposition + Canonical Composition)
            return \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        };

        // Normalize ข้อมูลผู้ใช้
        $user->full_name = $normalizeText($user->full_name);
        $user->faculty = $normalizeText($user->faculty);
        $user->department = $normalizeText($user->department);

        // Normalize ข้อมูลหมวดหมู่
        foreach ($summaryData['byCategory'] as &$category) {
            $category['name'] = $normalizeText($category['name']);
        }

        // Normalize ข้อมูลกิจกรรม
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
