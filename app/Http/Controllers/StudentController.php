<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEnglishNameRequest;
use App\Services\ActivitySummaryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * คอนโทรลเลอร์หน้านักศึกษา (Thin Controller)
 * จัดการหน้ากิจกรรมของฉัน, ประวัติการเข้าร่วม, สรุปชั่วโมง, ปฏิทิน, แจ้งเตือน, และ PDF
 */
class StudentController extends Controller
{
    public function __construct(
        private readonly ActivitySummaryService $summaryService
    ) {}

    /**
     * แสดงหน้าโปรไฟล์นักศึกษา: ข้อมูลส่วนตัว + สรุปชั่วโมง + ประวัติล่าสุด
     */
    public function profile(Request $request): View
    {
        $data = $this->summaryService->getProfileData($request->user());

        return view('student.profile', $data);
    }

    /**
     * แสดงกิจกรรมที่ลงทะเบียนไว้ + ภารกิจที่ต้องทำ
     */
    public function myActivities(Request $request): View
    {
        $data = $this->summaryService->getMyActivitiesData($request->user());

        return view('student.my-activities', $data);
    }

    /**
     * แสดงประวัติการเข้าร่วมกิจกรรมทั้งหมดที่เช็คอินแล้ว
     */
    public function history(Request $request): View
    {
        $attendances = $this->summaryService->getHistory($request->user());

        return view('student.history', compact('attendances'));
    }

    /**
     * แสดงหน้าสรุปชั่วโมงกิจกรรม แยกตามหมวดหมู่
     */
    public function summary(Request $request): View
    {
        $data = $this->summaryService->getSummary($request->user());

        return view('student.summary', $data);
    }

    /**
     * แสดงหน้าปฏิทินกิจกรรม
     */
    public function calendar(): View
    {
        return view('student.calendar');
    }

    /**
     * JSON endpoint: ดึงกิจกรรมสำหรับ FullCalendar
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $events = $this->summaryService->getCalendarEvents($request->user());

        return response()->json($events);
    }

    /**
     * JSON endpoint: รายการแจ้งเตือนสำหรับ navbar/banner
     */
    public function notifications(Request $request): JsonResponse
    {
        $alerts = $this->summaryService->getNotifications($request->user());

        return response()->json(['alerts' => $alerts]);
    }

    /**
     * อัปเดตชื่อภาษาอังกฤษของนักศึกษา
     */
    public function updateEnglishName(UpdateEnglishNameRequest $request): RedirectResponse
    {
        $this->summaryService->updateEnglishName($request->user(), (string) $request->validated('english_name'));

        return redirect()->back()->with('success', 'อัปเดตชื่อภาษาอังกฤษเรียบร้อยแล้ว');
    }

    /**
     * หน้าสแกน QR สำหรับนักศึกษา (สแกนเข้าร่วมกิจกรรม/เช็คอิน)
     */
    public function scanner(): View
    {
        return view('student.scanner');
    }

    /**
     * ดาวน์โหลด PDF ใบแสดงผลการเข้าร่วมกิจกรรม
     */
    public function downloadPdf(Request $request): Response
    {
        $user = $request->user();
        $pdf = $this->summaryService->generateTranscriptPdf($user);
        $filename = 'activity_transcript_' . $user->student_id . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }
}
