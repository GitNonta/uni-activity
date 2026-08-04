@extends('layouts.admin')
@section('title', 'API Keys & ความเป็นส่วนตัว')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="font-bold flex items-center gap-3" style="font-size:1.5rem; color:#1e293b;">
            <svg style="width:28px; height:28px; color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            จัดการ API Keys & ความเป็นส่วนตัว
        </h1>
        <p class="text-sm text-muted mt-1">สร้างและลบกุญแจสำหรับการเข้าถึง API ของระบบ เพื่อเชื่อมต่อกับแอปพลิเคชันภายนอกอย่างปลอดภัย</p>
    </div>
</div>

@include('admin.api-keys.content')
@endsection
