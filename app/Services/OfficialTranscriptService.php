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

        // ฟังก์ชันแปลงภาพเป็น Data URI (Base64) เพื่อตัดปัญหา file protocol / chroot ใน Dompdf
        $toBase64 = function (?string $path): ?string {
            if (!$path || !file_exists($path)) {
                return null;
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'svg'         => 'image/svg+xml',
                'webp'        => 'image/webp',
                default       => 'image/png',
            };
            $content = file_get_contents($path);
            return $content !== false ? 'data:' . $mime . ';base64,' . base64_encode($content) : null;
        };

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

        $now = now();
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];
        $issueDateThai = $now->format('j') . ' ' . ($thaiMonths[(int) $now->format('n')] ?? '') . ' ' . ((int) $now->format('Y') + 543);

        return [
            'student'            => $student,
            'audit'              => $auditResult,
            'docNumber'          => $docNumber,
            'verifyUrl'          => $verifyUrl,
            'qrCodeBase64'       => $qrCodeSvg,
            'emblemBase64'       => $toBase64($emblemPath),
            'watermarkBase64'    => null, // ปิด watermark ซ้อน เพื่อให้ตัวอักษรคมชัดไม่ถูกทับ
            'sig1Base64'         => $toBase64(file_exists($sig1Path) ? $sig1Path : null),
            'sig2Base64'         => $toBase64(file_exists($sig2Path) ? $sig2Path : null),
            'studentPhotoBase64' => $toBase64($studentPhoto),
            'issueDate'          => $now,
            'issueDateThai'      => $issueDateThai,
            'attendances'        => $auditResult['attendances'],
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

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'fontDir'                 => storage_path('fonts'),
            'fontCache'               => storage_path('fonts'),
            'defaultFont'             => 'sarabun',
            'isFontSubsettingEnabled' => true,
            'isHtml5ParserEnabled'    => true,
            'isRemoteEnabled'         => true,
            'dpi'                     => 96,
        ]);

        return $pdf;
    }
}
