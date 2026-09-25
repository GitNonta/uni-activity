<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\GraduationAuditService;
use App\Services\OfficialTranscriptService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Controller สำหรับนักศึกษาตรวจสอบสถานะการสำเร็จการศึกษาและดาวน์โหลด Official Transcript
 */
class StudentGraduationController extends Controller
{
    public function __construct(
        private readonly GraduationAuditService $auditService,
        private readonly OfficialTranscriptService $transcriptService
    ) {}

    /**
     * หน้าตรวจสอบสถานะความพร้อมจบการศึกษาของนักศึกษา
     */
    public function status(Request $request): View
    {
        $student = $request->user();
        $audit = $this->auditService->auditStudent($student);

        return view('student.graduation-status', [
            'student' => $student,
            'audit'   => $audit,
        ]);
    }

    /**
     * ดาวน์โหลด Official Activity Transcript PDF
     */
    public function downloadTranscript(Request $request): Response
    {
        $student = $request->user();
        $pdf = $this->transcriptService->generateOfficialTranscriptPdf($student);

        $filename = 'official_transcript_' . ($student->student_id ?: $student->id) . '_' . now()->format('Ymd') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
