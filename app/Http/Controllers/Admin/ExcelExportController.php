<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\StudentsExport;
use App\Exports\ActivitiesExport;
use App\Exports\StatisticsExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * คอนโทรลเลอร์สำหรับส่งออกข้อมูล Excel
 */
class ExcelExportController extends Controller
{
    /** แสดงหน้ารายการส่งออก */
    public function index()
    {
        return view('admin.exports.index');
    }

    /** ส่งออกรายชื่อนักศึกษา */
    public function exportStudents(Request $request)
    {
        $filters = [
            'faculty' => $request->get('faculty'),
            'year' => $request->get('year'),
            'program' => $request->get('program'),
            'status' => $request->get('status'),
        ];

        $fileName = 'students_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new StudentsExport($filters), $fileName);
    }

    /** ส่งออกรายการกิจกรรม */
    public function exportActivities(Request $request)
    {
        $filters = [
            'category' => $request->get('category'),
            'status' => $request->get('status'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $fileName = 'activities_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new ActivitiesExport($filters), $fileName);
    }

    /** ส่งออกสถิติระบบ */
    public function exportStatistics(Request $request)
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $fileName = 'statistics_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new StatisticsExport($dateFrom, $dateTo), $fileName);
    }

    /** ส่งออกรายงานการเข้าร่วมกิจกรรมของนักศึกษา */
    public function exportStudentAttendances(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:users,student_id',
        ]);

        $studentId = $request->get('student_id');
        $fileName = "attendances_{$studentId}_" . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new \App\Exports\StudentAttendancesExport($studentId), $fileName);
    }

    /** ส่งออกรายงานรายละเอียดกิจกรรม */
    public function exportActivityDetails(Request $request)
    {
        $request->validate([
            'activity_id' => 'required|exists:activities,id',
        ]);

        $activityId = $request->get('activity_id');
        $fileName = "activity_{$activityId}_details_" . date('Y-m-d_H-i-s') . '.xlsx';
        
        return Excel::download(new \App\Exports\ActivityDetailsExport($activityId), $fileName);
    }
}
