<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Service สำหรับการออกเอกสารใบรับรองชั่วโมงกิจกรรมรวมตลอดหลักสูตร (Official Activity Transcript)
 */
class OfficialTranscriptService
{
    public function __construct(
        private readonly GraduationAuditService $auditService
    ) {}

    /**
     * ดึงและประมวลผลข้อมูลสำหรับ Official Activity Transcript
     *
     * @return array<string, mixed>
     */
    public function getTranscriptData(User $student): array
    {
        $auditResult = $this->auditService->auditStudent($student);

        // หมายเลขอ้างอิงเอกสารทางการ (Official Document Number)
        $docNumber = 'TR-MDTU-' . ($student->student_id ?: $student->id) . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        // URL ตรวจสอบความถูกต้องเอกสาร (Verification URL)
        $verifyUrl = route('transcripts.verify', ['code' => $docNumber]);

        // QR Code สำหรับสแกนตรวจสอบ
        $qrCodeSvg = base64_encode((string) QrCode::format('svg')->size(100)->margin(0)->generate($verifyUrl));

        // ค้นหาพาธตรามหาวิทยาลัยและลายน้ำ
        $emblemPath = file_exists(public_path('images/pkru-emblem.png'))
            ? public_path('images/pkru-emblem.png')
            : (file_exists(public_path('images/--removebg-preview.png')) ? public_path('images/--removebg-preview.png') : null);

        $watermarkPath = file_exists(public_path('images/sample-watermark.png'))
            ? public_path('images/sample-watermark.png')
            : (file_exists(public_path('images/sd-removebg-preview.png')) ? public_path('images/sd-removebg-preview.png') : null);

        $sig1Path = public_path('images/signatures/signature1.png');
        $sig2Path = public_path('images/signatures/signature2.png');

        $studentPhoto = $student->profile_photo && file_exists(storage_path('app/public/' . $student->profile_photo))
            ? storage_path('app/public/' . $student->profile_photo)
            : null;

        // ฟังก์ชันตัดปัญหาการซ้อนทับของสระภาษาไทย
        $normalizeText = function (?string $text): ?string {
            if (!$text) {
                return $text;
            }
            if (class_exists(\Normalizer::class)) {
                return \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
            }
            return $text;
        };

        $student->full_name  = $normalizeText($student->full_name);
        $student->faculty    = $normalizeText($student->faculty);
        $student->department = $normalizeText($student->department);

        return [
            'student'        => $student,
            'audit'          => $auditResult,
            'docNumber'      => $docNumber,
            'verifyUrl'      => $verifyUrl,
            'qrCodeBase64'   => $qrCodeSvg,
            'emblemPath'     => $emblemPath,
            'watermarkPath'  => $watermarkPath,
            'sig1Path'       => file_exists($sig1Path) ? $sig1Path : null,
            'sig2Path'       => file_exists($sig2Path) ? $sig2Path : null,
            'studentPhoto'   => $studentPhoto,
            'issueDate'      => now(),
            'attendances'    => $auditResult['attendances'],
        ];
    }

    /**
     * เรนเดอร์ PDF เอกสาร Official Activity Transcript (A4 Portrait)
     */
    public function generateOfficialTranscriptPdf(User $student): DomPdfWrapper
    {
        $data = $this->getTranscriptData($student);

        /** @var DomPdfWrapper $pdf */
        $pdf = Pdf::loadView('pdf.official-activity-transcript', $data);

        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'dpi'                  => 150,
        ]);

        return $pdf;
    }
}
