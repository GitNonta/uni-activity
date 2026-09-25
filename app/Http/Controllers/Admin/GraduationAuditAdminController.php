<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GraduationAuditService;
use App\Services\OfficialTranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller สำหรับ Graduation Audit Report และการตรวจสอบการสำเร็จการศึกษา
 */
class GraduationAuditAdminController extends Controller
{
    public function __construct(
        private readonly GraduationAuditService $auditService,
        private readonly OfficialTranscriptService $transcriptService
    ) {}

    /**
     * หน้า Graduation Audit Report แสดงสถานะความพร้อมจบของนักศึกษา
     */
    public function index(Request $request): View
    {
        $filters = [
            'year'       => $request->input('year', '4'), // เริ่มต้น: นักศึกษาชั้นปี 4
            'faculty'    => $request->input('faculty'),
            'department' => $request->input('department'),
            'status'     => $request->input('status'), // 'passed' | 'deficit'
            'search'     => $request->input('search'),
        ];

        $report = $this->auditService->getGraduationMetrics($filters);

        return view('admin.graduation.audit', [
            'report' => $report,
        ]);
    }

    /**
     * ดึงข้อมูลผลการตรวจสอบของนักศึกษารายคน (JSON สำหรับ Modal)
     */
    public function showStudentAudit(User $user): JsonResponse
    {
        $audit = $this->auditService->auditStudent($user);

        return response()->json([
            'success' => true,
            'data'    => [
                'student_id'           => $user->student_id,
                'full_name'            => $user->full_name,
                'faculty'              => $user->faculty,
                'department'           => $user->department,
                'year'                 => $user->year,
                'is_eligible'          => $audit['is_eligible'],
                'total_hours'          => $audit['total_hours'],
                'min_total_hours'      => $audit['min_total_hours'],
                'scope_audit'          => $audit['scope_audit'],
                'category_audit'       => $audit['category_audit'],
                'mandatory_audit'      => $audit['mandatory_audit'],
                'missing_requirements' => $audit['missing_requirements'],
                'attendance_count'     => $audit['attendance_count'],
            ],
        ]);
    }

    /**
     * พิมพ์ / ดาวน์โหลด Official Activity Transcript PDF
     */
    public function downloadTranscript(User $user): Response
    {
        $pdf = $this->transcriptService->generateOfficialTranscriptPdf($user);

        $filename = 'official_transcript_' . ($user->student_id ?: $user->id) . '_' . now()->format('Ymd') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * ส่งแจ้งเตือนเร่งรัดชั่วโมงกิจกรรม (Deficit Alert) ไปยังนักศึกษา
     */
    public function sendDeficitAlert(Request $request, User $user): RedirectResponse
    {
        $audit = $this->auditService->auditStudent($user);

        if ($audit['is_eligible']) {
            return redirect()
                ->back()
                ->with('error', "นักศึกษารหัส {$user->student_id} มีชั่วโมงกิจกรรมครบถ้วนตามเกณฑ์แล้ว ไม่จำเป็นต้องส่งแจ้งเตือนขาดชั่วโมง");
        }

        $result = $this->auditService->sendDeficitAlert($user, $audit, $request->user());

        $lineMsg = $result['line_sent'] ? " และ LINE" : "";

        return redirect()
            ->back()
            ->with('success', "ส่งแจ้งเตือนเร่งรัดชั่วโมงกิจกรรมไปยังนักศึกษา {$user->full_name} ({$user->student_id}) ผ่าน In-App{$lineMsg} เรียบร้อยแล้ว");
    }

    /**
     * ส่งออกรายงาน Graduation Audit Report ในรูปแบบ CSV สำหรับฝ่ายทะเบียน
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'year'       => $request->input('year', '4'),
            'faculty'    => $request->input('faculty'),
            'department' => $request->input('department'),
            'status'     => $request->input('status'),
            'search'     => $request->input('search'),
        ];

        $report = $this->auditService->getGraduationMetrics($filters);
        $filename = 'graduation_audit_report_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM สำหรับเปิดใน Excel ภาษาไทยได้ถูกต้อง
            fputs($handle, "\xEF\xBB\xBF");

            // หัวตาราง
            fputcsv($handle, [
                'รหัสนักศึกษา',
                'ชื่อ-นามสกุล',
                'คณะ',
                'สาขาวิชา',
                'ชั้นปี',
                'ชั่วโมงสะสมรวม',
                'เกณฑ์ชั่วโมงรวม',
                'สถานะการสำเร็จการศึกษา',
                'ชั่วโมงกิจกรรมมหาวิทยาลัย',
                'ชั่วโมงกิจกรรมคณะ',
                'ชั่วโมงจิตอาสา',
                'รายการที่ยังขาด',
            ]);

            foreach ($report['audited_students'] as $audit) {
                $student = $audit['student'];
                $uniEarned = $audit['scope_audit']['university']['earned'] ?? 0;
                $facEarned = $audit['scope_audit']['faculty']['earned'] ?? 0;
                $volEarned = 0;
                foreach ($audit['category_audit'] as $cat) {
                    if (str_contains($cat['name'], 'จิตอาสา')) {
                        $volEarned = $cat['earned'];
                    }
                }

                fputcsv($handle, [
                    $student->student_id,
                    $student->full_name,
                    $student->faculty ?? '-',
                    $student->department ?? '-',
                    $student->year ?? '-',
                    number_format((float) $audit['total_hours'], 1),
                    number_format((float) $audit['min_total_hours'], 1),
                    $audit['is_eligible'] ? 'ผ่านเกณฑ์' : 'ยังไม่ผ่านเกณฑ์',
                    number_format((float) $uniEarned, 1),
                    number_format((float) $facEarned, 1),
                    number_format((float) $volEarned, 1),
                    implode(' | ', $audit['missing_requirements']),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
