<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    /** แสดงหน้ากรอก OTP */
    public function showVerifyForm(Request $request): View|RedirectResponse
    {
        $email = (string) $request->query('email');
        if (!$email) {
            return redirect()->route('admin.password.request');
        }
        return view('auth.verify-otp', compact('email'));
    }

    /** ตรวจสอบ OTP และดำเนินการเปลี่ยนรหัสผ่านจริง พร้อมระบบป้องกัน Brute-force */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $email       = (string) $request->email;
        $emailHash   = md5(strtolower($email));
        $lockoutKey  = "pwd_otp_locked_{$emailHash}";
        $attemptsKey = "pwd_otp_failed_attempts_{$emailHash}";

        // 1. ตรวจสอบการถูกระงับชั่วคราว
        if (\Illuminate\Support\Facades\Cache::has($lockoutKey)) {
            throw ValidationException::withMessages([
                'otp' => 'การยืนยันรหัส OTP ถูกระงับชั่วคราวเนื่องจากกรอกผิดเกินกำหนด กรุณารอ 15 นาที',
            ]);
        }

        // 2. ดึงข้อมูล OTP ที่ยังไม่หมดอายุ
        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $email)
            ->first();

        if (!$otpRecord) {
            throw ValidationException::withMessages([
                'otp' => 'ไม่พบคำขอรีเซ็ตรหัสผ่านสำหรับอีเมลนี้ (' . $email . ')',
            ]);
        }

        if (Carbon::parse($otpRecord->expires_at)->isPast()) {
            throw ValidationException::withMessages([
                'otp' => 'รหัส OTP หมดอายุแล้ว กรุณาขอรหัสใหม่',
            ]);
        }

        // 3. ตรวจสอบความถูกต้องด้วย hash_equals ป้องกัน Timing Attack
        if (!hash_equals((string) $otpRecord->otp, (string) $request->otp)) {
            $failedAttempts = (int) \Illuminate\Support\Facades\Cache::increment($attemptsKey);
            \Illuminate\Support\Facades\Cache::put($attemptsKey, $failedAttempts, now()->addMinutes(15));

            if ($failedAttempts >= 5) {
                DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();
                \Illuminate\Support\Facades\Cache::put($lockoutKey, true, now()->addMinutes(15));
                \Illuminate\Support\Facades\Cache::forget($attemptsKey);

                throw ValidationException::withMessages([
                    'otp' => 'คุณกรอกรหัส OTP ผิดเกิน 5 ครั้ง รหัสถูกยกเลิกแล้วเพื่อความปลอดภัย กรุณารอ 15 นาทีหรือขอรหัสใหม่',
                ]);
            }

            $remaining = 5 - $failedAttempts;
            throw ValidationException::withMessages([
                'otp' => "รหัส OTP ไม่ถูกต้อง (เหลือโอกาสอีก {$remaining} ครั้ง)",
            ]);
        }

        // 4. สำเร็จ -> ล้าง Failed Attempts
        \Illuminate\Support\Facades\Cache::forget($attemptsKey);
        \Illuminate\Support\Facades\Cache::forget($lockoutKey);

        // ดึงข้อมูลการเปลี่ยนรหัสที่ค้างไว้ใน Session
        $resetData = session('pending_password_reset');
        
        if (!$resetData || $resetData['email'] !== $request->email) {
            return redirect()->route('admin.password.request')
                ->withErrors(['email' => 'เซสชันหมดอายุหรือข้อมูลไม่ถูกต้อง กรุณาเริ่มขั้นตอนใหม่']);
        }

        // --- ดำเนินการเปลี่ยนรหัสผ่านด้วยระบบมาตรฐานของ Laravel ---
        $status = Password::broker('staff')->reset(
            [
                'email'                 => $resetData['email'],
                'password'              => $resetData['password'],
                'password_confirmation' => $resetData['password'], // ยืนยันซ้ำจาก session
                'token'                 => $resetData['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // ล้างข้อมูลหลังสำเร็จ
            DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();
            session()->forget('pending_password_reset');

            return redirect()->route('admin.login')->with('status', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        }

        // กรณี Token หมดอายุหรือผิดพลาด
        return redirect()->route('admin.password.request')
            ->withErrors(['email' => __($status)]);
    }
}
