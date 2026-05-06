<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * คอนโทรลเลอร์การยืนยันตัวตนนักศึกษา
 * จัดการเข้าสู่ระบบ, ลงทะเบียนบัญชี, ออกจากระบบ ด้วยรหัสนักศึกษา
 */
class StudentAuthController extends Controller
{
    /** แสดงหน้าเข้าสู่ระบบนักศึกษา */
    public function showLogin()
    {
        if (auth()->check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    /**
     * ดำเนินการเข้าสู่ระบบนักศึกษา
     * ตรวจสอบรหัสนักศึกษา → ตรวจบทบาท → ล็อกอิน
     */
    public function login(Request $request)
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

        Auth::login($user, $request->boolean('remember'));

        return redirect()->intended(route('activities.index'));
    }

    /** แสดงหน้าลงทะเบียนบัญชีนักศึกษาใหม่ */
    public function showRegister()
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
    public function register(Request $request)
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

        $user = User::create([
            'student_id' => $request->student_id,
            'email'      => 's' . $request->student_id . '@pkru.ac.th',
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
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
