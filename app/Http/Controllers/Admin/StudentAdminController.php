<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\User;
use App\Services\ActivitySummaryService;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์จัดการโปรไฟล์และชั่วโมงกิจกรรมนักศึกษา (ฝั่ง Admin)
 */
class StudentAdminController extends Controller
{
    use LogsAdminActivity;
    /** แสดงรายชื่อนักศึกษาทั้งหมด พร้อมสรุปชั่วโมง */
    public function index(Request $request)
    {
        $query = User::where('role', 'student')
            ->withCount(['attendances as approved_count' => fn($q) => $q->where('status', 'approved')])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('full_name', 'like', "%{$request->search}%")
                        ->orWhere('student_id', 'like', "%{$request->search}%")
                        ->orWhere('faculty', 'like', "%{$request->search}%")
                        ->orWhere('department', 'like', "%{$request->search}%");
                });
            })
            ->when($request->faculty, fn($q) => $q->where('faculty', $request->faculty))
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->when($request->program, fn($q) => $q->where('program', $request->program));

        $students = $query->orderBy('full_name')->paginate(20)->withQueryString();

        // คำนวณชั่วโมงรวมสำหรับแต่ละนักศึกษา
        $hoursMap = Attendance::where('status', 'approved')
            ->whereIn('user_id', $students->pluck('id'))
            ->with('activity:id,activity_hours')
            ->get()
            ->groupBy('user_id')
            ->map(fn($group) => $group->sum(fn($a) => (float) ($a->activity->activity_hours ?? 0)));

        $faculties = User::where('role', 'student')->whereNotNull('faculty')->distinct()->pluck('faculty')->sort()->values();
        $years     = User::where('role', 'student')->whereNotNull('year')->distinct()->pluck('year')->sort()->values();
        $programs  = User::where('role', 'student')->whereNotNull('program')->distinct()->pluck('program')->sort()->values();

        return view('admin.students.index', compact('students', 'hoursMap', 'faculties', 'years', 'programs'));
    }

    /** แสดงโปรไฟล์นักศึกษา + จัดการชั่วโมงกิจกรรม */
    public function show(ActivitySummaryService $summaryService, int $id)
    {
        $student = User::where('role', 'student')->findOrFail($id);

        $summary = $summaryService->getSummary($student);

        $attendances = Attendance::with('activity.category', 'verifier')
            ->where('user_id', $student->id)
            ->orderByDesc('checked_in_at')
            ->get();

        $activities = Activity::orderByDesc('activity_date')->get(['id', 'title', 'activity_hours', 'activity_date']);

        return view('admin.students.show', [
            'student'       => $student,
            'totalHours'    => $summary['totalHours'],
            'totalRequired' => $summary['totalRequired'],
            'byCategory'    => $summary['byCategory'],
            'attendances'   => $attendances,
            'activities'    => $activities,
        ]);
    }

    /** Admin เพิ่มการเข้าร่วมกิจกรรมให้นักศึกษา (manual) */
    public function addAttendance(Request $request, int $id)
    {
        $student = User::where('role', 'student')->findOrFail($id);

        $request->validate([
            'activity_id' => 'required|exists:activities,id',
            'status'      => 'required|in:approved,pending',
            'checked_in_at' => 'required|date',
        ]);

        $exists = Attendance::where('user_id', $student->id)
            ->where('activity_id', $request->activity_id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'นักศึกษาคนนี้มีบันทึกกิจกรรมนี้อยู่แล้ว');
        }

        $att = Attendance::create([
            'user_id'       => $student->id,
            'activity_id'   => $request->activity_id,
            'status'        => $request->status,
            'method'        => 'manual',
            'checked_in_at' => $request->checked_in_at,
            'verified_by'   => auth()->id(),
            'is_verified'   => $request->status === 'approved',
        ]);
        $this->auditCreate($att, "เพิ่มบันทึกกิจกรรมให้ \"{$student->full_name}\"");

        return back()->with('success', 'เพิ่มบันทึกกิจกรรมเรียบร้อยแล้ว');
    }

    /** Admin แก้ไขสถานะ/เวลาของการเข้าร่วมกิจกรรม */
    public function updateAttendance(Request $request, int $id, int $aid)
    {
        $student    = User::where('role', 'student')->findOrFail($id);
        $attendance = Attendance::where('user_id', $student->id)->findOrFail($aid);

        $request->validate([
            'status'        => 'required|in:approved,pending,rejected',
            'checked_in_at' => 'required|date',
        ]);

        $oldValues = $attendance->only(['status', 'checked_in_at']);
        $attendance->update([
            'status'        => $request->status,
            'checked_in_at' => $request->checked_in_at,
            'verified_by'   => auth()->id(),
            'is_verified'   => $request->status === 'approved',
        ]);
        $this->auditUpdate($attendance, $oldValues, "แก้ไขบันทึกกิจกรรมของ \"{$student->full_name}\"");

        return back()->with('success', 'อัปเดตบันทึกกิจกรรมเรียบร้อยแล้ว');
    }

    /** Admin ลบบันทึกการเข้าร่วมกิจกรรม */
    public function deleteAttendance(int $id, int $aid)
    {
        $student    = User::where('role', 'student')->findOrFail($id);
        $attendance = Attendance::where('user_id', $student->id)->findOrFail($aid);
        $this->auditDelete($attendance, "ลบบันทึกกิจกรรมของ \"{$student->full_name}\"");
        $attendance->delete();

        return back()->with('success', 'ลบบันทึกกิจกรรมเรียบร้อยแล้ว');
    }
}
