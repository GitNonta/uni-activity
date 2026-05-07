<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Models\Activity;
use App\Models\ActivityCategory;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\User;
use App\Models\JobListing;
use App\Models\ChatMessage;
use App\Models\ActivityFeedback;
use App\Models\AdminAuditLog;
use App\Services\QrCodeService;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;

/**
 * คอนโทรลเลอร์จัดการกิจกรรม (ฝั่งผู้ดูแล/Admin)
 * จัดการ CRUD กิจกรรม, อนุมัติลงทะเบียน, เช็คอิน, สร้างกิจกรรมด่วน, สลับเปิดเช็คอินก่อนเวลา
 */
class ActivityAdminController extends Controller
{
    use LogsAdminActivity;
    /** แสดงหน้า Dashboard: สถิติรวม + กิจกรรมล่าสุด + หมวดหมู่สำหรับ modal สร้างด่วน */
    public function dashboard()
    {
        $stats = [
            'totalActivities' => Activity::count(),
            'upcomingActivities' => Activity::whereIn('status', ['upcoming', 'open'])->count(),
            'totalStudents' => User::where('role', 'student')->count(),
            'totalRegistrations' => Registration::whereIn('status', ['pending', 'approved'])->count(),
            'pendingRegistrations' => Registration::where('status', 'pending')->count(),
            'pendingAttendances' => Attendance::where('status', 'pending')->count(),
            'upcomingThisWeek' => Activity::whereBetween('activity_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->whereIn('status', ['upcoming', 'open', 'ongoing'])
                ->count(),
            'totalJobs' => JobListing::count(),
            'unreadMessages' => ChatMessage::whereNull('read_at')->where('sender_role', 'student')->count(),
            'totalFeedbacks' => ActivityFeedback::count(),
        ];

        $recentActivities = Activity::with('category')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $pendingRegistrations = Registration::with(['user', 'activity'])
            ->where('status', 'pending')
            ->latest()
            ->take(8)
            ->get();

        $pendingAttendances = Attendance::with(['user', 'activity'])
            ->where('status', 'pending')
            ->latest()
            ->take(8)
            ->get();

        $categories = ActivityCategory::all();
        
        $recentAuditLogs = AdminAuditLog::with('user')
            ->orderByDesc('created_at')
            ->take(6)
            ->get();
        
        return view('admin.dashboard', compact('stats', 'recentActivities', 'pendingRegistrations', 'pendingAttendances', 'categories', 'recentAuditLogs'));
    }

    /** แสดงรายการกิจกรรมทั้งหมด รองรับกรองตามสถานะและค้นหา */
    public function index(Request $request)
    {
        $activities = Activity::with(['category', 'creator'])
            ->withCount([
                'registrations as pending_registrations_count' => fn($q) => $q->where('status', 'pending'),
                'attendances as pending_attendances_count' => fn($q) => $q->where('status', 'pending')
            ])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.activities.index', compact('activities'));
    }

    /** ดึงรายการคำขออนุมัติ (Pending) สำหรับแสดงใน Popup */
    public function pendingRequests($id)
    {
        $activity = Activity::findOrFail($id);
        
        $pendingRegs = \App\Models\Registration::with('user')
            ->where('activity_id', $id)
            ->where('status', 'pending')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'type' => 'registration',
                'student_id' => $r->user->student_id,
                'name' => $r->user->full_name,
                'faculty' => $r->user->faculty,
                'time' => $r->created_at->format('d/m H:i'),
                'details' => 'ลงทะเบียนขอเข้าร่วม'
            ]);

        $pendingAtts = \App\Models\Attendance::with('user')
            ->where('activity_id', $id)
            ->where('status', 'pending')
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'type' => 'attendance',
                'student_id' => $a->user->student_id,
                'name' => $a->user->full_name,
                'faculty' => $a->user->faculty,
                'time' => $a->created_at->format('d/m H:i'),
                'details' => ($a->distance_meters ? "เช็คอินห่าง " . number_format($a->distance_meters, 0) . "ม." : "บันทึกกิจกรรมด้วยตนเอง")
            ]);

        return response()->json([
            'activity_title' => $activity->title,
            'items' => $pendingRegs->concat($pendingAtts)->sortByDesc('time')->values()
        ]);
    }

    /** แสดงฟอร์มสร้างกิจกรรมใหม่ */
    public function create()
    {
        $categories = ActivityCategory::all();
        $faculties = Activity::whereNotNull('faculty')->distinct()->pluck('faculty')->sort()->values();
        $departments = Activity::whereNotNull('department')->distinct()->pluck('department')->sort()->values();
        return view('admin.activities.create', compact('categories', 'faculties', 'departments'));
    }

    /** บันทึกกิจกรรมใหม่ พร้อมสร้าง QR token + อัปโหลดรูป (ถ้ามี) */
    public function store(StoreActivityRequest $request, QrCodeService $qrService)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['qr_token'] = $qrService->generateToken();
        $data['is_mandatory'] = $request->boolean('is_mandatory');
        if (($data['scope'] ?? 'university') === 'university') {
            $data['faculty'] = null;
            $data['department'] = null;
        } elseif (($data['scope'] ?? 'university') === 'faculty') {
            $data['department'] = null;
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('activities', 'public');
        }

        $activity = Activity::create($data);
        $this->auditCreate($activity, "สร้างกิจกรรม \"{$activity->title}\"");

        return redirect()->route('admin.activities.index')->with('success', 'สร้างกิจกรรมสำเร็จ!');
    }

    /** แสดงรายละเอียดกิจกรรม พร้อมข้อมูลผู้ลงทะเบียน/เช็คอิน */
    public function show($id)
    {
        $activity = Activity::with(['category', 'registrations.user', 'attendances.user'])->findOrFail($id);
        return view('admin.activities.show', compact('activity'));
    }

    /** แสดงฟอร์มแก้ไขกิจกรรม */
    public function edit($id)
    {
        $activity = Activity::findOrFail($id);
        $categories = ActivityCategory::all();
        $faculties = Activity::whereNotNull('faculty')->distinct()->pluck('faculty')->sort()->values();
        $departments = Activity::whereNotNull('department')->distinct()->pluck('department')->sort()->values();
        return view('admin.activities.edit', compact('activity', 'categories', 'faculties', 'departments'));
    }

    /** อัปเดตข้อมูลกิจกรรม ตรวจสอบ validation + อัปโหลดรูปใหม่ (ถ้ามี) */
    public function update(Request $request, $id)
    {
        $activity = Activity::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'activity_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'activity_hours' => 'required|numeric|min:0.5',
            'max_participants' => 'required|integer|min:1',
            'register_open_at' => 'required|date',
            'register_close_at' => 'required|date',
            'checkin_open_at' => 'required|date',
            'checkin_close_at' => 'required|date',
            'category_id' => 'required|exists:activity_categories,id',
            'scope' => 'required|in:university,faculty,department',
            'faculty' => 'nullable|required_if:scope,faculty,department|string|max:100',
            'department' => 'nullable|required_if:scope,department|string|max:100',
            'status' => 'nullable|in:upcoming,open,full,ongoing,done,cancelled',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'checkin_radius' => 'nullable|integer|min:10|max:5000',
        ]);

        $data['is_mandatory'] = $request->boolean('is_mandatory');
        if ($data['scope'] === 'university') {
            $data['faculty'] = null;
            $data['department'] = null;
        } elseif ($data['scope'] === 'faculty') {
            $data['department'] = null;
        }
        $data['latitude'] = $request->filled('latitude') ? $request->latitude : null;
        $data['longitude'] = $request->filled('longitude') ? $request->longitude : null;
        $data['checkin_radius'] = $request->filled('checkin_radius') ? $request->checkin_radius : 200;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('activities', 'public');
        }

        $oldValues = $activity->only(['title', 'location', 'activity_date', 'status', 'activity_hours']);
        $activity->update($data);
        $this->auditUpdate($activity, $oldValues, "แก้ไขกิจกรรม \"{$activity->title}\"");

        return redirect()->route('admin.activities.index')->with('success', 'อัปเดตกิจกรรมสำเร็จ!');
    }

    /** ลบกิจกรรม */
    public function destroy($id)
    {
        $activity = Activity::findOrFail($id);
        $this->auditDelete($activity, "ลบกิจกรรม \"{$activity->title}\"");
        $activity->delete();

        return redirect()->route('admin.activities.index')->with('success', 'ลบกิจกรรมสำเร็จ');
    }

    /** แสดงรายชื่อผู้ลงทะเบียนกิจกรรม */
    public function participants($id)
    {
        $activity = Activity::with(['registrations.user'])->findOrFail($id);

        return view('admin.activities.participants', compact('activity'));
    }

    /** แสดงหน้าจอมอนิเตอร์เช็คอิน: ดูสถานะเช็คอินแบบ realtime */
    public function checkinMonitor($id)
    {
        $activity = Activity::with(['attendances.user', 'registrations.user'])->findOrFail($id);

        return view('admin.checkin.monitor', compact('activity'));
    }

    /** อนุมัติการลงทะเบียนของนักศึกษา */
    public function approveRegistration($id)
    {
        $registration = Registration::findOrFail($id);
        $registration->update(['status' => 'approved']);
        $this->auditApprove($registration, "อนุมัติการลงทะเบียน #{$registration->id}");

        return back()->with('success', 'อนุมัติการลงทะเบียนสำเร็จ');
    }

    /** ปฏิเสธการลงทะเบียนของนักศึกษา */
    public function rejectRegistration($id)
    {
        $registration = Registration::findOrFail($id);
        $registration->update(['status' => 'rejected']);
        $this->auditReject($registration, "ปฏิเสธการลงทะเบียน #{$registration->id}");

        return back()->with('success', 'ปฏิเสธการลงทะเบียนสำเร็จ');
    }

    /**
     * เช็คอินแบบ manual โดยผู้ดูแล
     * ค้นหานักศึกษาจากรหัส → ตรวจสอบลงทะเบียน → ตรวจซ้ำ → สร้าง attendance
     */
    public function manualCheckIn(Request $request, $activityId)
    {
        $request->validate(['student_id' => 'required|string']);

        $user = User::where('student_id', $request->student_id)->first();
        if (!$user) {
            return back()->with('error', 'ไม่พบรหัสนักศึกษา');
        }

        $activity = Activity::findOrFail($activityId);

        $registration = Registration::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->where('status', 'approved')
            ->first();

        if (!$registration) {
            return back()->with('error', 'นักศึกษาไม่ได้ลงทะเบียนกิจกรรมนี้');
        }

        $exists = $activity->attendances()->where('user_id', $user->id)->exists();
        if ($exists) {
            return back()->with('error', 'นักศึกษาเช็คอินไปแล้ว');
        }

        $att = $activity->attendances()->create([
            'user_id'     => $user->id,
            'method'      => 'manual',
            'status'      => 'approved',
            'verified_by' => auth()->id(),
            'is_verified' => true,
            'ip_address'  => $request->ip(),
        ]);
        $this->auditCreate($att, "เช็คอิน manual: {$user->full_name} ในกิจกรรม \"{$activity->title}\"");

        return back()->with('success', "เช็คอิน {$user->full_name} สำเร็จ");
    }

    /**
     * สร้างกิจกรรมด่วน (จาก modal บน Dashboard)
     * รับข้อมูลหลักเท่านั้น ค่าอื่นใช้ค่าเริ่มต้นอัตโนมัติ
     */
    public function quickStore(Request $request, QrCodeService $qrService)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'location'    => 'required|string|max:255',
            'category_id' => 'required|exists:activity_categories,id',
            'activity_date'  => 'required|date',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i',
            'activity_hours' => 'required|numeric|min:0.5',
        ]);

        $date = \Carbon\Carbon::parse($data['activity_date']);

        $activity = Activity::create(array_merge($data, [
            'max_participants'  => 50,
            'register_open_at'  => now(),
            'register_close_at' => $date->copy()->subHour(),
            'checkin_open_at'   => $date->copy()->setTimeFromTimeString($data['start_time'])->subMinutes(30),
            'checkin_close_at'  => $date->copy()->setTimeFromTimeString($data['end_time'])->addMinutes(30),
            'is_mandatory'      => false,
            'status'            => 'open',
            'created_by'        => auth()->id(),
            'qr_token'          => $qrService->generateToken(),
        ]));
        $this->auditCreate($activity, "สร้างกิจกรรมด่วน \"{$activity->title}\"");

        return redirect()->route('admin.dashboard')->with('success', 'สร้างกิจกรรมด่วนสำเร็จ!');
    }

    /** อนุมัติการเข้าร่วมกิจกรรม (attendance) */
    public function approveAttendance($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->update([
            'status'      => 'approved',
            'is_verified' => true,
            'verified_by' => auth()->id(),
        ]);
        $this->auditApprove($attendance, "อนุมัติการเข้าร่วม #{$attendance->id}");

        // เปลี่ยนสถานะการลงทะเบียนเป็น 'completed' เมื่ออนุมัติการเข้าร่วมแล้ว
        $registration = Registration::where('user_id', $attendance->user_id)
            ->where('activity_id', $attendance->activity_id)
            ->first();
        
        if ($registration && $registration->status === 'approved') {
            $registration->markAsCompleted();
        }

        return back()->with('success', 'อนุมัติการเข้าร่วมสำเร็จ');
    }

    /** ปฏิเสธการเข้าร่วมกิจกรรม (attendance) */
    public function rejectAttendance($id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->update([
            'status'      => 'rejected',
            'verified_by' => auth()->id(),
        ]);
        $this->auditReject($attendance, "ปฏิเสธการเข้าร่วม #{$attendance->id}");

        return back()->with('success', 'ปฏิเสธการเข้าร่วมสำเร็จ');
    }

    /** สลับเปิด/ปิดอนุญาตบันทึกกิจกรรมก่อนเวลาเช็คอิน */
    public function toggleEarlyCheckin($id)
    {
        $activity = Activity::findOrFail($id);
        $activity->update(['allow_early_checkin' => !$activity->allow_early_checkin]);
        $this->auditToggle($activity, ($activity->allow_early_checkin ? 'เปิด' : 'ปิด') . "เช็คอินก่อนเวลา: \"{$activity->title}\"");

        $msg = $activity->allow_early_checkin
            ? "เปิดอนุญาตบันทึกกิจกรรมก่อนเวลาแล้ว"
            : "ปิดการบันทึกกิจกรรมก่อนเวลาแล้ว";

        return back()->with('success', $msg);
    }

    /**
     * AJAX: อนุมัติรายการ (registration หรือ attendance) จาก Dashboard
     * Body: { type: 'registration'|'attendance', id: number }
     */
    public function quickApprove(Request $request)
    {
        $request->validate([
            'type' => 'required|in:registration,attendance',
            'id'   => 'required|integer',
        ]);

        if ($request->type === 'registration') {
            $item = Registration::with(['user', 'activity'])->findOrFail($request->id);
            $item->update(['status' => 'approved']);
            $this->auditApprove($item, "อนุมัติการลงทะเบียน #{$item->id} (Dashboard)");
            $label = 'ลงทะเบียน';
        } else {
            $item = Attendance::with(['user', 'activity'])->findOrFail($request->id);
            $item->update(['status' => 'approved', 'is_verified' => true, 'verified_by' => auth()->id()]);
            $this->auditApprove($item, "อนุมัติการเข้าร่วม #{$item->id} (Dashboard)");
            // อัปเดต registration เป็น completed ถ้ามี
            $reg = Registration::where('user_id', $item->user_id)
                ->where('activity_id', $item->activity_id)->first();
            if ($reg && $reg->status === 'approved') {
                $reg->markAsCompleted();
            }
            $label = 'เช็คอิน';
        }

        // นับ pending ใหม่หลัง approve
        $pendingCount = Registration::where('status', 'pending')->count()
                      + Attendance::where('status', 'pending')->count();

        return response()->json([
            'ok'           => true,
            'message'      => "อนุมัติ{$label}สำเร็จ",
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * AJAX: ปฏิเสธรายการ (registration หรือ attendance) จาก Dashboard
     * Body: { type: 'registration'|'attendance', id: number, reason?: string }
     */
    public function quickReject(Request $request)
    {
        $request->validate([
            'type'   => 'required|in:registration,attendance',
            'id'     => 'required|integer',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($request->type === 'registration') {
            $item = Registration::with(['user', 'activity'])->findOrFail($request->id);
            $item->update(['status' => 'rejected']);
            $this->auditReject($item, "ปฏิเสธการลงทะเบียน #{$item->id} (Dashboard)");
            $label = 'ลงทะเบียน';
        } else {
            $item = Attendance::with(['user', 'activity'])->findOrFail($request->id);
            $item->update(['status' => 'rejected', 'verified_by' => auth()->id()]);
            $this->auditReject($item, "ปฏิเสธการเข้าร่วม #{$item->id} (Dashboard)");
            $label = 'เช็คอิน';
        }

        $pendingCount = Registration::where('status', 'pending')->count()
                      + Attendance::where('status', 'pending')->count();

        return response()->json([
            'ok'           => true,
            'message'      => "ปฏิเสธ{$label}เรียบร้อยแล้ว",
            'pending_count' => $pendingCount,
        ]);
    }

    /** 
     * สร้าง QR Code ใหม่ (Regenerate QR Token) และตั้งเวลาหมดอายุ (ถ้ามีการกำหนด)
     */
    public function regenerateQr(Request $request, $id, QrCodeService $qrService)
    {
        $activity = Activity::findOrFail($id);
        
        $request->validate([
            'expires_in_hours' => 'nullable|integer|min:1|max:720'
        ]);

        $oldToken = $activity->qr_token;
        $newToken = $qrService->generateToken();
        
        $expiresAt = null;
        if ($request->filled('expires_in_hours')) {
            $expiresAt = now()->addHours($request->expires_in_hours);
        }

        $activity->update([
            'qr_token' => $newToken,
            'qr_expires_at' => $expiresAt
        ]);

        $this->auditUpdate($activity, ['qr_token' => $oldToken], "สร้าง QR Code ใหม่สำหรับกิจกรรม \"{$activity->title}\"");

        return back()->with('success', 'สร้าง QR Code ใหม่สำเร็จแล้ว' . ($expiresAt ? ' (หมดอายุใน ' . $request->expires_in_hours . ' ชั่วโมง)' : ''));
    }
}
