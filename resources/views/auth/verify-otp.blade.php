@extends('layouts.app')

@section('title', 'ยืนยัน OTP')

@section('content')
<div class="auth-container" style="max-width: 400px; margin: 4rem auto; padding: 2rem; background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">ยืนยันรหัส OTP</h1>
        <p style="color: #64748b; font-size: 0.875rem; margin-top: 0.5rem;">
            เราได้ส่งรหัสยืนยัน 6 หลักไปที่ <br>
            <strong>{{ $email }}</strong>
        </p>
    </div>

    <form method="POST" action="{{ route('admin.password.otp.verify') }}" id="otpForm">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label for="otp" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #475569;">รหัส OTP 6 หลัก</label>
            <div class="otp-container" style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1rem;">
                @for ($i = 0; $i < 6; $i++)
                    <input type="text" name="otp_part[]" maxlength="1" pattern="[0-9]*" inputmode="numeric" required
                        class="otp-box form-control @error('otp') is-invalid @enderror"
                        style="width: 2.5rem; height: 2.5rem; text-align: center; font-size: 1.5rem; border: 1px solid #e2e8f0; border-radius: 8px;" />
                @endfor
            </div>
            <input type="hidden" name="otp" id="otp_combined" />
            <script>
                const otpParts = document.querySelectorAll('input[name="otp_part[]"]');
                const hiddenOtp = document.getElementById('otp_combined');
                const otpForm = document.getElementById('otpForm');

                otpParts.forEach((input, idx) => {
                    input.addEventListener('paste', (e) => {
                        e.preventDefault();
                        const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                        if (pastedData) {
                            for (let i = 0; i < pastedData.length; i++) {
                                if (otpParts[i]) {
                                    otpParts[i].value = pastedData[i];
                                }
                            }
                            const lastIdx = Math.min(pastedData.length, 5);
                            otpParts[lastIdx].focus();
                            
                            hiddenOtp.value = Array.from(otpParts).map(i => i.value).join('');
                            if (hiddenOtp.value.length === 6) {
                                otpForm.submit();
                            }
                        }
                    });

                    input.addEventListener('input', (e) => {
                        e.target.value = e.target.value.replace(/[^0-9]/g, '');
                        // Auto‑move to next field when a digit is entered
                        if (e.target.value.length === 1 && idx < otpParts.length - 1) {
                            otpParts[idx + 1].focus();
                        }
                        // Update the hidden combined OTP value
                        const otpValue = Array.from(otpParts).map(i => i.value).join('');
                        hiddenOtp.value = otpValue;

                        if (otpValue.length === 6) {
                            otpForm.submit();
                        }
                    });

                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace' && e.target.value === '' && idx > 0) {
                            otpParts[idx - 1].focus();
                        }
                    });
                });
            </script>
            @error('otp')
                <span class="invalid-feedback" role="alert" style="color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;">
            ยืนยันรหัส
        </button>
    </form>

    <div style="text-align: center; margin-top: 2rem;">
        <p style="font-size: 0.8125rem; color: #64748b;">
            ไม่ได้รับรหัส? 
            <a href="{{ route('admin.password.request') }}" style="color: #4f46e5; text-decoration: none; font-weight: 500;">ส่งใหม่อีกครั้ง</a>
        </p>
    </div>
</div>
@endsection
