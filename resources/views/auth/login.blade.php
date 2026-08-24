{{-- หน้าเข้าสู่ระบบนักศึกษา: ใช้รหัสนักศึกษาเข้าสู่ระบบ --}}
@extends('layouts.app')
@section('title', 'เข้าสู่ระบบ')

@section('content')
<div class="container-sm" style="padding-top:4rem;">
    <div class="text-center mb-4">
        <h1 class="font-bold" style="font-size:1.5rem;">เข้าสู่ระบบ</h1>
        <p class="text-muted text-sm mt-1">ใช้รหัสนักศึกษาเพื่อเข้าสู่ระบบ</p>
    </div>

    {{-- ฟอร์มกรอกรหัสนักศึกษา --}}
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('login') }}" id="loginForm" onsubmit="handleLoginSubmit(this)">
                @csrf
                <div class="form-group">
                    <label for="student_id" class="form-label">รหัสนักศึกษา</label>
                    <input id="student_id" type="text" name="student_id" value="{{ old('student_id') }}"
                        class="form-control" style="text-align:center;letter-spacing:2px;"
                        placeholder="6XXXXXXXXX" required autofocus>
                    @error('student_id')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> จดจำฉันไว้
                    </label>
                </div>
                <button type="submit" id="submitBtn" class="btn btn-primary btn-block btn-lg" style="display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;">
                    <span>เข้าสู่ระบบ</span>
                </button>
            </form>
        </div>
    </div>

    <p class="text-center text-sm text-muted mt-4">
        ยังไม่มีบัญชี? <a href="{{ route('register') }}" class="font-semi">สมัครสมาชิก</a>
    </p>
    <p class="text-center text-sm text-muted mt-2">
        <a href="{{ route('admin.login') }}">เข้าสู่ระบบสำหรับผู้จัดกิจกรรม</a>
    </p>
</div>

<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.login-submit-spinner {
    width: 18px !important;
    height: 18px !important;
    min-width: 18px !important;
    min-height: 18px !important;
    max-width: 18px !important;
    max-height: 18px !important;
    display: inline-block !important;
    flex-shrink: 0 !important;
    animation: spin 1s linear infinite !important;
}
</style>

<script>
function handleLoginSubmit(form) {
    const btn = form.querySelector('#submitBtn');
    if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.style.opacity = '0.75';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = '<svg style="width:18px;height:18px;min-width:18px;min-height:18px;max-width:18px;max-height:18px;animation:spin 1s linear infinite;display:inline-block;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> <span>กำลังส่งรหัส OTP...</span>';
    }
}
</script>
@endsection
