<?php

namespace App\Exports;

use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export รายละเอียดกิจกรรมและผู้เข้าร่วม
 */
class ActivityDetailsExport implements FromView, WithColumnWidths, WithStyles
{
    protected $activityId;

    public function __construct($activityId)
    {
        $this->activityId = $activityId;
    }

    /** ดึงข้อมูลจาก View */
    public function view(): View
    {
        $activity = Activity::with([
            'category',
            'registrations.user',
            'attendances.user',
            'feedbacks.user'
        ])->findOrFail($this->activityId);

        return view('exports.activity-details', [
            'activity' => $activity
        ]);
    }

    /** กำหนดความกว้างคอลัมน์ */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // รหัสนักศึกษา
            'B' => 25, // ชื่อ-นามสกุล
            'C' => 15, // คณะ
            'D' => 10, // ชั้นปี
            'E' => 12, // วันที่ลงทะเบียน
            'F' => 12, // วันที่เข้าร่วม
            'G' => 10, // ชั่วโมง
            'H' => 10, // สถานะ
            'I' => 12, // เวลาเช็คอิน
            'J' => 15, // คะแนนประเมิน
            'K' => 20, // ความคิดเห็น
        ];
    }

    /** จัดรูปแบบสไตล์ */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E8F5E8']
                ],
                'alignment' => ['horizontal' => 'center']
            ],
            'A1:K1000' => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'E0E0E0']
                    ]
                ]
            ]
        ];
    }
}
