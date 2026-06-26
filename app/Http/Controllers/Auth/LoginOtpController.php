<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class LoginOtpController extends Controller
{
    /** แสดงหน้ากรอก OTP สำหรับ Login */
    public function showVerifyForm(Request $request)
    {
        if (!session()->has('login_otp_user_id')) {
            return redirect()->route('login');
        }

        $email = session('login_otp_email');
        return view('auth.verify-login-otp', compact('email'));
    }

    /** ส่ง OTP ใหม่ */
    public function resend(Request $request)
    {
        if (!session()->has('login_otp_user_id')) {
            return response()->json(['success' => false], 403);
        }

        $user = User::find(session('login_otp_user_id'));
        if ($user) {
            $this->sendOtp($user, $request);
            return back()->with('status', 'ส่งรหัส OTP ใหม่เรียบร้อยแล้ว');
        }

        return back()->withErrors(['otp' => 'เกิดข้อผิดพลาด']);
    }

    /** ยืนยัน OTP และ Log in */
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $userId = session('login_otp_user_id');
        $email = session('login_otp_email');

        if (!$userId || !$email) {
            return redirect()->route('login');
        }

        $otpRecord = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            throw ValidationException::withMessages([
                'otp' => 'รหัส OTP ไม่ถูกต้องหรือหมดอายุแล้ว',
            ]);
        }

        // ลบ OTP
        DB::table('password_reset_otps')->where('id', $otpRecord->id)->delete();

        // ล็อกอิน
        $user = User::find($userId);
        $remember = session('login_otp_remember', false);
        
        Auth::login($user, $remember);

        // ล้าง session ชั่วคราว
        session()->forget(['login_otp_user_id', 'login_otp_email', 'login_otp_remember']);

        if ($user->isAdmin() || $user->isStaff()) {
            return redirect()->intended(route('admin.dashboard'));
        }
        return redirect()->intended(route('activities.index'));
    }

    /** ฟังก์ชันช่วยส่ง OTP */
    public function sendOtp(User $user, Request $request)
    {
        $otp = (string) rand(100000, 999999);
        $ip = $request->ip();
        
        // ดึงพิกัดจาก IP (ใช้ API ภายนอกฟรี)
        $location = 'ไม่ทราบตำแหน่ง';
        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country");
            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json();
                $location = "{$data['city']}, {$data['regionName']}, {$data['country']}";
            }
        } catch (\Exception $e) {}

        // บันทึก OTP
        DB::table('password_reset_otps')->updateOrInsert(
            ['email' => $user->email],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // ส่งเมล
        Mail::to($user->email)->send(new \App\Mail\LoginOtpMail(
            $otp, 
            $user->full_name, 
            $ip, 
            $location
        ));
    }
}
