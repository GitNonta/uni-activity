<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * ส่งออกรายชื่อผู้สมัครงาน เป็น Excel
 */
class JobApplicantExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $applications;
    protected $job;
    protected int $row = 0;

    public function __construct($applications, $job)
    {
        $this->applications = $applications;
        $this->job = $job;
    }

    public function collection()
    {
        return $this->applications;
    }

    public function headings(): array
    {
        return ['ลำดับ', 'รหัสนักศึกษา', 'ชื่อ-สกุล', 'คณะ', 'สาขา', 'โทรศัพท์', 'สถานะ', 'วันที่สมัคร'];
    }

    public function map($app): array
    {
        $this->row++;
        return [
            $this->row,
            $app->user->student_id ?? '-',
            $app->user->full_name ?? '-',
            $app->user->faculty ?? '-',
            $app->user->department ?? '-',
            $app->user->phone ?? '-',
            match ($app->status) {
                'pending' => 'รอการพิจารณา',
                'confirmed' => 'ยืนยันแล้ว',
                'rejected' => 'ไม่ผ่าน',
                default => $app->status,
            },
            $app->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
