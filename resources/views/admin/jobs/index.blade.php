{{-- Admin: รายการประกาศงานทั้งหมด --}}
@extends('layouts.admin')
@section('title', __('ประกาศงาน'))

@section('content')
<div class="flex items-center justify-between mb-4" style="flex-wrap:wrap;gap:.5rem;">
    <h1 class="font-bold flex items-center gap-2" style="font-size:1.25rem;">
        <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>{{ __('จัดการประกาศงาน') }}    </h1>
    <a href="{{ route('admin.jobs.create') }}" class="btn btn-primary">
        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>{{ __('สร้างประกาศใหม่') }}    </a>
</div>

{{-- Search + Filter --}}
<form method="GET" action="{{ route('admin.jobs.index') }}" class="flex gap-2 mb-4" style="flex-wrap:wrap;">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('ค้นหาชื่องาน / ตำแหน่ง...') }}" class="form-control flex-1" style="min-width:200px;">
    <select name="status" class="form-control" style="width:auto;">
        <option value="">{{ __('ทุกสถานะ') }}</option>
        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>{{ __('เปิดรับสมัคร') }}</option>
        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('ปิดรับสมัคร') }}</option>
        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('เสร็จสิ้น') }}</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">{{ __('ค้นหา') }}</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th style="width:50px;"></th>
                    <th>{{ __('หัวข้องาน') }}</th>
                    <th>{{ __('ประเภท') }}</th>
                    <th>{{ __('ตำแหน่ง') }}</th>
                    <th>{{ __('สถานะ') }}</th>
                    <th>{{ __('ผู้สมัคร') }}</th>
                    <th>{{ __('วันเริ่ม') }}</th>
                    <th>{{ __('จัดการ') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jobs as $job)
                <tr>
                    <td>{{ $job->id }}</td>
                    <td style="width:50px; padding-right:0;">
                        @if($job->image_path)
                            <img src="{{ Storage::url($job->image_path) }}" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:8px; background:#f1f5f9;">
                        @endif
                    </td>
                    <td title="{{ $job->title }}"><a href="{{ route('admin.jobs.show', $job->id) }}" class="text-primary font-semi">{{ Str::limit($job->title, 40, '...') }}</a></td>
                    <td>
                        <span class="badge {{ $job->job_type === 'parttime' ? 'job-badge-parttime' : 'job-badge-general' }}">
                            {{ $job->job_type === 'parttime' ? 'Part-time' : 'งานทั่วไป' }}
                        </span>
                    </td>
                    <td title="{{ $job->position }}">{{ Str::limit($job->position, 30, '...') }}</td>
                    <td>
                        @if($job->status === 'open')
                            <span class="badge badge-green">{{ __('เปิด') }}</span>
                        @elseif($job->status === 'closed')
                            <span class="badge badge-red">{{ __('ปิด') }}</span>
                        @else
                            <span class="badge badge-gray">{{ __('เสร็จสิ้น') }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge badge-orange">{{ $job->applications_count }} คน</span>
                    </td>
                    <td>{{ $job->start_date?->format('d/m/Y') ?? '-' }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.jobs.show', $job->id) }}" class="btn btn-outline btn-sm">{{ __('ดู') }}</a>
                            <a href="{{ route('admin.jobs.edit', $job->id) }}" class="btn btn-outline btn-sm">{{ __('แก้ไข') }}</a>
                            <form method="POST" action="{{ route('admin.jobs.destroy', $job->id) }}" onsubmit="return confirm('ลบประกาศ &quot;{{ $job->title }}&quot; จริงหรือ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">{{ __('ลบ') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state-row">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <div class="empty-state-row-title">{{ __('ยังไม่มีประกาศงาน') }}</div>
                            <div class="empty-state-row-desc">{{ __('กดสร้างประกาศงานใหม่เพื่อเริ่มต้น') }}</div>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $jobs->links() }}</div>
@endsection
