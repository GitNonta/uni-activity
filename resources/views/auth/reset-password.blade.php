{{-- หน้ารีเซ็ตรหัสผ่านสำหรับ Staff --}}
@extends('layouts.app')
@section('title', 'รีเซ็ตรหัสผ่าน')

@section('content')
<div class="container" style="max-width:400px;margin:2rem auto;">
    <div class="card">
        <div class="card-body" style="padding:2rem;">
            <div style="text-align:center;margin-bottom:1.5rem;">
                <h1 style="font-size:1.5rem;font-weight:700;color:#1e293b;margin-bottom:.5rem;">รีเซ็ตรหัสผ่าน</h1>
                <p style="color:#64748b;font-size:.9rem;">ตั้งรหัสผ่านใหม่สำหรับบัญชีของคุณ</p>
            </div>

            @if(session('status'))
                <div style="background:#dcfce7;color:#166534;padding:.75rem;border-radius:8px;margin-bottom:1rem;font-size:.9rem;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $request->token }}">

                <div style="margin-bottom:1.5rem;">
                    <label for="email" class="form-label">อีเมล</label>
                    <input type="email" id="email" name="email" class="form-control" 
                        value="{{ $request->email ?? old('email') }}" 
                        placeholder="staff@example.com" 
                        readonly
                        style="background:#f8fafc;">
                    @error('email')
                        <p style="color:#dc2626;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label for="password" class="form-label">รหัสผ่านใหม่</label>
                    <input type="password" id="password" name="password" class="form-control" 
                        placeholder="••••••••" 
                        required>
                    @error('password')
                        <p style="color:#dc2626;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div style="margin-bottom:1.5rem;">
                    <label for="password_confirmation" class="form-label">ยืนยันรหัสผ่าน</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" 
                        placeholder="••••••••" 
                        required>
                    @error('password_confirmation')
                        <p style="color:#dc2626;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div style="margin-bottom:1rem;">
                    <button type="submit" class="btn btn-primary btn-block" style="padding:.75rem;">
                        รีเซ็ตรหัสผ่าน
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
