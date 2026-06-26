<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /** แสดงหน้ารีเซ็ตรหัสผ่าน */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * ขั้นตอนที่ 1: ตรวจสอบข้อมูลเบื้องต้นและส่ง OTP ยืนยันการเปลี่ยนรหัส
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // --- เพิ่มขั้นตอน OTP ก่อนเปลี่ยนรหัสจริง ---
        $otp = (string) rand(100000, 999999);
        $expiryMinutes = 10;

        // บันทึก OTP ลงฐานข้อมูล
        \Illuminate\Support\Facades\DB::table('password_reset_otps')->updateOrInsert(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes($expiryMinutes),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // เก็บข้อมูลรหัสผ่านใหม่ไว้ใน Session ชั่วคราว (Encrypted)
        session([
            'pending_password_reset' => [
                'email' => $request->email,
                'password' => $request->password,
                'token' => $request->token,
            ]
        ]);

        // ส่งอีเมล OTP
        try {
            \Illuminate\Support\Facades\Mail::to($request->email)->send(
                new \App\Mail\PasswordResetOtpMail($otp, $expiryMinutes)
            );
            
            return redirect()->route('admin.password.otp.show', ['email' => $request->email])
                ->with('status', 'กรุณากรอกรหัส OTP ที่ส่งไปยังอีเมลของคุณเพื่อยืนยันการเปลี่ยนรหัสผ่าน');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Final OTP Mail Error: ' . $e->getMessage());
            return back()->withErrors(['email' => 'ไม่สามารถส่งรหัสยืนยันไปยังอีเมลได้ กรุณาลองใหม่']);
        }
    }
}
