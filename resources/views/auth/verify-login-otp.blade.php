@extends('layouts.app')

@section('title', 'ยืนยันการเข้าสู่ระบบ')

@section('content')
<div class="auth-container" style="max-width: 400px; margin: 4rem auto; padding: 2rem; background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <div style="text-align: center; margin-bottom: 2rem;">
        <div style="width: 64px; height: 64px; background: #fff7ed; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
            <svg style="width: 32px; height: 32px; color: #ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
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

    <form method="POST" action="{{ route('login.otp.verify') }}" id="otp-form" onsubmit="handleOtpSubmit(this)">
        @csrf
        <div class="form-group" style="margin-bottom: 1.5rem;">
            <div class="otp-container" style="display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1rem;">
                @for ($i = 0; $i < 6; $i++)
                    <input type="text" name="otp_part[]" maxlength="1" pattern="[0-9]*" inputmode="numeric" required
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

        <button type="submit" id="verifySubmitBtn" class="btn btn-primary btn-block btn-lg" style="display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;">
            <span>เข้าสู่ระบบ</span>
        </button>
    </form>

    <form id="resend-form" method="POST" action="{{ route('login.otp.resend') }}" style="text-align: center; margin-top: 2rem;" onsubmit="handleResendSubmit(this)">
        @csrf
        <p style="font-size: 0.8125rem; color: #64748b;">
            หากไม่ได้รับรหัส? 
            <button type="submit" id="resendBtn" style="background: none; border: none; color: #ea580c; text-decoration: none; font-weight: 600; cursor: pointer; padding: 0; font-family: inherit;">
                ส่งใหม่อีกครั้ง
            </button>
            <span id="countdownText" style="display:none; color:#94a3b8; margin-left:4px;">(ใน <span id="secondsLeft">60</span>s)</span>
        </p>
    </form>

    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="{{ route('login') }}" style="font-size: 0.8125rem; color: #94a3b8; text-decoration: none;">← กลับไปหน้าเข้าสู่ระบบ</a>
    </div>
</div>

<script>
{
    const otpParts = document.querySelectorAll('input[name="otp_part[]"]');
    const hiddenOtp = document.getElementById('otp_combined');
    const otpForm = document.getElementById('otp-form');
    let isSubmitting = false;

    window.handleOtpSubmit = function(form) {
        if (isSubmitting) return false;
        isSubmitting = true;
        const btn = form.querySelector('#verifySubmitBtn');
        if (btn) {
            btn.disabled = true;
            btn.style.opacity = '0.75';
            btn.style.cursor = 'not-allowed';
            btn.innerHTML = '<svg style="width:16px;height:16px;animation:spin 1s linear infinite;display:inline-block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> กำลังตรวจสอบ...';
        }
        return true;
    };

    window.handleResendSubmit = function(form) {
        const resendBtn = document.getElementById('resendBtn');
        if (resendBtn && resendBtn.disabled) {
            return false;
        }
        if (resendBtn) {
            resendBtn.disabled = true;
            resendBtn.style.opacity = '0.5';
            resendBtn.style.cursor = 'not-allowed';
        }
        return true;
    };

    // Auto-focus first box
    if (otpParts.length > 0) {
        otpParts[0].focus();
    }

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
                if (hiddenOtp.value.length === 6 && !isSubmitting) {
                    if (otpForm.requestSubmit) {
                        otpForm.requestSubmit();
                    } else {
                        otpForm.submit();
                    }
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

            if (otpValue.length === 6 && !isSubmitting) {
                if (otpForm.requestSubmit) {
                    otpForm.requestSubmit();
                } else {
                    otpForm.submit();
                }
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && idx > 0) {
                otpParts[idx - 1].focus();
            }
        });
    });

    // 60-second cooldown timer for resend
    const resendBtn = document.getElementById('resendBtn');
    const countdownText = document.getElementById('countdownText');
    const secondsLeftSpan = document.getElementById('secondsLeft');

    let cooldownSeconds = 60;
    const cooldownKey = 'otp_resend_cooldown_expiry';
    const savedExpiry = sessionStorage.getItem(cooldownKey);
    const now = Date.now();

    if (!savedExpiry || now > parseInt(savedExpiry, 10)) {
        sessionStorage.setItem(cooldownKey, String(now + 60000));
    }

    function updateCooldown() {
        const expiry = parseInt(sessionStorage.getItem(cooldownKey) || '0', 10);
        const remaining = Math.max(0, Math.ceil((expiry - Date.now()) / 1000));

        if (remaining > 0) {
            if (resendBtn) {
                resendBtn.disabled = true;
                resendBtn.style.opacity = '0.5';
                resendBtn.style.cursor = 'not-allowed';
            }
            if (countdownText) countdownText.style.display = 'inline';
            if (secondsLeftSpan) secondsLeftSpan.textContent = remaining;
            setTimeout(updateCooldown, 1000);
        } else {
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.style.opacity = '1';
                resendBtn.style.cursor = 'pointer';
            }
            if (countdownText) countdownText.style.display = 'none';
        }
    }

    updateCooldown();
}
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endsection
