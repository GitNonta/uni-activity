<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export รายชื่อนักศึกษาทั้งหมด
 */
class StudentsExport implements FromView, WithColumnWidths, WithStyles
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
        $query = User::where('role', 'student');

        // กรองตามคณะ
        if (!empty($this->filters['faculty'])) {
            $query->where('faculty', $this->filters['faculty']);
        }

        // กรองตามปี
        if (!empty($this->filters['year'])) {
            $query->where('year', $this->filters['year']);
        }

        // กรองตามภาคเรียน
        if (!empty($this->filters['program'])) {
            $query->where('program', $this->filters['program']);
        }

        // กรองตามสถานะ
        if (!empty($this->filters['status'])) {
            $query->where('is_active', $this->filters['status'] === 'active');
        }

        $students = $query->orderBy('student_id')->get();

        return view('exports.students', [
            'students' => $students,
            'filters' => $this->filters
        ]);
    }

    /** กำหนดความกว้างคอลัมน์ */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // รหัสนักศึกษา
            'B' => 25, // ชื่อ-นามสกุล
            'C' => 20, // คณะ
            'D' => 20, // สาขา
            'E' => 10, // ชั้นปี
            'F' => 15, // ภาคเรียน
            'G' => 15, // อีเมล
            'H' => 10, // สถานะ
            'I' => 15, // ชั่วโมงทั้งหมด
            'J' => 15, // กิจกรรมที่เข้าร่วม
            'K' => 15, // สร้างเมื่อ
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
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'alignment' => ['horizontal' => 'center']
            ],
            'A1:J1000' => [
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
