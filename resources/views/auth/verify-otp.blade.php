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

    <form method="POST" action="{{ route('admin.password.otp.verify') }}">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label for="otp" style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem; color: #475569;">รหัส OTP 6 หลัก</label>
            <input type="text" name="otp" id="otp" 
                class="form-control @error('otp') is-invalid @enderror" 
                placeholder="000000" 
                required maxlength="6" autofocus
                style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1.25rem; text-align: center; letter-spacing: 0.5rem;">
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
