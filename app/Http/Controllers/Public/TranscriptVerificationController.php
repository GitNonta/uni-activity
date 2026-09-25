<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GraduationAuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public Controller สำหรับการสแกน QR Code ตรวจสอบความถูกต้องของ Official Activity Transcript
 */
class TranscriptVerificationController extends Controller
{
    public function __construct(
        private readonly GraduationAuditService $auditService
    ) {}

    public function verify(Request $request, string $code): View
    {
        // รูปแบบ Code: TR-MDTU-{student_id}-{date}-{random}
        $parts = explode('-', $code);
        $studentId = $parts[2] ?? null;

        $student = null;
        $audit = null;

        if ($studentId) {
            $student = User::where('student_id', $studentId)
                ->orWhere('id', is_numeric($studentId) ? (int) $studentId : -1)
                ->first();

            if ($student) {
                $audit = $this->auditService->auditStudent($student);
            }
        }

        $isValid = $student !== null;

        return view('transcripts.verify', [
            'code'     => $code,
            'isValid'  => $isValid,
            'student'  => $student,
            'audit'    => $audit,
        ]);
    }
}
