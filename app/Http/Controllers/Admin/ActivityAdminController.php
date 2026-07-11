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
use App\Models\Message;
use App\Models\Room;
use App\Models\ActivityFeedback;
use App\Models\AdminAuditLog;
use App\Services\ImageOptimizationService;
use App\Services\QrCodeService;
use App\Events\ActivityPublished;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $userId = Auth::id();
        $cacheTtl = 300; // 5 minutes

        // 1. ดึงสถิติหลัก (ใช้ Cache เพื่อลด TTFB)
        $stats = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_stats', $cacheTtl, function() {
            return [
                'totalActivities'      => Activity::count(),
                'upcomingActivities'   => Activity::whereIn('status', ['upcoming', 'open'])->count(),
                'totalStudents'        => User::where('role', 'student')->count(),
                'totalRegistrations'   => Registration::whereIn('status', ['pending', 'approved'])->count(),
                'pendingRegistrations' => Registration::where('status', 'pending')->count(),
                'pendingAttendances'   => Attendance::where('status', 'pending')->count(),
                'upcomingThisWeek'     => Activity::whereBetween('activity_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->whereIn('status', ['upcoming', 'open', 'ongoing'])
                    ->count(),
                'totalJobs'            => JobListing::count(),
                'totalFeedbacks'       => ActivityFeedback::count(),
            ];
        });

        // 2. สถิติเฉพาะบุคคล (เช่น ข้อความที่ยังไม่อ่าน) - แยก Cache ตาม User
        $stats['unreadMessages'] = \Illuminate\Support\Facades\Cache::remember("user_{$userId}_unread_msgs", 60, function() use ($userId) {
            return Message::whereHas('room', function($q) use ($userId) {
                $q->whereHas('users', function($u) use ($userId) {
                    $u->where('users.id', $userId);
                });
            })->where('user_id', '!=', $userId)
              ->whereColumn('created_at', '>', DB::raw('(SELECT last_read_at FROM room_user WHERE room_user.room_id = messages.room_id AND room_user.user_id = ' . $userId . ')'))
              ->count();
        });

        // 3. ข้อมูลรายการล่าสุด (ไม่ Cache เพื่อให้เห็นความเคลื่อนไหวทันที แต่จำกัดจำนวน)
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
    public function store(StoreActivityRequest $request, QrCodeService $qrService, ImageOptimizationService $imageOptimizer)
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['qr_token'] = $qrService->generateToken();
        $data['qr_checkout_token'] = $qrService->generateToken();
        $data['is_mandatory'] = $request->boolean('is_mandatory');
        $data['allow_walkin'] = $request->has('allow_walkin') ? $request->boolean('allow_walkin') : true;
        $data['require_attendance_approval'] = $request->boolean('require_attendance_approval');
        if (($data['scope'] ?? 'university') === 'university') {
            $data['faculty'] = null;
            $data['department'] = null;
        } elseif (($data['scope'] ?? 'university') === 'faculty') {
            $data['department'] = null;
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $imageOptimizer->storeActivityImageAsWebp($request->file('image'));
        }

        $activity = Activity::create($data);
        $this->auditCreate($activity, "สร้างกิจกรรม \"{$activity->title}\"");

        // ยิง event เพื่อส่ง LINE notification แบบ async
        ActivityPublished::dispatch($activity);

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
    public function update(Request $request, $id, ImageOptimizationService $imageOptimizer)
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
            'require_attendance_approval' => 'boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        $data['is_mandatory'] = $request->boolean('is_mandatory');
        $data['allow_walkin'] = $request->has('allow_walkin') ? $request->boolean('allow_walkin') : true;
        $data['require_attendance_approval'] = $request->boolean('require_attendance_approval');
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
            if ($activity->image_path) {
                Storage::disk('public')->delete($activity->image_path);
            }

            $data['image_path'] = $imageOptimizer->storeActivityImageAsWebp($request->file('image'));
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
        $registration = Registration::with('activity')->findOrFail($id);
        $registration->update(['status' => 'approved']);
        $this->auditApprove($registration, "อนุมัติการลงทะเบียน #{$registration->id}");

        \App\Models\Notification::create([
            'user_id' => $registration->user_id,
            'title'   => 'การลงทะเบียนได้รับการอนุมัติ',
            'message' => "การขอเข้าร่วมกิจกรรม \"{$registration->activity->title}\" ได้รับการอนุมัติแล้ว",
            'type'    => 'registration_approved',
        ]);

        return back()->with('success', 'อนุมัติการลงทะเบียนสำเร็จ');
    }

    /** ปฏิเสธการลงทะเบียนของนักศึกษา */
    public function rejectRegistration($id)
    {
        $registration = Registration::with('activity')->findOrFail($id);
        $registration->update(['status' => 'rejected']);
        $this->auditReject($registration, "ปฏิเสธการลงทะเบียน #{$registration->id}");

        \App\Models\Notification::create([
            'user_id' => $registration->user_id,
            'title'   => 'การลงทะเบียนถูกปฏิเสธ',
            'message' => "การขอเข้าร่วมกิจกรรม \"{$registration->activity->title}\" ไม่ได้รับการอนุมัติ",
            'type'    => 'registration_rejected',
        ]);

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
            'qr_checkout_token' => $qrService->generateToken(),
        ]));
        $this->auditCreate($activity, "สร้างกิจกรรมด่วน \"{$activity->title}\"");

        return redirect()->route('admin.dashboard')->with('success', 'สร้างกิจกรรมด่วนสำเร็จ!');
    }

    /** อนุมัติการเข้าร่วมกิจกรรม (attendance) */
    public function approveAttendance($id)
    {
        $attendance = Attendance::with('activity')->findOrFail($id);
        $attendance->update([
            'status'      => 'approved',
            'is_verified' => true,
            'verified_by' => auth()->id(),
        ]);
        $this->auditApprove($attendance, "อนุมัติการเข้าร่วม #{$attendance->id}");

        \App\Models\Notification::create([
            'user_id' => $attendance->user_id,
            'title'   => 'บันทึกกิจกรรมสำเร็จ',
            'message' => "บันทึกการเข้าร่วมกิจกรรม \"{$attendance->activity->title}\" ได้รับการอนุมัติแล้ว คุณได้รับ {$attendance->activity->activity_hours} ชม.",
            'type'    => 'attendance_approved',
        ]);

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
        $attendance = Attendance::with('activity')->findOrFail($id);
        $attendance->update([
            'status'      => 'rejected',
            'verified_by' => auth()->id(),
        ]);
        $this->auditReject($attendance, "ปฏิเสธการเข้าร่วม #{$attendance->id}");

        \App\Models\Notification::create([
            'user_id' => $attendance->user_id,
            'title'   => 'บันทึกกิจกรรมไม่สำเร็จ',
            'message' => "บันทึกการเข้าร่วมกิจกรรม \"{$attendance->activity->title}\" ถูกปฏิเสธ กรุณาติดต่อผู้จัด",
            'type'    => 'attendance_rejected',
        ]);

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

    /** แสดงหน้าสแกน QR นักศึกษาสำหรับเจ้าหน้าที่ */
    public function scanner($id)
    {
        $activity = Activity::findOrFail($id);
        return view('admin.checkin.scanner', compact('activity'));
    }

    /** ดำเนินการเช็คอินให้นักศึกษาจากการสแกน QR ส่วนตัว (Staff Scan) */
    public function scanStudent(Request $request, $id, \App\Services\CheckInService $checkInService)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $decoded = base64_decode($request->token);
            $parts = explode('|', $decoded);
            
            if (count($parts) !== 3) {
                return response()->json(['success' => false, 'message' => 'QR Code รูปแบบไม่ถูกต้อง'], 400);
            }

            [$userId, $timeWindow, $signature] = $parts;
            
            // ตรวจสอบ Signature
            $payload = $userId . '|' . $timeWindow;
            $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));
            
            if (!hash_equals($expectedSignature, $signature)) {
                return response()->json(['success' => false, 'message' => 'QR Code ไม่ถูกต้องหรือถูกปลอมแปลง'], 403);
            }

            // ตรวจสอบเวลา (อนุญาตให้เหลื่อมได้ 1 window คือ 30 วินาที)
            $currentTimeWindow = floor(now()->timestamp / 30);
            if (abs($currentTimeWindow - $timeWindow) > 1) {
                return response()->json(['success' => false, 'message' => 'QR Code หมดอายุแล้ว กรุณารีเฟรชหน้าจอ'], 403);
            }

            $user = User::findOrFail($userId);
            $activity = Activity::findOrFail($id);

            // จัดการเรื่องการลงทะเบียน (ถ้ายังไม่ลง → ลงให้เลยแบบ walk-in)
            $registration = Registration::where('user_id', $user->id)
                ->where('activity_id', $activity->id)
                ->first();

            if (!$registration) {
                // ตรวจสอบที่ว่าง
                if ($activity->getRemainingSlots() <= 0) {
                    return response()->json(['success' => false, 'message' => 'ไม่สามารถลงทะเบียนได้เนื่องจากกิจกรรมเต็มแล้ว'], 400);
                }

                $registration = Registration::create([
                    'user_id'       => $user->id,
                    'activity_id'   => $activity->id,
                    'status'        => 'approved',
                    'registered_at' => now(),
                    'notes'         => 'ลงทะเบียนหน้างานโดยเจ้าหน้าที่ (Staff Scan)',
                ]);
            } elseif ($registration->status !== 'approved') {
                $registration->update(['status' => 'approved']);
            }

            // บันทึกการเช็คอิน
            $result = $checkInService->processCheckIn(
                $activity->qr_token,
                $user,
                'staff_scan'
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "บันทึกการเข้าร่วมของ {$user->full_name} สำเร็จ",
                    'student' => [
                        'name' => $user->full_name,
                        'student_id' => $user->student_id,
                    ]
                ]);
            }

            return response()->json(['success' => false, 'message' => $result['message']], 400);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
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

    /** 
     * สร้าง QR Code ออกงานใหม่ (Regenerate Checkout QR Token)
     */
    public function regenerateCheckoutQr(Request $request, $id, QrCodeService $qrService)
    {
        $activity = Activity::findOrFail($id);
        
        $request->validate([
            'expires_in_hours' => 'nullable|integer|min:1|max:720'
        ]);

        $oldToken = $activity->qr_checkout_token;
        $newToken = $qrService->generateToken();
        
        $expiresAt = null;
        if ($request->filled('expires_in_hours')) {
            $expiresAt = now()->addHours($request->expires_in_hours);
        }

        $activity->update([
            'qr_checkout_token' => $newToken,
            // You could store a separate qr_checkout_expires_at if you want, 
            // but for now we reuse qr_expires_at or leave it unchanged
        ]);

        $this->auditUpdate($activity, ['qr_checkout_token' => $oldToken], "สร้าง QR Code ออกงานใหม่สำหรับกิจกรรม \"{$activity->title}\"");

        return back()->with('success', 'สร้าง QR Code ออกงานใหม่สำเร็จแล้ว');
    }
}
