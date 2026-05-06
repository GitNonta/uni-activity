<?php

namespace App\Exports;

use App\Models\User;
use App\Models\Activity;
use App\Models\ActivityFeedback;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export สถิติระบบทั้งหมด
 */
class StatisticsExport implements FromView, WithColumnWidths, WithStyles
{
    /** ช่วงวันที่ */
    protected $dateFrom;
    protected $dateTo;

    public function __construct($dateFrom = null, $dateTo = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    /** ดึงข้อมูลจาก View */
    public function view(): View
    {
        // สถิตินักศึกษา
        $totalStudents = User::where('role', 'student')->count();
        $activeStudents = User::where('role', 'student')->where('is_active', true)->count();
        
        // สถิติกิจกรรม
        $activitiesQuery = Activity::query();
        if ($this->dateFrom) {
            $activitiesQuery->where('activity_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $activitiesQuery->where('activity_date', '<=', $this->dateTo);
        }
        
        $totalActivities = $activitiesQuery->count();
        $completedActivities = $activitiesQuery->where('status', 'completed')->count();
        $totalHours = $activitiesQuery->sum('activity_hours');
        
        // สถิติการลงทะเบียนและเข้าร่วม
        $totalRegistrations = 0;
        $totalAttendances = 0;
        $activities = $activitiesQuery->get();
        
        foreach ($activities as $activity) {
            $totalRegistrations += $activity->registrations->count();
            $totalAttendances += $activity->attendances->count();
        }
        
        // สถิติ Feedback
        $feedbackQuery = ActivityFeedback::query();
        if ($this->dateFrom) {
            $feedbackQuery->whereHas('activity', function($q) {
                $q->where('activity_date', '>=', $this->dateFrom);
            });
        }
        if ($this->dateTo) {
            $feedbackQuery->whereHas('activity', function($q) {
                $q->where('activity_date', '<=', $this->dateTo);
            });
        }
        
        $totalFeedbacks = $feedbackQuery->count();
        $averageRating = $feedbackQuery->avg('rating');
        
        // สถิติตามคณะ
        $facultyStats = User::where('role', 'student')
            ->where('is_active', true)
            ->selectRaw('faculty, COUNT(*) as count')
            ->groupBy('faculty')
            ->orderBy('count', 'desc')
            ->get();
            
        // สถิติตามปี
        $yearStats = User::where('role', 'student')
            ->where('is_active', true)
            ->selectRaw('year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year')
            ->get();

        return view('exports.statistics', [
            'totalStudents' => $totalStudents,
            'activeStudents' => $activeStudents,
            'totalActivities' => $totalActivities,
            'completedActivities' => $completedActivities,
            'totalHours' => $totalHours,
            'totalRegistrations' => $totalRegistrations,
            'totalAttendances' => $totalAttendances,
            'totalFeedbacks' => $totalFeedbacks,
            'averageRating' => $averageRating,
            'facultyStats' => $facultyStats,
            'yearStats' => $yearStats,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    /** กำหนดความกว้างคอลัมน์ */
    public function columnWidths(): array
    {
        return [
            'A' => 25, // รายการ
            'B' => 15, // จำนวน
            'C' => 15, // ร้อยละ
        ];
    }

    /** จัดรูปแบบสไตล์ */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FFF3E0']
                ],
                'alignment' => ['horizontal' => 'center']
            ],
            'A2:C10' => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3E5F5']
                ]
            ],
            'A1:C1000' => [
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
