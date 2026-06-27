{{-- Admin: กล่องข้อความรวม --}}
@extends('layouts.admin')
@section('title', 'กล่องข้อความ')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold flex items-center gap-2" style="font-size:1.25rem;">
        <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        กล่องข้อความ
    </h1>
    <span class="text-sm text-muted">การสนทนาทั้งหมด {{ $threads->count() }} รายการ</span>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    @forelse($threads as $thread)
    @php
        $unread = $thread['unread'] ?? 0;
        $time   = $thread['last_time'];
    @endphp
    <a href="{{ route('admin.inbox.show', [$thread['job_id'], $thread['student_id']]) }}"
       style="display:flex;align-items:center;gap:1rem;padding:.9rem 1.25rem;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;transition:background .15s;{{ $unread > 0 ? 'background:#faf5ff;' : '' }}"
       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='{{ $unread > 0 ? '#faf5ff' : '' }}'">

        {{-- Avatar --}}
        @if(!empty($thread['student_photo']))
            <img src="{{ $thread['student_photo'] }}" alt="{{ $thread['student_name'] }}"
                 style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        @else
            <div style="width:42px;height:42px;border-radius:50%;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                {{ strtoupper(mb_substr($thread['student_name'], 0, 1)) }}
            </div>
        @endif

        {{-- Info --}}
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:baseline;gap:.5rem;margin-bottom:.2rem;">
                <span style="font-weight:{{ $unread > 0 ? '700' : '600' }};font-size:.95rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;">
                    {{ $thread['student_name'] }}
                </span>
                <span style="font-size:.8rem;color:#6366f1;font-weight:500;flex-shrink:0;">
                    [{{ $thread['job_title'] }}]
                </span>
            </div>
            <p style="margin:0;font-size:.82rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {!! $thread['last_message'] ? e($thread['last_message']) : '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ไฟล์แนบ' !!}
            </p>
        </div>

        {{-- Time + Unread --}}
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.3rem;flex-shrink:0;">
            <span style="font-size:.72rem;color:#94a3b8;">
                {{ $time ? $time->diffForHumans() : '' }}
            </span>
            @if($unread > 0)
            <span style="background:#4f46e5;color:#fff;border-radius:999px;font-size:.7rem;font-weight:700;padding:.1rem .45rem;min-width:20px;text-align:center;">
                {{ $unread }}
            </span>
            @endif
        </div>
    </a>
    @empty
    <div style="padding:3rem;text-align:center;color:#94a3b8;">
        <div style="margin-bottom:.5rem;display:flex;justify-content:center;color:#94a3b8;">
            <svg style="width:48px;height:48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        </div>
        <p style="margin:0;font-size:.95rem;">ยังไม่มีข้อความจากนักศึกษา</p>
    </div>
    @endforelse
</div>
@endsection
