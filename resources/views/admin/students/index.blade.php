{{-- หน้ารายชื่อนักศึกษาทั้งหมด (Admin): ค้นหา + กรอง + active badges + progress bar + quick export --}}
@extends('layouts.admin')
@section('title', 'จัดการนักศึกษา')

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.75rem;">
    <div>
        <h1 class="font-bold" style="font-size:1.4rem;">จัดการนักศึกษา</h1>
        <p class="text-sm text-muted">นักศึกษาทั้งหมด <strong>{{ $students->total() }}</strong> คน
            @if(request()->anyFilled(['search','faculty','department','year','program','completion']))
                <span style="color:#6366f1;font-weight:600;">(กรองแล้ว)</span>
            @endif
        </p>
    </div>
    {{-- Quick Export Button --}}
    <form method="POST" action="{{ route('admin.exports.students') }}" style="display:inline;">
        @csrf
        <input type="hidden" name="faculty"    value="{{ request('faculty') }}">
        <input type="hidden" name="year"       value="{{ request('year') }}">
        <input type="hidden" name="program"    value="{{ request('program') }}">
        <input type="hidden" name="status"     value="">
        <input type="hidden" name="completion" value="{{ request('completion') }}">
        <button type="submit" class="btn btn-outline btn-sm" style="color:#16a34a;border-color:#86efac;">
            <svg style="width:14px;height:14px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Export Excel
        </button>
    </form>
</div>

{{-- Active Filter Badges --}}
@php
    $activeFilters = [];
    if(request('search'))     $activeFilters['search']     = ['label' => 'ค้นหา: '.request('search'), 'key' => 'search'];
    if(request('faculty'))    $activeFilters['faculty']    = ['label' => 'คณะ: '.request('faculty'),   'key' => 'faculty'];
    if(request('department')) $activeFilters['department'] = ['label' => 'สาขา: '.request('department'),'key' => 'department'];
    if(request('year'))       $activeFilters['year']       = ['label' => 'ปี '.request('year'),         'key' => 'year'];
    if(request('program'))    $activeFilters['program']    = ['label' => request('program'),            'key' => 'program'];
    if(request('completion')) $activeFilters['completion'] = ['label' => request('completion') === 'complete' ? 'ครบเกณฑ์' : 'ยังไม่ครบ', 'key' => 'completion'];
@endphp
@if(count($activeFilters) > 0)
<div class="flex" style="gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
    @foreach($activeFilters as $filter)
    <a href="{{ request()->fullUrlWithoutQuery([$filter['key']]) }}"
       style="display:inline-flex;align-items:center;gap:4px;background:#ede9fe;color:#6d28d9;border-radius:999px;padding:4px 12px;font-size:.78rem;font-weight:600;text-decoration:none;">
        {{ $filter['label'] }}
        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
    </a>
    @endforeach
    <a href="{{ route('admin.students.index') }}" style="display:inline-flex;align-items:center;gap:4px;background:#f1f5f9;color:#64748b;border-radius:999px;padding:4px 12px;font-size:.78rem;text-decoration:none;">
        ล้างทั้งหมด ×
    </a>
</div>
@endif

{{-- ฟิลเตอร์ --}}
<form method="GET" action="{{ route('admin.students.index') }}" class="card mb-4">
    <div class="card-body" style="padding:.875rem 1rem;">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:180px;">
                <label class="form-label" style="font-size:.8rem;">ค้นหา</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="ชื่อ, รหัสนักศึกษา, คณะ, สาขา...">
            </div>
            <div style="min-width:140px;">
                <label class="form-label" style="font-size:.8rem;">คณะ</label>
                <select name="faculty" class="form-control">
                    <option value="">ทุกคณะ</option>
                    @foreach($faculties as $f)
                        <option value="{{ $f }}" {{ request('faculty') == $f ? 'selected' : '' }}>{{ $f }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:150px;">
                <label class="form-label" style="font-size:.8rem;">สาขา</label>
                <select name="department" class="form-control">
                    <option value="">ทุกสาขา</option>
                    @foreach($departments as $d)
                        <option value="{{ $d }}" {{ request('department') == $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:100px;">
                <label class="form-label" style="font-size:.8rem;">ชั้นปี</label>
                <select name="year" class="form-control">
                    <option value="">ทุกปี</option>
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>ปี {{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:130px;">
                <label class="form-label" style="font-size:.8rem;">ภาคเรียน</label>
                <select name="program" class="form-control">
                    <option value="">ทุกภาคเรียน</option>
                    @foreach($programs as $p)
                        <option value="{{ $p }}" {{ request('program') == $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:130px;">
                <label class="form-label" style="font-size:.8rem;">ชั่วโมง</label>
                <select name="completion" class="form-control">
                    <option value="">ทุกสถานะ</option>
                    <option value="complete"   {{ request('completion') === 'complete'   ? 'selected' : '' }}>ครบเกณฑ์ ✅</option>
                    <option value="incomplete" {{ request('completion') === 'incomplete' ? 'selected' : '' }}>ยังไม่ครบ ⏳</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">ค้นหา</button>
            @if(request()->anyFilled(['search','faculty','department','year','program','completion']))
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline">ล้าง</a>
            @endif
        </div>
    </div>
</form>

{{-- ตารางนักศึกษา --}}
<div class="card">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>นักศึกษา</th>
                    <th>คณะ / สาขา / ปี</th>
                    <th style="text-align:center;min-width:140px;">ชั่วโมง</th>
                    <th style="text-align:center;">กิจกรรม</th>
                    <th style="text-align:right;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                @php
                    $hrs = (float)($hoursMap[$student->id] ?? 0);
                    $pct = $totalRequired > 0 ? min(100, ($hrs / $totalRequired) * 100) : 0;
                    $barColor = $hrs >= $totalRequired ? '#16a34a' : ($hrs >= $totalRequired * 0.5 ? '#f59e0b' : '#6366f1');
                @endphp
                <tr>
                    <td>
                        <div class="font-semi text-sm">{{ $student->full_name }}</div>
                        <div class="text-xs text-muted">{{ $student->student_id }}</div>
                    </td>
                    <td>
                        <div class="text-sm">{{ $student->faculty ?? '-' }}</div>
                        <div class="text-xs text-muted">{{ $student->department ?? '-' }}</div>
                        <div style="margin-top:2px;">
                            @if($student->year)
                            <span style="font-size:.7rem;background:#f1f5f9;color:#475569;border-radius:4px;padding:1px 6px;">ปี {{ $student->year }}</span>
                            @endif
                            @if($student->program)
                            <span style="font-size:.7rem;border-radius:4px;padding:1px 6px;
                                {{ $student->program === 'กศ.บป.' ? 'background:#ede9fe;color:#7c3aed;' : 'background:#dbeafe;color:#1d4ed8;' }}">
                                {{ $student->program }}
                            </span>
                            @endif
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <div style="font-weight:700;font-size:1rem;color:{{ $barColor }};">
                            {{ number_format($hrs, 1) }} <span class="text-xs text-muted" style="font-weight:400;">ชม.</span>
                        </div>
                        {{-- Mini Progress Bar --}}
                        <div style="background:#e2e8f0;border-radius:999px;height:5px;width:90px;margin:.25rem auto;">
                            <div style="background:{{ $barColor }};border-radius:999px;height:5px;width:{{ $pct }}%;transition:width .4s;"></div>
                        </div>
                        <div style="font-size:.7rem;color:{{ $hrs >= $totalRequired ? '#16a34a' : '#94a3b8' }};margin-top:1px;">
                            {{ $hrs >= $totalRequired ? 'ครบเกณฑ์ ✅' : number_format($pct, 0).'%' }}
                        </div>
                    </td>
                    <td style="text-align:center;" class="text-sm">{{ $student->approved_count }}</td>
                    <td style="text-align:right;">
                        <a href="{{ route('admin.students.show', $student->id) }}" class="btn btn-outline btn-sm">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            จัดการ
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align:center;padding:2.5rem;color:#94a3b8;">ไม่พบนักศึกษา</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $students->links() }}</div>
@endsection
