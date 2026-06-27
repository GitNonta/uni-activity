{{-- Admin: รายละเอียดงาน + จัดการผู้สมัคร + คำถาม --}}
@extends('layouts.admin')
@section('title', $job->title)

@section('content')
<a href="{{ route('admin.jobs.index') }}" class="text-sm text-primary mb-2" style="display:inline-block;">&larr; กลับรายการ</a>

<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.5rem;">
    <h1 class="font-bold" style="font-size:1.25rem;">{{ $job->title }}</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.jobs.edit', $job->id) }}" class="btn btn-outline btn-sm flex items-center gap-1">
            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            แก้ไข
        </a>
        <a href="{{ route('admin.jobs.export-applicants', [$job->id, 'format' => 'csv']) }}" class="btn btn-outline btn-sm flex items-center gap-1"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> CSV</a>
        <a href="{{ route('admin.jobs.export-applicants', [$job->id, 'format' => 'xlsx']) }}" class="btn btn-outline btn-sm flex items-center gap-1"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Excel</a>
    </div>
</div>

{{-- ข้อมูลสรุป --}}
<div class="grid-4 mb-4">
    <div class="card stat-card">
        <div class="stat-label">สถานะ</div>
        <div>
            @if($job->status === 'open')
                <span class="badge badge-green flex items-center gap-1" style="font-size:.85rem;"><svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg> เปิดรับสมัคร</span>
            @elseif($job->status === 'closed')
                <span class="badge badge-red flex items-center gap-1" style="font-size:.85rem;"><svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg> ปิดรับสมัคร</span>
            @else
                <span class="badge badge-gray flex items-center gap-1" style="font-size:.85rem;"><svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg> เสร็จสิ้น</span>
            @endif
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">รอพิจารณา</div>
        <div class="stat-value" style="color:#eab308;">{{ $pendingCount }}</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">ยืนยันแล้ว</div>
        <div class="stat-value success">{{ $confirmedCount }} / {{ $job->quota }}</div>
    </div>
    <div class="card stat-card">
        <div class="stat-label">ไม่ผ่าน</div>
        <div class="stat-value" style="color:#dc2626;">{{ $rejectedCount }}</div>
    </div>
</div>

{{-- เปลี่ยนสถานะ --}}
<div class="card mb-4">
    <div class="card-body flex items-center gap-2" style="flex-wrap:wrap;">
        <span class="font-semi text-sm">เปลี่ยนสถานะ:</span>
        @foreach(['open' => 'เปิดรับสมัคร', 'closed' => 'ปิดรับสมัคร', 'completed' => 'เสร็จสิ้น'] as $s => $label)
            @if($job->status !== $s)
            <form method="POST" action="{{ route('admin.jobs.update-status', $job->id) }}" style="display:inline;">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $s }}">
                <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('เปลี่ยนเป็น {{ $label }}?')">{{ $label }}</button>
            </form>
            @endif
        @endforeach
    </div>
</div>

{{-- ข้อมูลงาน --}}
<div class="card mb-4">
    <div class="card-header flex items-center gap-2">
        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        รายละเอียดงาน
    </div>
    <div class="card-body">
        <div class="grid-2" style="font-size:.875rem;">
            <div><span class="text-muted">ประเภท:</span> <span class="badge {{ $job->job_type === 'parttime' ? 'job-badge-parttime' : 'job-badge-general' }}">{{ $job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป' }}</span></div>
            <div><span class="text-muted">ตำแหน่ง:</span> <strong>{{ $job->position }}</strong></div>
            <div><span class="text-muted">สถานที่:</span> {{ $job->location }}</div>
            <div><span class="text-muted">ค่าตอบแทน:</span> {{ $job->compensation ?? '-' }}</div>
            <div><span class="text-muted">ช่วงเวลา:</span> {{ $job->work_period ?? '-' }}</div>
            <div><span class="text-muted">การแต่งกาย:</span> {{ $job->dresscode ?? '-' }}</div>
            <div><span class="text-muted">เพศ:</span> {{ $job->gender === 'any' ? 'ไม่จำกัด' : ($job->gender === 'male' ? 'ชาย' : 'หญิง') }}</div>
            <div><span class="text-muted">ผู้สร้าง:</span> {{ $job->creator->full_name ?? '-' }}</div>
            <div><span class="text-muted">วันเริ่มงาน:</span> {{ $job->start_date->format('d/m/Y') }}</div>
            <div><span class="text-muted">วันสิ้นสุด:</span> {{ $job->end_date?->format('d/m/Y') ?? '-' }}</div>
        </div>
        @if($job->description)
            <hr class="divider">
            <p class="text-sm">{{ $job->description }}</p>
        @endif
        @if($job->note)
            <div class="alert alert-info text-sm mt-2" style="background:#fef3c7;color:#92400e;border-color:#fde68a;"><strong>หมายเหตุ:</strong> {{ $job->note }}</div>
        @endif
    </div>
</div>

{{-- ตารางผู้สมัคร --}}
<div class="card mb-4">
    <div class="card-header flex items-center gap-2">
        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        ผู้สมัคร ({{ $job->applications->count() }})
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ชื่อ-สกุล</th>
                    <th>รหัสนักศึกษา</th>
                    <th>คณะ</th>
                    <th>สถานะ</th>
                    <th>วันสมัคร</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($job->applications as $i => $app)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="font-semi">{{ $app->user->full_name ?? '-' }}</td>
                    <td>{{ $app->user->student_id ?? '-' }}</td>
                    <td>{{ $app->user->faculty ?? '-' }}</td>
                    <td>
                        @if($app->status === 'pending')
                            <span class="badge badge-yellow">รอพิจารณา</span>
                        @elseif($app->status === 'confirmed')
                            <span class="badge badge-green">ยืนยันแล้ว</span>
                        @else
                            <span class="badge badge-red">ไม่ผ่าน</span>
                        @endif
                    </td>
                    <td class="text-sm">{{ $app->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($app->status === 'pending')
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('admin.jobs.update-applicant', [$job->id, $app->id]) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn btn-success btn-sm flex items-center gap-1">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    ยืนยัน
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.jobs.update-applicant', [$job->id, $app->id]) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="rejected">
                                <button type="submit" class="btn btn-danger btn-sm flex items-center gap-1">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    ปฏิเสธ
                                </button>
                            </form>
                        </div>
                        @else
                        <span class="text-xs text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted" style="padding:1.5rem;">ยังไม่มีผู้สมัคร</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- คอมเมนต์ --}}
<div class="card">
    <div class="card-header flex items-center gap-2">
        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        คอมเมนต์ ({{ $job->comments->count() }})
    </div>
    <div class="card-body">
        @forelse($job->comments as $comment)
        <div style="padding:.5rem 0;border-bottom:1px solid #f1f5f9;">
            <div class="flex items-center gap-2 mb-1">
                <span class="font-semi text-sm">{{ $comment->user->full_name ?? 'ผู้ใช้' }}</span>
                <span class="text-xs text-muted">{{ $comment->created_at->diffForHumans() }}</span>
                <form method="POST" action="{{ route('admin.jobs.admin-comment-delete', $comment->id) }}" style="margin-left:auto;">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-danger" style="background:none;border:none;cursor:pointer;" onclick="return confirm('ลบคอมเมนต์?')">ลบ</button>
                </form>
            </div>
            <p class="text-sm">{{ $comment->body }}</p>
        </div>
        @empty
        <p class="text-sm text-muted">ยังไม่มีคอมเมนต์</p>
        @endforelse
    </div>
</div>
@endsection
