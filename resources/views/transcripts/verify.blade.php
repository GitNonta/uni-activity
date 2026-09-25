@extends('layouts.app')
@section('title', 'ตรวจสอบความถูกต้อง Official Activity Transcript')

@section('content')
<div style="max-width:680px;margin:2rem auto;padding:0 1rem;">
    <div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:1rem;padding:2rem;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
        @if($isValid && $student)
            <div class="text-center mb-4">
                <div style="width:4rem;height:4rem;border-radius:50%;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <svg style="width:2.2rem;height:2.2rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h1 class="font-bold text-dark" style="font-size:1.4rem;">เอกสารใบรับรองกิจกรรมถูกต้องตามระเบียบ</h1>
                <p class="text-xs text-muted mt-1">Official Activity Transcript ออกโดย มหาวิทยาลัยเวทย์มนต์และเทคโนโลยีดิจิทัล</p>
                <div class="mt-2">
                    <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:0.8rem;padding:0.35rem 0.75rem;">Document Ref: {{ $code }}</span>
                </div>
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem;">
                <div class="grid-2" style="gap:0.75rem;font-size:0.875rem;">
                    <div>
                        <span class="text-muted text-xs block">รหัสนักศึกษา:</span>
                        <span class="font-bold text-dark">{{ $student->student_id }}</span>
                    </div>
                    <div>
                        <span class="text-muted text-xs block">ชื่อ-นามสกุล:</span>
                        <span class="font-bold text-dark">{{ $student->full_name }}</span>
                    </div>
                    <div>
                        <span class="text-muted text-xs block">คณะ:</span>
                        <span>{{ $student->faculty ?: '-' }}</span>
                    </div>
                    <div>
                        <span class="text-muted text-xs block">สาขาวิชา:</span>
                        <span>{{ $student->department ?: '-' }}</span>
                    </div>
                </div>
            </div>

            @if($audit)
                <div style="border-top:1px solid #f1f5f9;padding-top:1.25rem;margin-bottom:1.5rem;">
                    <h3 class="font-bold text-sm text-dark mb-2">สถานะการสำเร็จการศึกษาด้านกิจกรรม:</h3>
                    @if($audit['is_eligible'])
                        <div style="background:#ecfdf5;border:1px solid #10b981;border-radius:0.5rem;padding:0.75rem 1rem;color:#065f46;font-size:0.875rem;" class="flex items-center gap-2">
                            <svg style="width:1.2rem;height:1.2rem;color:#059669;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span class="font-bold">ผ่านเกณฑ์กิจกรรมการสำเร็จการศึกษาครบถ้วน (สะสมได้ {{ number_format($audit['total_hours'], 1) }} / {{ number_format($audit['min_total_hours'], 1) }} ชม.)</span>
                        </div>
                    @else
                        <div style="background:#fef2f2;border:1px solid #ef4444;border-radius:0.5rem;padding:0.75rem 1rem;color:#991b1b;font-size:0.875rem;" class="flex items-center gap-2">
                            <svg style="width:1.2rem;height:1.2rem;color:#dc2626;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            <span>อยู่ระหว่างการสะสมชั่วโมง (สะสมแล้ว {{ number_format($audit['total_hours'], 1) }} ชม.)</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="text-center text-xs text-muted">
                ตรวจสอบผ่านระบบจัดเก็บและรับรองระเบียนกิจกรรมนักศึกษาดิจิทัล
            </div>
        @else
            <div class="text-center py-6">
                <div style="width:4rem;height:4rem;border-radius:50%;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <svg style="width:2.2rem;height:2.2rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
                <h1 class="font-bold text-dark" style="font-size:1.3rem;">ไม่พบข้อมูลเอกสารในระบบ</h1>
                <p class="text-xs text-muted mt-2">รหัสอ้างอิงเอกสาร <strong>{{ $code }}</strong> อาจไม่ถูกต้องหรือยังไม่ได้รับการออกเอกสารอย่างเป็นทางการ</p>
                <div class="mt-4">
                    <a href="{{ route('home') }}" class="btn btn-primary btn-sm">กลับหน้าหลัก</a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
