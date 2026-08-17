<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\FaceVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * คอนโทรลเลอร์เช็คอิน / บันทึกกิจกรรม
 * จัดการเช็คอินผ่าน QR Code และบันทึกกิจกรรมด้วยตัวเอง (self check-in)
 */
class CheckInController extends Controller
{
    /** รับ service เช็คอิน และ service ตรวจสอบใบหน้าผ่าน dependency injection */
    public function __construct(
        private readonly CheckInService $checkInService,
        private readonly FaceVerificationService $faceVerificationService
    ) {}

    /** แสดงหน้าเช็คอิน/ออกงานจาก QR Code (ใช้ token จาก URL) */
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
            
            // Auto-Extract face encodings (512D + 128D) if missing but has profile photo
            if ((!$user->face_descriptor || !$user->face_descriptor_js) && $user->profile_photo) {
                $photoPath = storage_path('app/public/' . $user->profile_photo);
                $aiServerUrl = config('services.ai_server.url');
                $aiKey = config('services.ai_server.key');
                if (file_exists($photoPath) && !empty($aiServerUrl)) {
                    try {
                        Log::info("Auto-extracting missing face encodings for user {$user->id} on check-in page visit");
                        
                        $httpReq = Http::timeout(10);
                        if (!empty($aiKey)) {
                            $httpReq = $httpReq->withHeaders(['X-API-Key' => $aiKey]);
                        }
                        $response = $httpReq
                            ->attach('image', file_get_contents($photoPath), basename($photoPath))
                            ->post(rtrim($aiServerUrl, '/') . '/extract');
                        
                        if ($response->successful()) {
                            $aiResult = $response->json();
                            $updateData = [];
                            $extracted = [];
                            
                            // Update only missing encodings
                            if (!$user->face_descriptor && !empty($aiResult['embedding_512d'])) {
                                $updateData['face_descriptor'] = $aiResult['embedding_512d'];
                                $extracted[] = '512D';
                            }
                            if (!$user->face_descriptor_js && !empty($aiResult['embedding_128d'])) {
                                $updateData['face_descriptor_js'] = $aiResult['embedding_128d'];
                                $extracted[] = '128D';
                            }
                            
                            if (!$user->face_descriptor && empty($updateData['face_descriptor']) && !empty($aiResult['embedding'])) {
                                $updateData['face_descriptor'] = $aiResult['embedding'];
                                $extracted[] = '512D (legacy)';
                            }
                            
                            if (!empty($updateData)) {
                                $user->update($updateData);
                                Log::info("Auto-extracted " . implode(' + ', $extracted) . " for user {$user->id}");
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Auto-extraction failed for user {$user->id}: " . $e->getMessage());
                    }
                }
            }

            $profilePhotoUrl = $user->profile_photo
                ? asset('storage/' . $user->profile_photo)
                : null;
            $faceScanMethod = $activity->face_scan_method ?? 'python';
            $profileJsDescriptor = $user->face_descriptor_js ? json_encode($user->face_descriptor_js) : 'null';
            
            return view('checkin.selfie', compact('activity', 'token', 'isCheckoutToken', 'profilePhotoUrl', 'faceScanMethod', 'profileJsDescriptor'));
        }

        return view('checkin.scan', compact('activity', 'token', 'isCheckoutToken'));
    }

    /**
     * ดำเนินการเช็คอิน/ออกงานผ่าน QR Code พร้อมการตรวจสอบใบหน้าบน Server เท่านั้น (Server-Authoritative)
     */
    public function store(Request $request, string $token): View|RedirectResponse
    {
        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();
            
        if ($activity->qr_expires_at && now()->gt($activity->qr_expires_at)) {
            return back()->with('error', 'QR Code หมดอายุแล้ว');
        }

        $user = $request->user();
        $isCheckoutToken = ($activity->qr_checkout_token === $token);
        
        $score = null;
        $passed = null;
        $livenessPassed = null;
        $savedSelfieFilename = null;

        // ── ตรวจสอบ Face Verification บน Server เมื่อกิจกรรมกำหนดให้ต้องสแกนใบหน้า ──
        if ($activity->require_face_scan) {
            if (!$request->filled('selfie')) {
                return back()->with('error', 'กิจกรรมนี้กำหนดให้ต้องสแกนใบหน้า กรุณาถ่ายภาพเซลฟี่เพื่อยืนยันตัวตน');
            }

            // ส่งรูปเซลฟี่ไปตรวจสอบบน Python AI Server โดยตรง (ห้ามเชื่อถือ Client-side Score)
            $faceResult = $this->faceVerificationService->verifyFace($user, (string) $request->selfie, [
                'mode' => 'python',
            ]);

            $passed         = (bool) ($faceResult['is_match'] ?? false);
            $score          = (float) ($faceResult['score_percentage'] ?? 0);
            $livenessPassed = (bool) ($faceResult['liveness_passed'] ?? ($faceResult['liveness']['passed'] ?? true));

            // หากผู้ใช้มี Face Descriptor ลงทะเบียนไว้และ Server ตรวจไม่ผ่าน หรือติด Liveness Anti-Spoofing -> ปฏิเสธทันที
            if ($user->face_descriptor && (!$passed || !$livenessPassed)) {
                $reason = !$passed 
                    ? "ใบหน้าไม่ตรงกับข้อมูลในระบบ (คะแนนความคล้ายคลึง: " . round($score, 1) . "%)" 
                    : "การตรวจสอบ Liveness ไม่ผ่าน (ตรวจพบรูปถ่าย/ภาพปลอม)";

                Log::warning("Face Verification Rejected for user {$user->id} on activity {$activity->id}: {$reason}");

                return back()->with('error', "การยืนยันใบหน้าไม่ผ่าน: {$reason} กรุณาสแกนใบหน้าจริงใหม่อีกครั้ง");
            }
        }

        // ── ดำเนินการบันทึก Check-in ผ่าน CheckInService ──
        $result = $this->checkInService->processCheckIn(
            $token,
            $user,
            'qr_scan',
            $request->filled('latitude') ? (float) $request->latitude : null,
            $request->filled('longitude') ? (float) $request->longitude : null,
        );

        if ($result['success']) {
            if ($request->filled('selfie') && !empty($result['attendance_id'])) {
                $att = Attendance::find($result['attendance_id']);
                if ($att) {
                    $imageData = (string) $request->selfie;
                    $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                    $imageDecoded = base64_decode(str_replace(' ', '+', $imageData));

                    $isCheckout = in_array($result['status'], ['approved', 'pending'], true) && $result['status'] !== 'checked_in';
                    
                    if ($isCheckout) {
                        $savedSelfieFilename = 'selfies/checkout_' . $att->id . '_' . time() . '.jpg';
                    } else {
                        $savedSelfieFilename = 'selfies/' . $att->id . '_' . time() . '.jpg';
                    }
                    Storage::disk('public')->put($savedSelfieFilename, $imageDecoded);

                    if ($isCheckout) {
                        $att->update([
                            'checkout_selfie_photo_path' => $savedSelfieFilename,
                            'checkout_face_match_score'  => $score,
                            'checkout_face_match_passed' => $passed,
                        ]);

                        // ลบรูปเซลฟี่ทั้งเข้าและออกทิ้งเมื่อจบกิจกรรมและ AI ตรวจผ่าน (PDPA / Privacy)
                        if ($passed) {
                            if ($att->selfie_photo_path && Storage::disk('public')->exists($att->selfie_photo_path)) {
                                Storage::disk('public')->delete($att->selfie_photo_path);
                            }
                            if ($att->checkout_selfie_photo_path && Storage::disk('public')->exists($att->checkout_selfie_photo_path)) {
                                Storage::disk('public')->delete($att->checkout_selfie_photo_path);
                            }
                            $att->update([
                                'selfie_photo_path'          => null,
                                'checkout_selfie_photo_path' => null,
                            ]);
                        }
                    } else {
                        $att->update([
                            'selfie_photo_path' => $savedSelfieFilename,
                            'face_match_score'  => $score,
                            'face_match_passed' => $passed,
                        ]);
                    }
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

    /** ตรวจสอบภาพเรียวไทม์จากหน้าจอเซลฟี่ผ่าน FaceVerificationService กลาง */
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

    /** แสดงหน้าถ่าย selfie เพื่อยืนยันตัวตน */
    public function selfiePage(string $token, int $attendance): View|RedirectResponse
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

    /** บันทึก selfie + คะแนนเปรียบเทียบใบหน้า + liveness ด้วย Server-Side Evaluation */
    public function storeSelfie(Request $request, string $token, int $attendance): View|RedirectResponse
    {
        $request->validate([
            'selfie' => 'required|string',
        ]);

        $att = Attendance::where('id', $attendance)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $user = auth()->user();
        $base64 = (string) $request->input('selfie');
        $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $base64));

        $filename = 'selfies/' . auth()->id() . '_' . time() . '.jpg';
        Storage::disk('public')->put($filename, $imageData);

        // ตรวจสอบผ่าน FaceVerificationService
        $faceResult = $this->faceVerificationService->verifyFace($user, $base64, [
            'mode' => 'python',
        ]);

        $score            = (float) ($faceResult['score_percentage'] ?? 0);
        $passed           = (bool) ($faceResult['is_match'] ?? false);
        $livenessScore    = $faceResult['liveness_score'] ?? ($faceResult['liveness']['score'] ?? null);
        $livenessPassed   = $faceResult['liveness_passed'] ?? ($faceResult['liveness']['passed'] ?? null);
        $detectorPipeline = $faceResult['detector_used'] ?? ($faceResult['pipeline'] ?? null);

        $att->update([
            'selfie_photo_path' => $filename,
            'face_match_score'  => $score,
            'face_match_passed' => $passed,
            'liveness_score'    => $livenessScore,
            'liveness_passed'   => $livenessPassed,
            'detector_pipeline' => $detectorPipeline,
        ]);

        $activity = Activity::where('qr_token', $token)
            ->orWhere('qr_checkout_token', $token)
            ->firstOrFail();

        return view('checkin.success', [
            'activity'         => $activity,
            'status'           => 'checked_in',
            'distance'         => $att->distance_meters,
            'selfie_result'    => $passed,
            'face_match_score' => $score,
            'liveness_passed'  => $livenessPassed,
        ]);
    }

    /** บันทึกกิจกรรมด้วยตัวเอง (ไม่ต้องสแกน QR) → ส่งพิกัด GPS เพื่อตรวจสอบอัตโนมัติ */
    public function selfCheckIn(Request $request, int|string $activityId): RedirectResponse
    {
        Activity::findOrFail($activityId);

        return back()->with('error', 'กรุณาสแกน QR Code หน้างานเพื่อเช็คอินกิจกรรม');
    }

    /** แสดงหน้า Walk-in Check-in สำหรับ staff/admin หน้างาน */
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

    /** ดำเนินการ Walk-in Check-in: staff/admin ค้นหานักศึกษาจากรหัส → บันทึก attendance อัตโนมัติ */
    public function walkInStore(Request $request, string $token): RedirectResponse
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
            'user_id'       => $user->id,
            'activity_id'   => $activity->id,
            'method'        => 'walk_in',
            'status'        => 'approved',
            'is_verified'   => true,
            'checked_in_at' => now(),
            'ip_address'    => $request->ip(),
        ]);

        broadcast(new \App\Events\AttendeeCheckedIn($token, $user))->toOthers();

        return back()
            ->with('success', 'บันทึกการเข้าร่วมของ ' . $user->full_name . ' (' . $user->student_id . ') สำเร็จ')
            ->with('checked_in_student', [
                'id'             => $user->id,
                'name'           => $user->full_name,
                'student_id'     => $user->student_id,
                'activity_id'    => $activity->id,
                'activity_title' => $activity->title
            ]);
    }

    /** API: ดึงรายชื่อผู้เข้าร่วมกิจกรรม walk-in แบบ real-time (JSON) */
    public function walkInAttendees(string $token): JsonResponse
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
