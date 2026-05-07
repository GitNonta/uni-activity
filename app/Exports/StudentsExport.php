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
    protected array $fields;

    private const DEFAULT_FIELDS = [
        'student_id',
        'full_name',
        'faculty',
        'department',
        'year',
        'program',
        'email',
        'is_active',
        'total_hours',
        'approved_count',
        'created_at',
    ];

    private const LABELS = [
        'student_id' => 'รหัสนักศึกษา',
        'full_name' => 'ชื่อ-นามสกุล',
        'faculty' => 'คณะ',
        'department' => 'สาขาวิชา',
        'year' => 'ชั้นปี',
        'program' => 'ภาคเรียน',
        'email' => 'อีเมล',
        'is_active' => 'สถานะ',
        'total_hours' => 'ชั่วโมงทั้งหมด',
        'approved_count' => 'กิจกรรมที่เข้าร่วม',
        'created_at' => 'สร้างเมื่อ',
    ];

    public function __construct($filters = [], array $fields = [])
    {
        $this->filters = $filters;
        $this->fields = array_values(array_intersect($fields ?: self::DEFAULT_FIELDS, self::DEFAULT_FIELDS));
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
            'filters' => $this->filters,
            'fields' => $this->fields,
            'labels' => self::LABELS,
        ]);
    }

    /** กำหนดความกว้างคอลัมน์ */
    public function columnWidths(): array
    {
        $widths = [];
        foreach (array_values($this->fields) as $index => $field) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $widths[$column] = match ($field) {
                'full_name' => 25,
                'faculty', 'department', 'email' => 22,
                default => 15,
            };
        }

        return $widths;
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
