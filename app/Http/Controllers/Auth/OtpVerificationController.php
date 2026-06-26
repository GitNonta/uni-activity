<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtpVerificationController extends Controller
{
    /** แสดงหน้ากรอก OTP */
    public function showVerifyForm(Request $request)
    {
        $email = $request->query('email');
        if (!$email) {
            return redirect()->route('admin.password.request');
        }
        return view('auth.verify-otp', compact('email'));
    }

    /** ตรวจสอบ OTP และดำเนินการเปลี่ยนรหัสผ่านจริง */
    public function verify(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $request->email)
            ->first();

        if (!$otpRecord) {
            throw ValidationException::withMessages([
                'otp' => 'ไม่พบคำขอรีเซ็ตรหัสผ่านสำหรับอีเมลนี้ (' . $request->email . ')',
            ]);
        }

        if ($otpRecord->otp !== $request->otp) {
            throw ValidationException::withMessages([
                'otp' => 'รหัส OTP ไม่ถูกต้อง',
            ]);
        }

        if (\Carbon\Carbon::parse($otpRecord->expires_at)->isPast()) {
            throw ValidationException::withMessages([
                'otp' => 'รหัส OTP หมดอายุแล้ว (เมื่อ ' . $otpRecord->expires_at . ' เวลาปัจจุบัน ' . now() . ')',
            ]);
        }

        // ดึงข้อมูลการเปลี่ยนรหัสที่ค้างไว้ใน Session
        $resetData = session('pending_password_reset');
        
        if (!$resetData || $resetData['email'] !== $request->email) {
            return redirect()->route('admin.password.request')
                ->withErrors(['email' => 'เซสชันหมดอายุหรือข้อมูลไม่ถูกต้อง กรุณาเริ่มขั้นตอนใหม่']);
        }

        // --- ดำเนินการเปลี่ยนรหัสผ่านด้วยระบบมาตรฐานของ Laravel ---
        $status = \Illuminate\Support\Facades\Password::broker('staff')->reset(
            [
                'email' => $resetData['email'],
                'password' => $resetData['password'],
                'password_confirmation' => $resetData['password'], // ยืนยันซ้ำจาก session
                'token' => $resetData['token'],
            ],
            function (\App\Models\User $user, string $password) {
                $user->forceFill([
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'remember_token' => \Illuminate\Support\Str::random(60),
                ])->save();

                event(new \Illuminate\Auth\Events\PasswordReset($user));
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
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
