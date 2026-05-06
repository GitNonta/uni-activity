<?php

namespace App\Exports;

use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export รายการกิจกรรมทั้งหมด
 */
class ActivitiesExport implements FromView, WithColumnWidths, WithStyles
{
    /** ฟิลเตอร์ข้อมูล */
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    /** ดึงข้อมูลจาก View */
    public function view(): View
    {
        $query = Activity::with('category', 'registrations', 'attendances');

        // กรองตามหมวดหมู่
        if (!empty($this->filters['category'])) {
            $query->where('category_id', $this->filters['category']);
        }

        // กรองตามสถานะ
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // กรองตามช่วงวันที่
        if (!empty($this->filters['date_from'])) {
            $query->where('activity_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->where('activity_date', '<=', $this->filters['date_to']);
        }

        $activities = $query->orderBy('activity_date', 'desc')->get();

        return view('exports.activities', [
            'activities' => $activities,
            'filters' => $this->filters
        ]);
    }

    /** กำหนดความกว้างคอลัมน์ */
    public function columnWidths(): array
    {
        return [
            'A' => 20, // รหัสกิจกรรม
            'B' => 30, // ชื่อกิจกรรม
            'C' => 15, // หมวดหมู่
            'D' => 12, // วันที่
            'E' => 10, // ชั่วโมง
            'F' => 8,  // จำนวน
            'G' => 8,  // เต็ม
            'H' => 8,  // เหลือ
            'I' => 10, // สถานะ
            'J' => 15, // คะแนนเฉลี่ย
            'K' => 10, // Feedback
            'L' => 20, // สร้างเมื่อ
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
            'A1:L1000' => [
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
