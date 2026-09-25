{{-- หน้าตั้งค่าเกณฑ์ชั่วโมงกิจกรรมตามโครงสร้างการสำเร็จการศึกษา (Admin) --}}
@extends('layouts.admin')
@section('title', 'กำหนดเกณฑ์ชั่วโมงกิจกรรมการสำเร็จการศึกษา')

@section('content')
<div class="mb-4">
    <div class="flex items-center gap-2 text-sm text-muted mb-2">
        <a href="{{ route('admin.graduation.audit.index') }}" class="text-primary hover:underline">รายงานตรวจสอบการสำเร็จการศึกษา</a>
        <span>/</span>
        <span class="text-dark font-medium">ตั้งค่าเกณฑ์ชั่วโมงกิจกรรม</span>
    </div>

    <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="font-bold flex items-center gap-2" style="font-size:1.35rem;color:#0f172a;">
                <span style="display:inline-flex;padding:0.4rem;background:#e0e7ff;border-radius:0.5rem;color:#4338ca;">
                    <svg style="width:1.35rem;height:1.35rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </span>
                <span>กำหนดเกณฑ์ชั่วโมงกิจกรรมตามโครงสร้างหลักสูตร</span>
            </h1>
            <p class="text-xs text-muted mt-1">กำหนดสัดส่วนชั่วโมงบังคับมหาวิทยาลัย, กิจกรรมคณะ, และกิจกรรมจิตอาสาเพื่อตรวจสอบเงื่อนไขการจบการศึกษา</p>
        </div>
        <div>
            <a href="{{ route('admin.graduation.audit.index') }}" class="btn btn-outline btn-sm flex items-center gap-1">
                <svg style="width:0.9rem;height:0.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>กลับหน้ารายงาน Audit</span>
            </a>
        </div>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border-left:4px solid #10b981;padding:0.9rem 1.2rem;border-radius:0.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;">
    <svg style="width:1.25rem;height:1.25rem;color:#059669;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <div style="font-size:0.875rem;color:#065f46;font-weight:500;">{{ session('success') }}</div>
</div>
@endif

@if($errors->any())
<div style="background:#fef2f2;border-left:4px solid #ef4444;padding:0.9rem 1.2rem;border-radius:0.5rem;margin-bottom:1.5rem;">
    <ul class="text-xs" style="color:#b91c1c;margin:0;padding-left:1.25rem;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;max-width:800px;">
    <form action="{{ route('admin.graduation.criteria.update') }}" method="POST">
        @csrf
        @method('PUT')

        {{-- ชื่อเกณฑ์ --}}
        <div class="mb-4">
            <label class="block text-xs font-semibold text-dark mb-1">ชื่อเกณฑ์การสำเร็จการศึกษา <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $criteria->name ?? 'เกณฑ์กิจกรรมมาตรฐานมหาวิทยาลัย (ปริญญาตรี)') }}" required style="width:100%;border-radius:0.5rem;font-size:0.875rem;">
        </div>

        {{-- ชั่วโมงรวม --}}
        <div class="grid-2 mb-4" style="gap:1rem;">
            <div>
                <label class="block text-xs font-semibold text-dark mb-1">ชั่วโมงสะสมรวมขั้นต่ำ (ชม.) <span class="text-danger">*</span></label>
                <input type="number" step="0.5" name="min_total_hours" class="form-control" value="{{ old('min_total_hours', $criteria->min_total_hours ?? 100) }}" required min="1" max="500" style="width:100%;border-radius:0.5rem;font-size:0.875rem;">
                <span class="text-xs text-muted">เกณฑ์มาตรฐาน: 100 ชั่วโมง</span>
            </div>
            <div>
                <label class="block text-xs font-semibold text-dark mb-1">ชั่วโมงกิจกรรมภาคบังคับเฉพาะ (ชม.)</label>
                <input type="number" step="0.5" name="min_mandatory_hours" class="form-control" value="{{ old('min_mandatory_hours', $criteria->min_mandatory_hours ?? 10) }}" required min="0" max="200" style="width:100%;border-radius:0.5rem;font-size:0.875rem;">
                <span class="text-xs text-muted">กิจกรรมที่กำหนดให้เป็น Mandatory</span>
            </div>
        </div>

        <hr style="border:none;border-top:1px solid #f1f5f9;margin:1.5rem 0;">

        <h3 class="font-bold text-sm text-dark mb-3 flex items-center gap-1">
            <svg style="width:1rem;height:1rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            <span>โครงสร้างหมวดหมู่กิจกรรมบังคับ (Curriculum Requirements)</span>
        </h3>

        <div class="grid-3 mb-4" style="gap:1rem;">
            {{-- 1. บังคับมหาวิทยาลัย --}}
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;">
                <label class="block text-xs font-semibold text-dark mb-1">1. กิจกรรมระดับมหาวิทยาลัย (ส่วนกลาง)</label>
                <div class="flex items-center gap-1 mt-2">
                    <input type="number" step="0.5" name="scope_university" class="form-control" value="{{ old('scope_university', $criteria->scope_requirements['university'] ?? 40) }}" required min="0" max="300" style="border-radius:0.375rem;font-size:0.875rem;">
                    <span class="text-xs text-muted">ชม.</span>
                </div>
                <span class="text-xs text-muted mt-1 block">เกณฑ์มาตรฐาน: 40 ชม.</span>
            </div>

            {{-- 2. กิจกรรมคณะ --}}
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;">
                <label class="block text-xs font-semibold text-dark mb-1">2. กิจกรรมระดับคณะ / สาขาวิชา</label>
                <div class="flex items-center gap-1 mt-2">
                    <input type="number" step="0.5" name="scope_faculty" class="form-control" value="{{ old('scope_faculty', $criteria->scope_requirements['faculty'] ?? 40) }}" required min="0" max="300" style="border-radius:0.375rem;font-size:0.875rem;">
                    <span class="text-xs text-muted">ชม.</span>
                </div>
                <span class="text-xs text-muted mt-1 block">เกณฑ์มาตรฐาน: 40 ชม.</span>
            </div>

            {{-- 3. จิตอาสา --}}
            @php
                $volHours = 20.0;
                if (!empty($criteria->category_requirements)) {
                    foreach ($criteria->category_requirements as $k => $v) {
                        $volHours = (float) $v;
                        break;
                    }
                }
            @endphp
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;padding:1rem;">
                <label class="block text-xs font-semibold text-dark mb-1">3. กิจกรรมจิตอาสา / บำเพ็ญประโยชน์</label>
                <div class="flex items-center gap-1 mt-2">
                    <input type="number" step="0.5" name="category_volunteer" class="form-control" value="{{ old('category_volunteer', $volHours) }}" required min="0" max="200" style="border-radius:0.375rem;font-size:0.875rem;">
                    <span class="text-xs text-muted">ชม.</span>
                </div>
                <span class="text-xs text-muted mt-1 block">เกณฑ์มาตรฐาน: 20 ชม.</span>
            </div>
        </div>

        {{-- คำอธิบายเกณฑ์ --}}
        <div class="mb-4">
            <label class="block text-xs font-semibold text-dark mb-1">คำอธิบายและข้อบังคับเพิ่มเติม</label>
            <textarea name="description" rows="3" class="form-control" style="width:100%;border-radius:0.5rem;font-size:0.875rem;">{{ old('description', $criteria->description) }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.25rem;font-size:0.875rem;">
            บันทึกการปรับปรุงเกณฑ์
        </button>
    </form>
</div>
@endsection
