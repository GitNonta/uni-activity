@extends('layouts.app')

@section('title', 'ยืนยันการเข้าสู่ระบบ')

@section('content')
<div class="auth-container" style="max-width: 400px; margin: 4rem auto; padding: 2rem; background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="width: 64px; height: 64px; background: #eef2ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
            <svg style="width: 32px; height: 32px; color: #4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        </div>
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">ยืนยันรหัส OTP</h1>
        <p style="color: #64748b; font-size: 0.875rem; margin-top: 0.5rem;">
            ป้อนรหัส 6 หลักที่ส่งไปยัง <br>
            <strong>{{ $email }}</strong>
        </p>
    </div>

    @if (session('status'))
        <div style="background: #f0fdf4; color: #166534; padding: 0.75rem; border-radius: 8px; font-size: 0.875rem; margin-bottom: 1.5rem; text-align: center;">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.otp.verify') }}" id="otp-form">
        @csrf
        <div class="form-group" style="margin-bottom: 1.5rem;">
            <div class="otp-container" style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1rem;">
                @for ($i = 0; $i < 6; $i++)
                    <input type="text" name="otp_part[]" maxlength="1" pattern="[0-9]" required
                        class="otp-box form-control @error('otp') is-invalid @enderror"
                        style="width: 2.5rem; height: 2.5rem; text-align: center; font-size: 1.5rem; border: 1px solid #e2e8f0; border-radius: 8px;" />
                @endfor
            </div>
            <input type="hidden" name="otp" id="otp_combined" />
            @error('otp')
                <span style="color: #ef4444; font-size: 0.75rem; margin-top: 0.5rem; display: block; text-align: center;">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; background: #4f46e5; color: #fff; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 12px rgba(79,70,229,0.3);">
            เข้าสู่ระบบ
        </button>
    </form>

    <form id="resend-form" method="POST" action="{{ route('login.otp.resend') }}" style="text-align: center; margin-top: 2rem;">
        @csrf
        <p style="font-size: 0.8125rem; color: #64748b;">
            หากไม่ได้รับรหัส? 
            <button type="submit" style="background: none; border: none; color: #4f46e5; text-decoration: none; font-weight: 600; cursor: pointer; padding: 0; font-family: inherit;">ส่งใหม่อีกครั้ง</button>
        </p>
    </form>

    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="{{ route('login') }}" style="font-size: 0.8125rem; color: #94a3b8; text-decoration: none;">← กลับไปหน้าเข้าสู่ระบบ</a>
    </div>
</div>

<script>
    const otpParts = document.querySelectorAll('input[name="otp_part[]"]');
    const hiddenOtp = document.getElementById('otp_combined');

    otpParts.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
            // Auto‑move to next field when a digit is entered
            if (e.target.value.length === 1 && idx < otpParts.length - 1) {
                otpParts[idx + 1].focus();
            }
            // Update the hidden combined OTP value
            const otpValue = Array.from(otpParts).map(i => i.value).join('');
            hiddenOtp.value = otpValue;
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && idx > 0) {
                otpParts[idx - 1].focus();
            }
        });
    });
</script>
@endsection
