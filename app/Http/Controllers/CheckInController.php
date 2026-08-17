<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Services\CheckInService;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * คอนโทรลเลอร์เช็คอิน / บันทึกกิจกรรม (Thin Controller)
 * จัดการ HTTP routing สำหรับ QR check-in, Face verification, และ Walk-in monitor
 */
class CheckInController extends Controller
{
    public function __construct(
        private readonly CheckInService $checkInService,
        private readonly FaceVerificationService $faceVerificationService,
    ) {}

    /**
     * แสดงหน้าเช็คอิน/ออกงานจาก QR Code (ใช้ token จาก URL)
     */
    public function show(string $token): View|RedirectResponse
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            abort(403, 'QR Code หมดอายุแล้ว');
        }

        $isCheckoutToken = ($activity->qr_checkout_token === $token);

        if ($activity->require_face_scan) {
            $user = auth()->user();

            // Auto-extract face encodings if missing via FaceVerificationService
            $this->faceVerificationService->ensureFaceEncodings($user);

            $profilePhotoUrl = $user->profile_photo ? asset('storage/' . $user->profile_photo) : null;
            $faceScanMethod  = $activity->face_scan_method ?? 'python';

            return view('checkin.selfie', compact('activity', 'token', 'isCheckoutToken', 'profilePhotoUrl', 'faceScanMethod'));
        }

        return view('checkin.scan', compact('activity', 'token', 'isCheckoutToken'));
    }

    /**
     * ดำเนินการเช็คอิน/ออกงานผ่าน QR Code พร้อมการตรวจสอบใบหน้าบน Server (Server-Authoritative)
     */
    public function store(Request $request, string $token): View|RedirectResponse
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            return back()->with('error', 'QR Code หมดอายุแล้ว');
        }

        $result = $this->checkInService->processQrCheckInWithFace(
            $activity,
            $request->user(),
            $token,
            $request->filled('selfie') ? (string) $request->selfie : null,
            $request->filled('latitude') ? (float) $request->latitude : null,
            $request->filled('longitude') ? (float) $request->longitude : null,
        );

        if ($result['success']) {
            return view('checkin.success', [
                'activity' => $result['activity'],
                'status'   => $result['status'],
                'distance' => $result['distance'],
            ]);
        }

        return back()->with('error', $result['message']);
    }

    /**
     * ตรวจสอบภาพเรียวไทม์จากหน้าจอเซลฟี่ผ่าน FaceVerificationService กลาง
     */
    public function verifyFrame(Request $request, string $token): JsonResponse
    {
        $request->validate(['image' => 'required|string']);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if (!$user->face_descriptor) {
            return response()->json([
                'success' => false,
                'message' => 'No profile descriptor',
            ]);
        }

        // Rate limiting per user: 1 request per second
        $cacheKey = 'face_verify_' . $user->id;
        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests',
            ]);
        }
        Cache::put($cacheKey, true, 1);

        $result = $this->faceVerificationService->verifyFace($user, (string) $request->input('image'), [
            'mode' => 'python',
        ]);

        return response()->json($result);
    }

    /**
     * แสดงหน้าถ่าย selfie เพื่อยืนยันตัวตน
     */
    public function selfiePage(string $token, int $attendance): View|RedirectResponse
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        $att = Attendance::where('id', $attendance)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $user = auth()->user();
        $profilePhotoUrl = $user->profile_photo ? asset('storage/' . $user->profile_photo) : null;

        return view('checkin.selfie', compact('activity', 'token', 'att', 'profilePhotoUrl'));
    }

    /**
     * บันทึก selfie + คะแนนเปรียบเทียบใบหน้า + liveness ด้วย Server-Side Evaluation
     */
    public function storeSelfie(Request $request, string $token, int $attendance): View|RedirectResponse
    {
        $request->validate([
            'selfie' => 'required|string',
        ]);

        $att = Attendance::where('id', $attendance)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        $selfieResult = $this->checkInService->saveSelfieForAttendance(
            $att,
            auth()->user(),
            (string) $request->input('selfie'),
            $activity
        );

        return view('checkin.success', [
            'activity'         => $activity,
            'status'           => 'checked_in',
            'distance'         => $att->distance_meters,
            'selfie_result'    => $selfieResult['passed'],
            'face_match_score' => $selfieResult['score'],
            'liveness_passed'  => $selfieResult['liveness_passed'],
        ]);
    }

    /**
     * บันทึกกิจกรรมด้วยตัวเอง
     */
    public function selfCheckIn(Request $request, int|string $activityId): RedirectResponse
    {
        Activity::findOrFail($activityId);

        return back()->with('error', 'กรุณาสแกน QR Code หน้างานเพื่อเช็คอินกิจกรรม');
    }

    /**
     * แสดงหน้า Walk-in Check-in สำหรับ staff/admin หน้างาน
     */
    public function walkInPage(string $token): View
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

    /**
     * ดำเนินการ Walk-in Check-in: staff/admin ค้นหานักศึกษาจากรหัส
     */
    public function walkInStore(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'student_id' => 'required|string',
        ]);

        $activity = Activity::where('qr_token', $token)->firstOrFail();

        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            return back()->with('error', 'QR Code หมดอายุแล้ว')->withInput();
        }

        $result = $this->checkInService->processWalkInCheckIn(
            $activity,
            (string) $request->student_id,
            $token,
            $request->ip()
        );

        if ($result['success']) {
            return back()
                ->with('success', $result['message'])
                ->with('checked_in_student', $result['checked_in_student']);
        }

        return back()->with('error', $result['message'])->withInput();
    }

    /**
     * API: ดึงรายชื่อผู้เข้าร่วมกิจกรรม walk-in แบบ real-time (JSON)
     */
    public function walkInAttendees(string $token): JsonResponse
    {
        if (!auth()->check() || (!auth()->user()->isStaff() && !auth()->user()->isAdmin())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $activity = Activity::where('qr_token', $token)->firstOrFail();
        $attendances = $this->checkInService->getWalkInAttendees($activity);

        return response()->json([
            'count'       => count($attendances),
            'attendances' => $attendances,
        ]);
    }
}
