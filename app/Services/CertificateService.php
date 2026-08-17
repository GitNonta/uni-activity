<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    /**
     * ออกใบรับรองชั่วโมงกิจกรรมตามหมวดหมู่ (Idempotent: ไม่ออกซ้ำถ้ามีอยู่แล้วในปีการศึกษานั้น)
     */
    public function issueCategoryCertificate(User $user, ActivityCategory $category, ?string $academicYear = null): ?Certificate
    {
        $academicYear = $academicYear ?? (string) date('Y');

        return DB::transaction(function () use ($user, $category, $academicYear): ?Certificate {
            $existing = Certificate::where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->where('academic_year', $academicYear)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $totalHours = (float) Attendance::where('user_id', $user->id)
                ->where('status', 'approved')
                ->whereHas('activity', fn ($query) => $query->where('category_id', $category->id))
                ->with('activity:id,activity_hours')
                ->get()
                ->sum(fn ($attendance): float => (float) ($attendance->activity->activity_hours ?? 0));

            if ((float) ($category->required_hours ?? 0) > $totalHours) {
                return null;
            }

            $verificationToken = Str::random(40);

            return Certificate::create([
                'certificate_code'   => 'PKRU-CERT-' . date('Y') . '-' . strtoupper(Str::random(8)),
                'user_id'            => $user->id,
                'category_id'        => $category->id,
                'title'              => "ใบรับรองการเข้าร่วมกิจกรรม ด้าน{$category->name}",
                'hours_completed'    => $totalHours,
                'academic_year'      => $academicYear,
                'issued_at'          => now(),
                'verification_token' => $verificationToken,
                'metadata'           => [
                    'student_name'  => $user->full_name,
                    'student_id'    => $user->student_id,
                    'faculty'       => $user->faculty,
                    'department'    => $user->department,
                    'category_name' => $category->name,
                ],
            ]);
        });
    }

    /**
     * ออกใบรับรองกิจกรรมรวมของมหาวิทยาลัย
     */
    public function issueGeneralCertificate(User $user, ?string $academicYear = null): ?Certificate
    {
        $academicYear = $academicYear ?? (string) date('Y');

        $existing = Certificate::where('user_id', $user->id)
            ->whereNull('category_id')
            ->where('academic_year', $academicYear)
            ->first();

        if ($existing) {
            return $existing;
        }

        $totalHours = (float) Attendance::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with('activity:id,activity_hours')
            ->get()
            ->sum(fn($a) => (float) ($a->activity->activity_hours ?? 0));

        if ($totalHours <= 0) {
            return null;
        }

        $code = 'PKRU-CERT-' . date('Y') . '-' . strtoupper(Str::random(8));
        $verificationToken = Str::random(40);

        return Certificate::create([
            'certificate_code'   => $code,
            'user_id'            => $user->id,
            'category_id'        => null,
            'title'              => "ใบรับรองการเข้าร่วมกิจกรรมพัฒนานักศึกษาครบตามเกณฑ์มหาวิทยาลัย",
            'hours_completed'    => $totalHours,
            'academic_year'      => $academicYear,
            'issued_at'          => now(),
            'verification_token' => $verificationToken,
            'metadata'           => [
                'student_name' => $user->full_name,
                'student_id'   => $user->student_id,
                'faculty'      => $user->faculty,
                'department'   => $user->department,
            ],
        ]);
    }

    /**
     * เรนเดอร์ไฟล์ PDF เกียรติบัตร A4 แนวนอน
     */
    public function renderPdf(Certificate $certificate): Response
    {
        $certificate->loadMissing(['user', 'category']);

        $verifyUrl = route('certificates.verify', $certificate->certificate_code);
        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'user'        => $certificate->user,
            'verifyUrl'   => $verifyUrl,
            'qrCodeSvg'   => QrCode::format('svg')->size(90)->margin(0)->generate($verifyUrl),
        ])
        ->setPaper('a4', 'landscape')
        ->setOptions([
            'defaultFont'          => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
        ]);

        $filename = "certificate_{$certificate->certificate_code}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
