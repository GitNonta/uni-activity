@extends('layouts.admin')
@section('title', 'ตั้งค่าระบบ')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="font-bold" style="font-size:1.5rem;">ตั้งค่าระบบ (System Settings)</h1>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4" style="background:#dcfce7;color:#166534;padding:1rem;border-radius:8px;border:1px solid #bbf7d0;">
        {{ session('success') }}
    </div>
@endif

<div class="card p-6" style="max-width: 600px;">
    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <h2 class="font-bold mb-4" style="font-size: 1.1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">รูปแบบอีเมลนักศึกษาอัตโนมัติ</h2>
        <p class="text-sm text-gray-500 mb-6">
            เมื่อนักศึกษาเข้าสู่ระบบครั้งแรกผ่าน SSO ระบบจะสร้างบัญชีโดยใช้อีเมลตามรูปแบบนี้
        </p>

        <div class="mb-4">
            <label class="block text-sm font-bold text-gray-700 mb-2">คำนำหน้าอีเมล (Prefix)</label>
            <input type="text" name="student_email_prefix" value="{{ old('student_email_prefix', $settings['student_email_prefix']) }}" class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="เช่น s (เว้นว่างได้)">
            @error('student_email_prefix')
                <p class="text-red-500 text-xs mt-1" style="color: #ef4444;">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-500 mt-1">อักษรหรือข้อความที่จะนำหน้ารหัสนักศึกษา (เช่น 's'6710886217)</p>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-bold text-gray-700 mb-2">โดเมนอีเมล (Domain)</label>
            <input type="text" name="student_email_domain" value="{{ old('student_email_domain', $settings['student_email_domain']) }}" class="form-control" style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px;" placeholder="เช่น @pkru.ac.th" required>
            @error('student_email_domain')
                <p class="text-red-500 text-xs mt-1" style="color: #ef4444;">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-500 mt-1">โดเมนของสถาบันที่ลงท้ายด้วย @ (เช่น @pkru.ac.th)</p>
        </div>

        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6" style="background: #f8fafc; border: 1px dashed #cbd5e1;">
            <p class="text-sm text-gray-600 font-bold mb-1">ตัวอย่างอีเมลที่ได้:</p>
            <code class="text-indigo-600 bg-white px-2 py-1 rounded" id="email-preview" style="color: #4f46e5;"></code>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary" style="background:#4f46e5;color:white;padding:0.6rem 1.2rem;border-radius:6px;font-weight:600;border:none;">
                บันทึกการตั้งค่า
            </button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const prefixInput = document.querySelector('input[name="student_email_prefix"]');
        const domainInput = document.querySelector('input[name="student_email_domain"]');
        const preview = document.getElementById('email-preview');

        function updatePreview() {
            const prefix = prefixInput.value.trim();
            const domain = domainInput.value.trim();
            preview.textContent = `${prefix}6710886217${domain}`;
        }

        prefixInput.addEventListener('input', updatePreview);
        domainInput.addEventListener('input', updatePreview);
        
        // Initial preview
        updatePreview();
    });
</script>
@endsection
