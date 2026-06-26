<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * คอนโทรลเลอร์สำหรับลืมรหัสผ่าน (Staff เท่านั้น)
 */
class ForgotPasswordController extends Controller
{
    /** แสดงฟอร์มกรอกอีเมลสำหรับลืมรหัสผ่าน */
    public function showLinkRequestForm()
    {
        if (auth()->check()) {
            return redirect('/');
        }
        return view('auth.forgot-password');
    }

    /** ส่งอีเมล reset link ไปยังผู้ใช้ */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // ตรวจสอบว่าอีเมลนี้เป็นเจ้าหน้าที่หรือผู้ดูแลระบบหรือไม่
        $user = User::where('email', $request->email)
            ->whereIn('role', ['staff', 'admin'])
            ->first();
        
        if (!$user) {
            // ไม่เปิดเผยว่าอีเมลไม่มีในระบบ (security)
            throw ValidationException::withMessages([
                'email' => 'ไม่พบอีเมลนี้ในระบบ หรืออีเมลนี้ไม่ใช่บัญชีเจ้าหน้าที่/ผู้ดูแลระบบ',
            ]);
        }

        // สร้าง reset token และส่งอีเมล (ใช้ broker 'staff')
        $status = Password::broker('staff')->sendResetLink(
            $request->only('email')
        );

        // ถ้าส่งสำเร็จ
        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', 'ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลเรียบร้อยแล้ว กรุณาตรวจสอบอีเมล');
        }

        // ถ้าส่งไม่สำเร็จ
        throw ValidationException::withMessages([
            'email' => 'ไม่สามารถส่งลิงก์รีเซ็ตรหัสผ่านได้ กรุณาลองใหม่',
        ]);
    }
}
