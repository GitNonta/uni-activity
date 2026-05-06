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
            <form method="POST" action="{{ route('login') }}">
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
                <button type="submit" class="btn btn-primary btn-block btn-lg">เข้าสู่ระบบ</button>
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
@endsection
