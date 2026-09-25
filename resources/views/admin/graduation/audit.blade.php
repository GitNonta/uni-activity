{{-- หน้าตรวจสอบการสำเร็จการศึกษา (Graduation Audit Report) --}}
@extends('layouts.admin')
@section('title', 'รายงานตรวจสอบการสำเร็จการศึกษา (Graduation Audit)')

@section('content')
<div class="mb-4">
    <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="font-bold flex items-center gap-2" style="font-size:1.35rem;color:#0f172a;">
                <span style="display:inline-flex;padding:0.4rem;background:#e0e7ff;border-radius:0.5rem;color:#4338ca;">
                    <svg style="width:1.35rem;height:1.35rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path>
                    </svg>
                </span>
                <span>ระบบ Transcript & ตรวจสอบการสำเร็จการศึกษา</span>
            </h1>
            <p class="text-xs text-muted mt-1">ตรวจสอบคุณสมบัติด้านกิจกรรมนักศึกษาเชื่อมโยงกับฝ่ายทะเบียน และพิมพ์ Official Activity Transcript</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.graduation.criteria.index') }}" class="btn btn-outline btn-sm flex items-center gap-1" title="แก้ไขเกณฑ์โครงสร้างชั่วโมงกิจกรรม">
                <svg style="width:0.95rem;height:0.95rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span>เกณฑ์ชั่วโมงกิจกรรม</span>
            </a>
            <a href="{{ route('admin.graduation.audit.export', request()->query()) }}" class="btn btn-outline btn-sm flex items-center gap-1" title="ส่งออก CSV สำหรับส่งต่อฝ่ายทะเบียน">
                <svg style="width:0.95rem;height:0.95rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <span>ส่งออก CSV (ฝ่ายทะเบียน)</span>
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

@if(session('error'))
<div style="background:#fef2f2;border-left:4px solid #ef4444;padding:0.9rem 1.2rem;border-radius:0.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;">
    <svg style="width:1.25rem;height:1.25rem;color:#dc2626;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <div style="font-size:0.875rem;color:#991b1b;font-weight:500;">{{ session('error') }}</div>
</div>
@endif

{{-- สถิติ Audit Metrics ภาพรวม --}}
<div class="grid-4 mb-4" style="gap:1rem;">
    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">นักศึกษาตามตัวกรอง (ปี {{ $report['filters']['year'] === 'all' ? 'ทุกชั้นปี' : $report['filters']['year'] }})</span>
            <span style="color:#4f46e5;padding:0.3rem;background:#eef2ff;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </span>
        </div>
        <p class="font-bold text-dark mt-2" style="font-size:1.4rem;">{{ $report['total_students'] }} <span style="font-size:0.8rem;font-weight:normal;color:#64748b;">คน</span></p>
    </div>

    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">ผ่านเกณฑ์กิจกรรมครบถ้วน</span>
            <span style="color:#059669;padding:0.3rem;background:#ecfdf5;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </span>
        </div>
        <p class="font-bold text-success mt-2" style="font-size:1.4rem;">{{ $report['eligible_count'] }} <span style="font-size:0.8rem;font-weight:normal;color:#059669;">({{ $report['eligible_percentage'] }}%)</span></p>
    </div>

    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">ยังไม่ผ่านเกณฑ์ (ต้องเร่งรัด)</span>
            <span style="color:#dc2626;padding:0.3rem;background:#fef2f2;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </span>
        </div>
        <p class="font-bold mt-2" style="font-size:1.4rem;color:#dc2626;">{{ $report['deficit_count'] }} <span style="font-size:0.8rem;font-weight:normal;color:#dc2626;">({{ $report['deficit_percentage'] }}%)</span></p>
    </div>

    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">ชั่วโมงกิจกรรมเฉลี่ย</span>
            <span style="color:#0284c7;padding:0.3rem;background:#f0f9ff;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </span>
        </div>
        <p class="font-bold text-dark mt-2" style="font-size:1.4rem;">{{ $report['average_hours'] }} <span style="font-size:0.8rem;font-weight:normal;color:#64748b;">ชม. / {{ $report['criteria']->min_total_hours ?? 100 }}</span></p>
    </div>
</div>

{{-- กล่องตัวกรอง (Filter Bar) --}}
<div class="card mb-4" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.25rem;">
    <form method="GET" action="{{ route('admin.graduation.audit.index') }}" class="flex items-center gap-3" style="flex-wrap:wrap;">
        {{-- 1. ชั้นปี --}}
        <div style="min-width:130px;">
            <label class="block text-xs font-semibold text-muted mb-1">ชั้นปี</label>
            <select name="year" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.6rem;border-radius:0.375rem;">
                <option value="4" {{ ($report['filters']['year'] == '4') ? 'selected' : '' }}>ปี 4 (ปีสุดท้าย)</option>
                <option value="3" {{ ($report['filters']['year'] == '3') ? 'selected' : '' }}>ปี 3</option>
                <option value="2" {{ ($report['filters']['year'] == '2') ? 'selected' : '' }}>ปี 2</option>
                <option value="1" {{ ($report['filters']['year'] == '1') ? 'selected' : '' }}>ปี 1</option>
                <option value="all" {{ ($report['filters']['year'] == 'all') ? 'selected' : '' }}>ทุกชั้นปี</option>
            </select>
        </div>

        {{-- 2. คณะ --}}
        <div style="min-width:160px;">
            <label class="block text-xs font-semibold text-muted mb-1">คณะ</label>
            <select name="faculty" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.6rem;border-radius:0.375rem;">
                <option value="">ทุกคณะ</option>
                @foreach($report['faculties'] as $fac)
                    <option value="{{ $fac }}" {{ ($report['filters']['faculty'] == $fac) ? 'selected' : '' }}>{{ $fac }}</option>
                @endforeach
            </select>
        </div>

        {{-- 3. สาขาวิชา --}}
        <div style="min-width:160px;">
            <label class="block text-xs font-semibold text-muted mb-1">สาขาวิชา</label>
            <select name="department" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.6rem;border-radius:0.375rem;">
                <option value="">ทุกสาขาวิชา</option>
                @foreach($report['departments'] as $dept)
                    <option value="{{ $dept }}" {{ ($report['filters']['department'] == $dept) ? 'selected' : '' }}>{{ $dept }}</option>
                @endforeach
            </select>
        </div>

        {{-- 4. สถานะ --}}
        <div style="min-width:140px;">
            <label class="block text-xs font-semibold text-muted mb-1">สถานะสำเร็จการศึกษา</label>
            <select name="status" class="form-control" style="font-size:0.85rem;padding:0.4rem 0.6rem;border-radius:0.375rem;">
                <option value="">ทั้งหมด</option>
                <option value="passed" {{ ($report['filters']['status'] == 'passed') ? 'selected' : '' }}>ผ่านเกณฑ์แล้ว</option>
                <option value="deficit" {{ ($report['filters']['status'] == 'deficit') ? 'selected' : '' }}>ยังไม่ผ่านเกณฑ์</option>
            </select>
        </div>

        {{-- 5. ค้นหา --}}
        <div style="flex:1;min-width:180px;">
            <label class="block text-xs font-semibold text-muted mb-1">ค้นหา (รหัส หรือ ชื่อ-สกุล)</label>
            <input type="text" name="search" class="form-control" placeholder="เช่น 6401001 หรือ สมชาย" value="{{ $report['filters']['search'] }}" style="font-size:0.85rem;padding:0.4rem 0.6rem;border-radius:0.375rem;">
        </div>

        <div style="margin-top:auto;display:flex;gap:0.5rem;">
            <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1" style="height:35px;">
                <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <span>กรอง</span>
            </button>
            <a href="{{ route('admin.graduation.audit.index') }}" class="btn btn-outline btn-sm flex items-center gap-1" style="height:35px;" title="ล้างตัวกรอง">
                <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </a>
        </div>
    </form>
</div>

{{-- ตารางแสดงผลรายชื่อนักศึกษาและการตรวจสอบ (Audit Student Table) --}}
<div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;">
    <div class="flex items-center justify-between mb-4 pb-3" style="border-bottom:1px solid #f1f5f9;">
        <div class="flex items-center gap-2">
            <span style="display:inline-flex;padding:0.35rem;background:#f1f5f9;border-radius:0.375rem;color:#475569;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
            </span>
            <h2 class="font-bold" style="font-size:1.1rem;color:#0f172a;margin:0;">รายชื่อนักศึกษาและผลการตรวจสอบชั่วโมงกิจกรรม</h2>
        </div>
        <span class="text-xs text-muted">พบ {{ $report['audited_students']->count() }} รายการ</span>
    </div>

    @if($report['audited_students']->isEmpty())
        <div class="text-center py-8 text-muted" style="font-size:0.875rem;">
            <svg style="width:2.5rem;height:2.5rem;margin:0 auto 0.75rem;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <p>ไม่พบข้อมูลนักศึกษาตามเงื่อนไขตัวกรองที่ระบุ</p>
        </div>
    @else
        <div style="overflow-x:auto;">
            <table class="responsive-table" style="width:100%;font-size:0.875rem;">
                <thead>
                    <tr style="border-bottom:1px solid #e2e8f0;text-align:left;">
                        <th style="padding:0.75rem;">รหัสนักศึกษา / ชื่อ</th>
                        <th style="padding:0.75rem;">คณะ / สาขาวิชา</th>
                        <th style="padding:0.75rem;" class="text-center">ชั่วโมงรวม</th>
                        <th style="padding:0.75rem;">โครงสร้างหมวดหมู่กิจกรรม</th>
                        <th style="padding:0.75rem;" class="text-center">สถานะการสำเร็จการศึกษา</th>
                        <th style="padding:0.75rem;" class="text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['audited_students'] as $audit)
                    @php $s = $audit['student']; @endphp
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.75rem;">
                            <div class="font-bold text-dark">{{ $s->student_id }}</div>
                            <div class="text-xs text-muted">{{ $s->full_name }}</div>
                            <span class="badge" style="background:#f1f5f9;color:#475569;font-size:0.65rem;margin-top:2px;">ปี {{ $s->year ?: '-' }}</span>
                        </td>
                        <td style="padding:0.75rem;">
                            <div class="text-xs font-semibold text-dark">{{ $s->faculty ?: '-' }}</div>
                            <div class="text-xs text-muted">{{ $s->department ?: '-' }}</div>
                        </td>
                        <td style="padding:0.75rem;" class="text-center">
                            <span class="font-bold {{ $audit['total_passed'] ? 'text-success' : 'text-danger' }}" style="font-size:1rem;">
                                {{ number_format($audit['total_hours'], 1) }}
                            </span>
                            <span class="text-xs text-muted">/ {{ number_format($audit['min_total_hours'], 0) }} ชม.</span>
                        </td>
                        <td style="padding:0.75rem;">
                            <div class="flex gap-2" style="flex-wrap:wrap;">
                                {{-- มหาวิทยาลัย --}}
                                @if(isset($audit['scope_audit']['university']))
                                    @php $uni = $audit['scope_audit']['university']; @endphp
                                    <span class="badge" style="background:{{ $uni['passed'] ? '#ecfdf5' : '#fee2e2' }};color:{{ $uni['passed'] ? '#059669' : '#dc2626' }};font-size:0.7rem;" title="{{ $uni['name'] }}: {{ $uni['earned'] }}/{{ $uni['required'] }} ชม.">
                                        มหาลัย: {{ number_format($uni['earned'], 0) }}/{{ number_format($uni['required'], 0) }}
                                    </span>
                                @endif

                                {{-- คณะ --}}
                                @if(isset($audit['scope_audit']['faculty']))
                                    @php $fac = $audit['scope_audit']['faculty']; @endphp
                                    <span class="badge" style="background:{{ $fac['passed'] ? '#ecfdf5' : '#fee2e2' }};color:{{ $fac['passed'] ? '#059669' : '#dc2626' }};font-size:0.7rem;" title="{{ $fac['name'] }}: {{ $fac['earned'] }}/{{ $fac['required'] }} ชม.">
                                        คณะ: {{ number_format($fac['earned'], 0) }}/{{ number_format($fac['required'], 0) }}
                                    </span>
                                @endif

                                {{-- จิตอาสา --}}
                                @foreach($audit['category_audit'] as $cat)
                                    @if(str_contains($cat['name'], 'จิตอาสา'))
                                        <span class="badge" style="background:{{ $cat['passed'] ? '#ecfdf5' : '#fee2e2' }};color:{{ $cat['passed'] ? '#059669' : '#dc2626' }};font-size:0.7rem;" title="{{ $cat['name'] }}: {{ $cat['earned'] }}/{{ $cat['required'] }} ชม.">
                                            จิตอาสา: {{ number_format($cat['earned'], 0) }}/{{ number_format($cat['required'], 0) }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>

                            @if(!$audit['is_eligible'] && !empty($audit['missing_requirements']))
                                <div class="text-xs mt-1" style="color:#b91c1c;max-width:280px;line-height:1.3;">
                                    <span class="font-medium">ขาด:</span> {{ implode(', ', $audit['missing_requirements']) }}
                                </div>
                            @endif
                        </td>
                        <td style="padding:0.75rem;" class="text-center">
                            @if($audit['is_eligible'])
                                <span class="badge" style="background:#ecfdf5;color:#059669;font-size:0.75rem;padding:0.35rem 0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:0.25rem;">
                                    <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span>ผ่านเกณฑ์กิจกรรม</span>
                                </span>
                            @else
                                <span class="badge" style="background:#fef2f2;color:#dc2626;font-size:0.75rem;padding:0.35rem 0.6rem;font-weight:600;display:inline-flex;align-items:center;gap:0.25rem;">
                                    <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>ยังไม่ผ่านเกณฑ์</span>
                                </span>
                            @endif
                        </td>
                        <td style="padding:0.75rem;" class="text-right">
                            <div class="flex justify-end gap-1" style="align-items:center;">
                                {{-- พิมพ์ Official Transcript --}}
                                <a href="{{ route('admin.graduation.audit.transcript', $s) }}" target="_blank" class="btn btn-outline btn-sm text-xs flex items-center gap-1" style="padding:0.25rem 0.5rem;" title="พิมพ์ Official Activity Transcript">
                                    <svg style="width:0.85rem;height:0.85rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    <span>Transcript</span>
                                </a>

                                {{-- ส่งแจ้งเตือนเร่งรัด (เฉพาะคนที่ยังไม่ผ่านเกณฑ์) --}}
                                @if(!$audit['is_eligible'])
                                <form action="{{ route('admin.graduation.audit.alert', $s) }}" method="POST" style="margin:0;display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-outline btn-sm text-xs flex items-center gap-1" style="padding:0.25rem 0.5rem;color:#d97706;border-color:#fde68a;" title="ส่งแจ้งเตือนเร่งรัดชั่วโมง (In-App + LINE)" onclick="return confirm('ส่งแจ้งเตือนเร่งรัดชั่วโมงกิจกรรมไปยัง {{ $s->full_name }} ({{ $s->student_id }}) ใช่หรือไม่?')">
                                        <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                        <span>เตือน</span>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
