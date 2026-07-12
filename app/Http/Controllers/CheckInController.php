<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * คอนโทรลเลอร์เช็คอิน / บันทึกกิจกรรม
 * จัดการเช็คอินผ่าน QR Code และบันทึกกิจกรรมด้วยตัวเอง (self check-in)
 */
class CheckInController extends Controller
{
    /** รับ service เช็คอินผ่าน dependency injection */
    public function __construct(private CheckInService $checkInService)
    {
    }

    /** แสดงหน้าเช็คอิน/ออกงานจาก QR Code (ใช้ token จาก URL) */
    public function show(string $token)
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            abort(403, 'QR Code หมดอายุแล้ว');
        }

        $isCheckoutToken = ($activity->qr_checkout_token === $token);
        
        if ($activity->require_selfie_verification && !$isCheckoutToken) {
            $user = auth()->user();
            $profilePhotoUrl = $user->profile_photo
                ? asset('storage/' . $user->profile_photo)
                : null;
            return view('checkin.selfie', compact('activity', 'token', 'isCheckoutToken', 'profilePhotoUrl'));
        }

        return view('checkin.scan', compact('activity', 'token', 'isCheckoutToken'));
    }

    /** ดำเนินการเช็คอิน/ออกงานผ่าน QR Code → เรียก CheckInService พร้อมพิกัด GPS */
    public function store(Request $request, string $token)
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();
            
        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            return back()->with('error', 'QR Code หมดอายุแล้ว');
        }
        $result = $this->checkInService->processCheckIn(
            $token,
            $request->user(),
            'qr_scan',
            $request->filled('latitude') ? (float) $request->latitude : null,
            $request->filled('longitude') ? (float) $request->longitude : null,
        );

        if ($result['success']) {
            // หากมีการส่งข้อมูล selfie มาด้วย (จากหน้า selfie.blade.php) ให้บันทึกรูปเลย
            if ($request->filled('selfie') && !empty($result['attendance_id'])) {
                $att = Attendance::find($result['attendance_id']);
                if ($att) {
                    $imageData = $request->selfie;
                    $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
                    $imageData = str_replace(' ', '+', $imageData);
                    $imageDecoded = base64_decode($imageData);

                    $filename = 'selfies/' . $att->id . '_' . time() . '.jpg';
                    Storage::disk('public')->put($filename, $imageDecoded);

                    $score = $request->filled('face_match_score') ? (float) $request->face_match_score : null;
                    $passed = ($score !== null && $score >= 60);

                    $att->update([
                        'selfie_photo_path' => $filename,
                        'face_match_score'  => $score,
                        'face_match_passed' => $passed,
                    ]);
                }
            }

            return view('checkin.success', [
                'activity' => $result['activity'],
                'status'   => $result['status'],
                'distance' => $result['distance'],
            ]);
        }

        return back()->with('error', $result['message']);
    }

    /** แสดงหน้าถ่าย selfie เพื่อยืนยันตัวตน */
    public function selfiePage(string $token, int $attendance)
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        $att = Attendance::where('id', $attendance)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $user = auth()->user();
        $profilePhotoUrl = $user->profile_photo
            ? asset('storage/' . $user->profile_photo)
            : null;

        return view('checkin.selfie', compact('activity', 'token', 'att', 'profilePhotoUrl'));
    }

    /** บันทึก selfie + คะแนนเปรียบเทียบใบหน้า */
    public function storeSelfie(Request $request, string $token, int $attendance)
    {
        $request->validate([
            'selfie' => 'required|string', // base64 image
            'face_match_score' => 'nullable|numeric|min:0|max:100',
        ]);

        $att = Attendance::where('id', $attendance)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Decode base64 selfie and save to storage
        $base64 = $request->input('selfie');
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $imageData = base64_decode($imageData);

        $filename = 'selfies/' . auth()->id() . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $imageData);

        $score = $request->input('face_match_score', null);
        $threshold = 60; // ค่า threshold 60%
        $passed = $score !== null ? ((float) $score >= $threshold) : null;

        $att->update([
            'selfie_photo_path' => $filename,
            'face_match_score'  => $score,
            'face_match_passed' => $passed,
        ]);

        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        return view('checkin.success', [
            'activity' => $activity,
            'status'   => 'checked_in',
            'distance' => $att->distance_meters,
            'selfie_result' => $passed,
            'face_match_score' => $score,
        ]);
    }

    /** บันทึกกิจกรรมด้วยตัวเอง (ไม่ต้องสแกน QR) → ส่งพิกัด GPS เพื่อตรวจสอบอัตโนมัติ */
    public function selfCheckIn(Request $request, $activityId)
    {
        Activity::findOrFail($activityId);

        return back()->with('error', 'กรุณาสแกน QR Code หน้างานเพื่อเช็คอินกิจกรรม');
    }

    /** แสดงหน้า Walk-in Check-in สำหรับ staff/admin หน้างาน */
    public function walkInPage(string $token)
    {
        $activity = Activity::where('qr_token', $token)->firstOrFail();

        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            abort(403, 'QR Code หมดอายุแล้ว');
        }

        $attendances = Attendance::with('user')
            ->where('activity_id', $activity->id)
            ->orderByDesc('checked_in_at')
            ->get();

        return view('checkin.walkin', compact('activity', 'token', 'attendances'));
    }

    /** ดำเนินการ Walk-in Check-in: staff/admin ค้นหานักศึกษาจากรหัส → บันทึก attendance อัตโนมัติ */
    public function walkInStore(Request $request, string $token)
    {
        $request->validate([
            'student_id' => 'required|string',
        ]);

        $activity = Activity::where('qr_token', $token)->firstOrFail();
        
        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            return back()->with('error', 'QR Code หมดอายุแล้ว')->withInput();
        }

        $now = now();
        if (!$activity->allow_early_checkin && $now->lt($activity->checkin_open_at)) {
            return back()->with('error', 'ยังไม่ถึงเวลาเช็คอิน — เปิดเช็คอินเวลา ' . $activity->checkin_open_at->format('d/m/Y H:i'))->withInput();
        }
        if ($now->gt($activity->checkin_close_at)) {
            return back()->with('error', 'หมดเวลาเช็คอินแล้ว (ปิดเมื่อ ' . $activity->checkin_close_at->format('d/m/Y H:i') . ')')->withInput();
        }

        $user = User::where('student_id', $request->student_id)
            ->where('users.role', 'student')
            ->first();

        if (!$user) {
            return back()->with('error', 'ไม่พบรหัสนักศึกษา "' . $request->student_id . '" ในระบบ')->withInput();
        }

        if (Attendance::where('user_id', $user->id)->where('activity_id', $activity->id)->exists()) {
            return back()->with('error', 'นักศึกษา ' . $user->full_name . ' (' . $user->student_id . ') เช็คอินไปแล้ว')->withInput();
        }

        Attendance::create([
            'user_id'      => $user->id,
            'activity_id'  => $activity->id,
            'method'       => 'walk_in',
            'status'       => 'approved',
            'is_verified'  => true,
            'checked_in_at' => now(),
            'ip_address'   => $request->ip(),
        ]);

        broadcast(new \App\Events\AttendeeCheckedIn($token, $user))->toOthers();

        return back()
            ->with('success', 'บันทึกการเข้าร่วมของ ' . $user->full_name . ' (' . $user->student_id . ') สำเร็จ')
            ->with('checked_in_student', [
                'id' => $user->id,
                'name' => $user->full_name,
                'student_id' => $user->student_id,
                'activity_id' => $activity->id,
                'activity_title' => $activity->title
            ]);
    }

    /** API: ดึงรายชื่อผู้เข้าร่วมกิจกรรม walk-in แบบ real-time (JSON) */
    public function walkInAttendees(string $token)
    {
        if (!auth()->check() || (!auth()->user()->isStaff() && !auth()->user()->isAdmin())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $activity = Activity::where('qr_token', $token)->firstOrFail();

        $attendances = Attendance::with('user')
            ->where('activity_id', $activity->id)
            ->orderByDesc('checked_in_at')
            ->get()
            ->map(fn($att) => [
                'student_id'    => $att->user->student_id,
                'full_name'     => $att->user->full_name,
                'faculty'       => $att->user->faculty ?? '-',
                'checked_in_at' => $att->checked_in_at?->format('d/m/Y H:i:s') ?? $att->created_at->format('d/m/Y H:i:s'),
                'method'        => $att->method,
            ]);

        return response()->json([
            'count'       => $attendances->count(),
            'attendances' => $attendances,
        ]);
    }
}

