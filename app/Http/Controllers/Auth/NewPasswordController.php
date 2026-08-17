<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /** แสดงหน้ารีเซ็ตรหัสผ่าน */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * ขั้นตอนที่ 1: ตรวจสอบข้อมูลเบื้องต้นและส่ง OTP ยืนยันการเปลี่ยนรหัส พร้อมระบบ Lock / Deduplication
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email       = strtolower((string) $request->email);
        $emailHash   = md5($email);
        $lockKey     = "password_reset_otp_lock_{$emailHash}";
        $cooldownKey = "password_reset_otp_cooldown_{$emailHash}";

        Cache::lock($lockKey, 10)->block(5, function () use ($request, $email, $cooldownKey): void {
            // หากเพิ่งส่งไปภายใน 60 วินาที และมี OTP เดิมอยู่ ให้ข้ามการส่งซ้ำ
            if (Cache::has($cooldownKey)) {
                $existing = DB::table('password_reset_otps')
                    ->where('email', $email)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($existing) {
                    return;
                }
            }

            $otp = (string) random_int(100000, 999999);
            $expiryMinutes = 10;

            // บันทึก OTP ลงฐานข้อมูล
            DB::table('password_reset_otps')->updateOrInsert(
                ['email' => $email],
                [
                    'otp'        => $otp,
                    'expires_at' => now()->addMinutes($expiryMinutes),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // บันทึก Cooldown 60 วินาที
            Cache::put($cooldownKey, true, now()->addSeconds(60));

            // ส่งอีเมล OTP
            try {
                Mail::to($email)->send(
                    new \App\Mail\PasswordResetOtpMail($otp, $expiryMinutes)
                );
            } catch (\Throwable $e) {
                Log::error('Password Reset OTP Mail Error: ' . $e->getMessage());
            }
        });

        // เก็บข้อมูลรหัสผ่านใหม่ไว้ใน Session ชั่วคราว
        session([
            'pending_password_reset' => [
                'email'    => $request->email,
                'password' => $request->password,
                'token'    => $request->token,
            ]
        ]);

        return redirect()->route('admin.password.otp.show', ['email' => $request->email])
            ->with('status', 'กรุณากรอกรหัส OTP ที่ส่งไปยังอีเมลของคุณเพื่อยืนยันการเปลี่ยนรหัสผ่าน');
    }
}
