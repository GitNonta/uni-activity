{{-- หน้าลืมรหัสผ่านสำหรับ Staff --}}
@extends('layouts.app')
@section('title', 'ลืมรหัสผ่าน')

@section('content')
<div class="container" style="max-width:400px;margin:2rem auto;">
    <div class="card">
        <div class="card-body" style="padding:2rem;">
            <div style="text-align:center;margin-bottom:1.5rem;">
                <h1 style="font-size:1.5rem;font-weight:700;color:#1e293b;margin-bottom:.5rem;">ลืมรหัสผ่าน</h1>
                <p style="color:#64748b;font-size:.9rem;">กรอกอีเมลเพื่อรับลิงก์รีเซ็ตรหัสผ่าน</p>
            </div>

            @if(session('status'))
                <div style="background:#dcfce7;color:#166534;padding:.75rem;border-radius:8px;margin-bottom:1rem;font-size:.9rem;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.email') }}">
                @csrf

                <div style="margin-bottom:1.5rem;">
                    <label for="email" class="form-label">อีเมล</label>
                    <input type="email" id="email" name="email" class="form-control" 
                        value="{{ old('email') }}" 
                        placeholder="staff@example.com" 
                        required autofocus>
                    @error('email')
                        <p style="color:#dc2626;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div style="margin-bottom:1rem;">
                    <button type="submit" class="btn btn-primary btn-block" style="padding:.75rem;">
                        ส่งลิงก์รีเซ็ตรหัสผ่าน
                    </button>
                </div>

                <div style="text-align:center;">
                    <a href="{{ route('admin.login') }}" style="color:#64748b;font-size:.9rem;">
                        &larr; กลับไปหน้าเข้าสู่ระบบ
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
