<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * คอนโทรลเลอร์การยืนยันตัวตนนักศึกษา
 * จัดการเข้าสู่ระบบ, ลงทะเบียนบัญชี, ออกจากระบบ ด้วยรหัสนักศึกษา
 */
class StudentAuthController extends Controller
{
    /** แสดงหน้าเข้าสู่ระบบนักศึกษา */
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    /**
     * ดำเนินการเข้าสู่ระบบนักศึกษา
     * ตรวจสอบรหัสนักศึกษา → ส่ง OTP → ไปหน้ายืนยัน
     */
    public function login(Request $request, LoginOtpController $otpController): RedirectResponse
    {
        $request->validate(['student_id' => 'required|string']);

        // ค้นหานักศึกษาจากรหัสที่ยังเปิดใช้งานอยู่
        $user = User::where('student_id', $request->student_id)
                    ->where('is_active', true)
                    ->first();

        if (!$user) {
            return back()->withErrors(['student_id' => 'รหัสนักศึกษาไม่ถูกต้อง'])->withInput();
        }

        // ป้องกันเจ้าหน้าที่เข้าทางช่องนักศึกษา
        if ($user->isStaff()) {
            return back()->withErrors(['student_id' => 'ผู้จัดกิจกรรมกรุณาเข้าสู่ระบบทางหน้าผู้ดูแล'])->withInput();
        }

        // โหมด OTP สำหรับบัญชีที่ระบุใน AUTH_OTP_BYPASS_IDS (.env) — ไม่มี ID ฝังในโค้ด
        $bypassIds = config('auth.otp_bypass_ids', []);
        if (!empty($bypassIds) && (
            in_array($user->student_id, $bypassIds, true) ||
            in_array($user->email, $bypassIds, true)
        )) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            return redirect()->intended(route('activities.index'));
        }

        // กำหนดข้อมูล Session ชั่วคราวสำหรับยืนยัน OTP
        session([
            'login_otp_user_id' => $user->id,
            'login_otp_email'   => $user->email,
            'login_otp_remember'=> $request->boolean('remember'),
        ]);

        $otpController->sendOtp($user, $request);

        return redirect()->route('login.otp.show');
    }

    /** แสดงหน้าลงทะเบียนบัญชีนักศึกษาใหม่ */
    public function showRegister(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect('/');
        }
        return view('auth.register');
    }

    /**
     * ลงทะเบียนบัญชีนักศึกษาใหม่
     * ตรวจสอบข้อมูล → สร้างผู้ใช้ → ล็อกอินอัตโนมัติ
     */
    public function register(Request $request): RedirectResponse
    {
        $request->validate([
            'student_id' => 'required|string|max:20|unique:users,student_id',
            'full_name'  => 'required|string|max:255',
            'faculty'    => 'required|string|max:100',
            'department' => 'required|string|max:100',
            'year'       => 'required|integer|min:1|max:6',
            'program'    => 'required|string|in:ปกติ,กศ.บป.',
        ], [
            'student_id.required' => 'กรุณากรอกรหัสนักศึกษา',
            'student_id.unique'   => 'รหัสนักศึกษานี้ถูกใช้งานแล้ว',
            'full_name.required'  => 'กรุณากรอกชื่อ-นามสกุล',
            'faculty.required'    => 'กรุณาเลือกคณะ',
            'department.required' => 'กรุณากรอกสาขาวิชา',
            'year.required'       => 'กรุณาเลือกชั้นปี',
            'program.required'    => 'กรุณาเลือกภาคเรียน',
        ]);

        $prefix = (string) \App\Models\Setting::get('student_email_prefix', 's');
        $domain = (string) \App\Models\Setting::get('student_email_domain', '@pkru.ac.th');

        $user = User::create([
            'student_id' => $request->student_id,
            'email'      => $prefix . $request->student_id . $domain,
            'full_name'  => $request->full_name,
            'faculty'    => $request->faculty,
            'department' => $request->department,
            'year'       => $request->year,
            'program'    => $request->program,
            'role'       => 'student',
        ]);

        Auth::login($user);

        return redirect()->route('activities.index');
    }

    /** ออกจากระบบนักศึกษา → ลบ session → กลับหน้า login */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
