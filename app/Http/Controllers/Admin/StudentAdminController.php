<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use App\Models\JobListing;
use App\Models\Room;
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
            ->when($request->department, fn($q) => $q->where('department', $request->department))
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->when($request->program, fn($q) => $q->where('program', $request->program));

        $filteredStudents = $query->orderBy('full_name')->get();

        // คำนวณชั่วโมงรวมสำหรับแต่ละนักศึกษา
        $hoursMap = Attendance::where('status', 'approved')
            ->whereIn('user_id', $filteredStudents->pluck('id'))
            ->with('activity:id,activity_hours')
            ->get()
            ->groupBy('user_id')
            ->map(fn($group) => $group->sum(fn($a) => (float) ($a->activity->activity_hours ?? 0)));

        $totalRequired = (float) (Setting::get('total_required_hours') ?? ActivityCategory::sum('required_hours'));
        if ($request->completion === 'complete') {
            $filteredStudents = $filteredStudents->filter(fn($student) => (float) ($hoursMap[$student->id] ?? 0) >= $totalRequired);
        } elseif ($request->completion === 'incomplete') {
            $filteredStudents = $filteredStudents->filter(fn($student) => (float) ($hoursMap[$student->id] ?? 0) < $totalRequired);
        }

        $page = (int) $request->get('page', 1);
        $students = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredStudents->forPage($page, 20)->values(),
            $filteredStudents->count(),
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $faculties = User::where('role', 'student')->whereNotNull('faculty')->distinct()->pluck('faculty')->sort()->values();
        $departments = User::where('role', 'student')->whereNotNull('department')->distinct()->pluck('department')->sort()->values();
        $years     = User::where('role', 'student')->whereNotNull('year')->distinct()->pluck('year')->sort()->values();
        $programs  = User::where('role', 'student')->whereNotNull('program')->distinct()->pluck('program')->sort()->values();

        return view('admin.students.index', compact('students', 'hoursMap', 'faculties', 'departments', 'years', 'programs', 'totalRequired'));
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

        $activitiesQuery = Activity::orderByDesc('activity_date');
        if (auth()->user()->isStaff()) {
            $activitiesQuery->where('created_by', auth()->id());
        }
        $activities = $activitiesQuery->get(['id', 'title', 'activity_hours', 'activity_date']);

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

        $activity = Activity::findOrFail($request->activity_id);
        if (auth()->user()->isStaff() && $activity->created_by !== auth()->id()) {
            abort(403, 'คุณไม่มีสิทธิ์จัดบันทึกในกิจกรรมนี้');
        }

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
        $attendance = Attendance::with('activity')->where('user_id', $student->id)->findOrFail($aid);

        if (auth()->user()->isStaff() && (!$attendance->activity || $attendance->activity->created_by !== auth()->id())) {
            abort(403, 'คุณไม่มีสิทธิ์แก้ไขบันทึกกิจกรรมนี้');
        }

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
        $attendance = Attendance::with('activity')->where('user_id', $student->id)->findOrFail($aid);

        if (auth()->user()->isStaff() && (!$attendance->activity || $attendance->activity->created_by !== auth()->id())) {
            abort(403, 'คุณไม่มีสิทธิ์ลบบันทึกกิจกรรมนี้');
        }

        $this->auditDelete($attendance, "ลบบันทึกกิจกรรมของ \"{$student->full_name}\"");
        $attendance->delete();

        return back()->with('success', 'ลบบันทึกกิจกรรมเรียบร้อยแล้ว');
    }

    /** ส่งข้อความแรกเริ่มแชทกับนักศึกษา */
    public function sendMessage(Request $request, \App\Repositories\ChatRepository $chatRepository, int $id)
    {
        $student = User::where('role', 'student')->findOrFail($id);

        $request->validate([
            'job_id'  => 'required|integer',
            'message' => 'required|string|max:2000',
        ]);

        $jobId = (int) $request->job_id;

        // Security check for staff
        if (auth()->user()->isStaff()) {
            if ($jobId !== 0) {
                $job = JobListing::findOrFail($jobId);
                if ($job->created_by !== auth()->id()) {
                    abort(403, 'คุณไม่มีสิทธิ์แชทสำหรับงานนี้');
                }
            } else {
                $job = null;
            }
        } else {
            $job = $jobId == 0 ? null : JobListing::findOrFail($jobId);
        }

        // 1. Get or create room
        $roomQuery = Room::whereHas('users', function ($q) use ($student) {
            $q->where('users.id', $student->id);
        });
        if ($jobId == 0) {
            $roomQuery->whereNull('job_id');
        } else {
            $roomQuery->where('job_id', $jobId);
        }
        $room = $roomQuery->first();

        if (!$room) {
            if ($jobId == 0) {
                $adminIds = User::where('role', 'admin')->pluck('id')->toArray();
                $room = $chatRepository->createRoom(
                    array_merge([$student->id, auth()->id()], $adminIds),
                    'direct',
                    'ติดต่อสอบถามเจ้าหน้าที่',
                    null
                );
            } else {
                $room = $chatRepository->createRoom(
                    [$student->id, auth()->id()],
                    'direct',
                    $job->title,
                    $jobId
                );
            }
        }

        // 2. Send the message
        $chatRepository->sendMessage($room, auth()->user(), $request->message);

        // 3. Redirect to the inbox thread
        return redirect()->route('admin.inbox.show', [$jobId, $student->id])
            ->with('success', 'ส่งข้อความถึงนักศึกษาเรียบร้อยแล้ว');
    }
}
