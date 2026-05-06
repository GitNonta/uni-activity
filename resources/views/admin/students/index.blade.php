{{-- หน้ารายชื่อนักศึกษาทั้งหมด (Admin): ค้นหา + กรอง + สรุปชั่วโมง --}}
@extends('layouts.admin')
@section('title', 'จัดการนักศึกษา')

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.75rem;">
    <h1 class="font-bold" style="font-size:1.4rem;">จัดการนักศึกษา</h1>
    <span class="text-sm text-muted">นักศึกษาทั้งหมด {{ $students->total() }} คน</span>
</div>

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
            <button type="submit" class="btn btn-primary">ค้นหา</button>
            @if(request()->anyFilled(['search','faculty','year']))
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
                    <th>คณะ / สาขา</th>
                    <th style="text-align:center;">ชั่วโมงรวม</th>
                    <th style="text-align:center;">กิจกรรม</th>
                    <th style="text-align:right;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                <tr>
                    <td>
                        <div class="font-semi text-sm">{{ $student->full_name }}</div>
                        <div class="text-xs text-muted">{{ $student->student_id }}</div>
                    </td>
                    <td>
                        <div class="text-sm">{{ $student->faculty ?? '-' }}</div>
                        <div class="text-xs text-muted">{{ $student->department ?? '-' }}</div>
                    </td>
                    <td class="text-sm">
                        @if($student->year) <div>ปี {{ $student->year }}</div> @endif
                        @if($student->program) <div class="text-xs {{ $student->program === 'กศ.บป.' ? 'badge-purple' : 'badge-blue' }}" style="display:inline-block;padding:2px 6px;border-radius:4px;margin-top:2px;">{{ $student->program }}</div> @endif
                    </td>
                    <td style="text-align:center;">
                        @php $hrs = $hoursMap[$student->id] ?? 0; @endphp
                        <span class="font-bold" style="color:{{ $hrs >= 36 ? '#16a34a' : ($hrs >= 18 ? '#d97706' : '#1e40af') }};">
                            {{ number_format($hrs, 1) }}
                        </span>
                        <span class="text-xs text-muted"> ชม.</span>
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
                    <td colspan="6" style="text-align:center;padding:2.5rem;color:#94a3b8;">ไม่พบนักศึกษา</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $students->links() }}</div>
@endsection
