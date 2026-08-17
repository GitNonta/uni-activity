{{-- หน้าเข้าสู่ระบบผู้จัดกิจกรรม (staff): ใช้อีเมล + รหัสผ่าน --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบผู้จัดกิจกรรม</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container-sm" style="padding-top:6rem;">
        <div class="text-center mb-4">
            <svg class="icon-xl" style="margin:0 auto 1rem;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <h1 class="font-bold" style="font-size:1.5rem;">ผู้จัดกิจกรรม</h1>
            <p class="text-muted text-sm mt-1">เข้าสู่ระบบด้วยอีเมลและรหัสผ่าน</p>
        </div>

        {{-- ฟอร์มกรอกอีเมลและรหัสผ่าน --}}
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.login') }}" id="staffLoginForm" onsubmit="handleStaffLoginSubmit(this)">
                    @csrf
                    <div class="form-group">
                        <label for="email" class="form-label">อีเมล</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            class="form-control" placeholder="admin@example.com" required autofocus>
                        @error('email')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">รหัสผ่าน</label>
                        <input id="password" type="password" name="password"
                            class="form-control" required>
                        @error('password')<p class="form-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember"> จดจำฉันไว้
                        </label>
                    </div>
                    <button type="submit" id="staffSubmitBtn" class="btn btn-primary btn-block btn-lg" style="display:inline-flex;align-items:center;justify-content:center;gap:0.5rem;">
                        <span>เข้าสู่ระบบ</span>
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-sm text-muted mt-4">
            <a href="{{ route('admin.password.request') }}" style="color:#64748b;">ลืมรหัสผ่าน?</a>
        </p>
        <p class="text-center text-sm text-muted">
            <a href="{{ route('login') }}">กลับหน้าเข้าสู่ระบบนักศึกษา</a>
        </p>
    </div>

    <script>
    function handleStaffLoginSubmit(form) {
        const btn = form.querySelector('#staffSubmitBtn');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.style.opacity = '0.75';
            btn.style.cursor = 'not-allowed';
            btn.innerHTML = '<svg style="width:16px;height:16px;animation:spin 1s linear infinite;display:inline-block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> กำลังส่งรหัส OTP...';
        }
    }
    </script>
</body>
</html>
