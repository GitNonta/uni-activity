<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * คอนโทรลเลอร์การยืนยันตัวตนเจ้าหน้าที่ (Admin)
 * จัดการเข้าสู่ระบบและออกจากระบบด้วย email + password
 */
class StaffAuthController extends Controller
{
    /** แสดงหน้าเข้าสู่ระบบเจ้าหน้าที่ */
    public function showLogin()
    {
        if (auth()->check()) {
            return redirect('/');
        }
        return view('auth.staff-login');
    }

    /**
     * ดำเนินการเข้าสู่ระบบเจ้าหน้าที่
     * ตรวจสอบ email + password → ส่ง OTP → ไปหน้ายืนยัน
     */
    public function login(Request $request, LoginOtpController $otpController)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // ค้นหาเจ้าหน้าที่จาก email ที่ยังเปิดใช้งานอยู่ (ทั้ง staff และ admin)
        $user = User::where('email', $request->email)
                    ->whereIn('role', ['staff', 'admin'])
                    ->where('is_active', true)
                    ->first();

        // ตรวจสอบ email และ password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'])->withInput();
        }

        // --- เพิ่มระบบ OTP ก่อน Login ---
        session([
            'login_otp_user_id' => $user->id,
            'login_otp_email'   => $user->email,
            'login_otp_remember'=> $request->boolean('remember'),
        ]);

        $otpController->sendOtp($user, $request);

        return redirect()->route('login.otp.show');
    }

    /** ออกจากระบบเจ้าหน้าที่ → ลบ session → กลับหน้า admin login */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
