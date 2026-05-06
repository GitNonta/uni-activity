<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export ประวัติการเข้าร่วมกิจกรรมของนักศึกษาคนเดียว
 */
class StudentAttendancesExport implements FromView, WithColumnWidths, WithStyles
{
    protected $studentId;

    public function __construct($studentId)
    {
        $this->studentId = $studentId;
    }

    /** ดึงข้อมูลจาก View */
    public function view(): View
    {
        $student = User::where('student_id', $this->studentId)->firstOrFail();
        
        $attendances = Attendance::with('activity.category')
            ->where('user_id', $student->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('exports.student-attendances', [
            'student' => $student,
            'attendances' => $attendances
        ]);
    }

    /** กำหนดความกว้างคอลัมน์ */
    public function columnWidths(): array
    {
        return [
            'A' => 15, // รหัสกิจกรรม
            'B' => 30, // ชื่อกิจกรรม
            'C' => 15, // หมวดหมู่
            'D' => 12, // วันที่
            'E' => 10, // ชั่วโมง
            'F' => 12, // เวลาเช็คอิน
            'G' => 10, // สถานะ
            'H' => 20, // หมายเหตุ
            'I' => 15, // สร้างเมื่อ
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
            'A1:I1000' => [
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
