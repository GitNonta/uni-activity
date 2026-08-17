<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\SecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use function App\Helpers\log_action;

class LoginOtpController extends Controller
{
    /** แสดงหน้ากรอก OTP สำหรับ Login */
    public function showVerifyForm(Request $request): View|RedirectResponse
    {
        if (!session()->has('login_otp_user_id')) {
            return redirect()->route('login');
        }

        $email = (string) session('login_otp_email');
        return view('auth.verify-login-otp', compact('email'));
    }

    /** ส่ง OTP ใหม่ พร้อมตรวจสอบ Cooldown */
    public function resend(Request $request): RedirectResponse
    {
        if (!session()->has('login_otp_user_id')) {
            return redirect()->route('login');
        }

        $userId = (int) session('login_otp_user_id');
        $cooldownKey = "otp_cooldown_{$userId}";

        // ตรวจสอบว่าอยู่ในช่วง Cooldown หรือไม่
        if (Cache::has($cooldownKey)) {
            return back()->with('status', 'รหัส OTP ถูกส่งไปแล้ว กรุณารอสักครู่ (60 วินาที) ก่อนขอรหัสใหม่');
        }

        $user = User::find($userId);
        if ($user) {
            $this->sendOtp($user, $request, force: true);
            return back()->with('status', 'ส่งรหัส OTP ใหม่เรียบร้อยแล้ว');
        }

        return back()->withErrors(['otp' => 'เกิดข้อผิดพลาดในการส่งรหัส OTP']);
    }

    /** ยืนยัน OTP และ Log in พร้อมระบบป้องกัน Brute-force และ Timing Attacks */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $userId = session('login_otp_user_id');
        $email  = session('login_otp_email');

        if (!$userId || !$email) {
            return redirect()->route('login');
        }

        $emailHash   = md5(strtolower((string) $email));
        $lockoutKey  = "otp_locked_{$emailHash}";
        $attemptsKey = "otp_failed_attempts_{$emailHash}";

        // 1. ตรวจสอบว่าถูกล็อคเนื่องจากกรอกผิดเกินจำนวนครั้งหรือไม่
        if (Cache::has($lockoutKey)) {
            throw ValidationException::withMessages([
                'otp' => 'บัญชีนี้ถูกระงับการยืนยัน OTP ชั่วคราวเนื่องจากกรอกผิดเกินกำหนด กรุณารอ 15 นาที',
            ]);
        }

        // 2. ดึง OTP ที่ยังไม่หมดอายุ
        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->first();

        // 3. ตรวจสอบความถูกต้องด้วย hash_equals ป้องกัน Timing Attack
        $isValidOtp = $otpRecord && hash_equals((string) $otpRecord->otp, (string) $request->otp);

        if (!$isValidOtp) {
            $failedAttempts = (int) Cache::increment($attemptsKey);
            Cache::put($attemptsKey, $failedAttempts, now()->addMinutes(15));

            // หากกรอกผิดเกิน 5 ครั้ง ให้ล็อคและลบ OTP ทันที (Anti Brute-force)
            if ($failedAttempts >= 5) {
                if ($otpRecord) {
                    DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();
                }
                Cache::put($lockoutKey, true, now()->addMinutes(15));
                Cache::forget($attemptsKey);

                throw ValidationException::withMessages([
                    'otp' => 'คุณกรอกรหัส OTP ผิดเกิน 5 ครั้ง รหัสถูกยกเลิกแล้วเพื่อความปลอดภัย กรุณารอ 15 นาทีหรือขอรหัสใหม่',
                ]);
            }

            $remaining = 5 - $failedAttempts;
            throw ValidationException::withMessages([
                'otp' => "รหัส OTP ไม่ถูกต้องหรือหมดอายุแล้ว (เหลือโอกาสอีก {$remaining} ครั้ง)",
            ]);
        }

        // 4. ยืนยันสำเร็จ -> ล้าง Failed Attempts, Cooldown และลบ OTP Record
        Cache::forget($attemptsKey);
        Cache::forget($lockoutKey);
        Cache::forget("otp_cooldown_{$userId}");
        DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();

        // ล็อกอิน
        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $remember = (bool) session('login_otp_remember', false);
        Auth::login($user, $remember);

        // บันทึก audit log
        log_action('login', null, null, 'User logged in via OTP');

        // --- บันทึก Device Fingerprint + ตรวจ Multi-Account ---
        /** @var DeviceFingerprintService $fpService */
        $fpService = app(DeviceFingerprintService::class);
        /** @var SecurityService $secService */
        $secService = app(SecurityService::class);

        $fingerprint = $fpService->generate($request);

        // บันทึก login metadata
        $user->update([
            'last_login_ip'           => $request->ip(),
            'last_login_at'           => now(),
            'last_device_fingerprint' => $fingerprint,
        ]);

        // ตรวจจับ multi-account บนเครื่องเดียวกัน
        $secService->checkAndLogMultiAccountLogin($request, $user->id);

        // ล้าง session ชั่วคราว
        session()->forget(['login_otp_user_id', 'login_otp_email', 'login_otp_remember']);

        if ($user->isAdmin() || $user->isStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }
        return redirect()->intended(route('activities.index'));
    }

    /**
     * ฟังก์ชันช่วยส่ง OTP พร้อม Atomic Lock และ Deduplication ป้องกันการส่งซ้ำ
     */
    public function sendOtp(User $user, Request $request, bool $force = false): bool
    {
        $lockKey     = "otp_send_lock_{$user->id}";
        $cooldownKey = "otp_cooldown_{$user->id}";

        // ใช้ Atomic Lock ป้องกัน Race Condition เมื่อมีการ Submit ฟอร์มพร้อมกัน
        return (bool) Cache::lock($lockKey, 10)->block(5, function () use ($user, $request, $cooldownKey, $force): bool {
            // หากไม่ได้เป็นการกดขอใหม่โดยตรง (force) และเพิ่งส่งไปภายใน 60 วินาที
            if (!$force && Cache::has($cooldownKey)) {
                $existing = DB::table('password_reset_otps')
                    ->where('email', $user->email)
                    ->where('expires_at', '>', now())
                    ->first();

                // มี OTP ที่ยังไม่หมดอายุและเพิ่งส่งไปแล้ว ให้ข้ามการส่งซ้ำ
                if ($existing) {
                    return true;
                }
            }

            // สุ่ม OTP ด้วย CSPRNG (Cryptographically Secure Pseudo-Random Number Generator)
            $otp = (string) random_int(100000, 999999);
            $ip  = (string) $request->ip();

            // ดึงพิกัดจาก IP โดยใช้ Cache 24 ชม. เพื่อลด Latency จาก API ภายนอก
            $location = Cache::remember("geoip_loc_{$ip}", 86400, function () use ($ip): string {
                if (in_array($ip, ['127.0.0.1', '::1'], true) || str_starts_with($ip, '192.168.')) {
                    return 'Local Network (LAN)';
                }

                try {
                    $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country");
                    if ($response->successful() && $response->json('status') === 'success') {
                        $data = $response->json();
                        return "{$data['city']}, {$data['regionName']}, {$data['country']}";
                    }
                } catch (\Throwable) {}

                return 'ไม่ทราบตำแหน่ง';
            });

            // บันทึก OTP ลงฐานข้อมูล
            DB::table('password_reset_otps')->updateOrInsert(
                ['email' => $user->email],
                [
                    'otp'        => $otp,
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // บันทึก Cooldown 60 วินาที
            Cache::put($cooldownKey, true, now()->addSeconds(60));

            // ส่งอีเมล OTP
            Mail::to($user->email)->send(new \App\Mail\LoginOtpMail(
                $otp,
                $user->full_name,
                $ip,
                $location
            ));

            return true;
        });
    }
}
