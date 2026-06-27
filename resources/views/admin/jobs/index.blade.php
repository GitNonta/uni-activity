{{-- Admin: รายการประกาศงานทั้งหมด --}}
@extends('layouts.admin')
@section('title', 'ประกาศงาน')

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.5rem;">
    <h1 class="font-bold flex items-center gap-2" style="font-size:1.25rem;">
        <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        จัดการประกาศงาน
    </h1>
    <a href="{{ route('admin.jobs.create') }}" class="btn btn-primary">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        สร้างประกาศใหม่
    </a>
</div>

{{-- Search + Filter --}}
<form method="GET" action="{{ route('admin.jobs.index') }}" class="flex gap-2 mb-4" style="flex-wrap:wrap;">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหาชื่องาน / ตำแหน่ง..." class="form-control flex-1" style="min-width:200px;">
    <select name="status" class="form-control" style="width:auto;">
        <option value="">ทุกสถานะ</option>
        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>เปิดรับสมัคร</option>
        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>ปิดรับสมัคร</option>
        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>เสร็จสิ้น</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">ค้นหา</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>หัวข้องาน</th>
                    <th>ประเภท</th>
                    <th>ตำแหน่ง</th>
                    <th>สถานะ</th>
                    <th>ผู้สมัคร</th>
                    <th>วันเริ่ม</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                <tr>
                    <td>{{ $job->id }}</td>
                    <td><a href="{{ route('admin.jobs.show', $job->id) }}" class="text-primary font-semi">{{ Str::limit($job->title, 40) }}</a></td>
                    <td>
                        <span class="badge {{ $job->job_type === 'parttime' ? 'job-badge-parttime' : 'job-badge-general' }}">
                            {{ $job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป' }}
                        </span>
                    </td>
                    <td>{{ $job->position }}</td>
                    <td>
                        @if($job->status === 'open')
                            <span class="badge badge-green">เปิด</span>
                        @elseif($job->status === 'closed')
                            <span class="badge badge-red">ปิด</span>
                        @else
                            <span class="badge badge-gray">เสร็จสิ้น</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-blue">{{ $job->applications_count }} คน</span>
                    </td>
                    <td>{{ $job->start_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.jobs.show', $job->id) }}" class="btn btn-outline btn-sm">ดู</a>
                            <a href="{{ route('admin.jobs.edit', $job->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                            <form method="POST" action="{{ route('admin.jobs.destroy', $job->id) }}" onsubmit="return confirm('ลบประกาศ &quot;{{ $job->title }}&quot; จริงหรือ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted" style="padding:2rem;">ยังไม่มีประกาศงาน</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $jobs->links() }}</div>
@endsection
