{{-- หน้ากิจกรรมของฉัน: ภารกิจที่ต้องทำ + รายการลงทะเบียน + QR Pass modal --}}
@extends('layouts.app')
@section('title', 'กิจกรรมของฉัน')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h1 class="font-bold" style="font-size:1.5rem;">กิจกรรมของฉัน</h1>
    <a href="{{ route('student.calendar') }}" class="btn btn-outline btn-sm">
        <svg style="width:15px;height:15px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        ปฏิทิน
    </a>
</div>

{{-- ── ภารกิจที่ต้องทำ ── --}}
@if($todos->isNotEmpty())
<div class="mb-5">
    <div class="flex items-center gap-2 mb-3">
        <svg style="width:18px;height:18px;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 4h6m-6 4h4"/>
        </svg>
        <h2 class="font-bold" style="font-size:1rem;">ภารกิจที่ต้องทำ
            <span style="background:#f59e0b;color:#fff;border-radius:999px;padding:1px 8px;font-size:.75rem;margin-left:6px;">{{ $todos->count() }}</span>
        </h2>
    </div>
    <div style="display:flex;gap:.75rem;overflow-x:auto;padding-bottom:.5rem;scroll-snap-type:x mandatory;">
        @foreach($todos as $todo)
        <div style="min-width:260px;max-width:300px;scroll-snap-align:start;flex-shrink:0;
                    background:{{ $todo['bg'] }};border:1.5px solid {{ $todo['color'] }}22;
                    border-left:4px solid {{ $todo['color'] }};border-radius:12px;padding:1rem;
                    box-shadow:0 2px 8px rgba(0,0,0,.06);">
            {{-- Icon + label --}}
            <div class="flex items-center gap-2 mb-2">
                @if($todo['icon'] === 'check')
                    <span style="background:{{ $todo['color'] }};color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </span>
                @elseif($todo['icon'] === 'clock')
                    <span style="background:{{ $todo['color'] }};color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                @elseif($todo['icon'] === 'star')
                    <span style="background:{{ $todo['color'] }};color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    </span>
                @else
                    <span style="background:{{ $todo['color'] }};color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                @endif
                <span style="font-size:.78rem;font-weight:700;color:{{ $todo['color'] }};">{{ $todo['label'] }}</span>
            </div>
            <p class="font-semi text-sm" style="margin-bottom:.35rem;color:#1e293b;line-height:1.3;">{{ $todo['activity']->title }}</p>
            <p class="text-xs text-muted" style="margin-bottom:.6rem;">
                📅 {{ $todo['activity']->activity_date->format('d/m/Y') }}
                · <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> {{ $todo['activity']->location }}
            </p>
            <a href="{{ $todo['action_url'] }}" class="btn btn-sm" style="background:{{ $todo['color'] }};color:#fff;width:100%;justify-content:center;font-size:.8rem;padding:.4rem .75rem;border-radius:8px;">
                {{ $todo['action_label'] }}
            </a>
        </div>
        @endforeach
    </div>
</div>
@endif



{{-- ── กิจกรรมที่เข้าร่วมผ่าน Walk-in ── --}}
@forelse($walkInAttendances as $att)
    <div class="card mb-2" style="border-left:4px solid #f59e0b;">
        <div class="card-body flex items-center justify-between gap-4">
            <div style="flex:1;min-width:0;">
                <div class="flex items-center gap-2 mb-1">
                    @include('components.status-badge', ['status' => $att->activity->computed_status])
                    <span class="badge" style="background:#f59e0b;color:white;font-size:.72rem;padding:.25rem .6rem;border-radius:999px;">Walk-in</span>
                    @if($att->status === 'approved')
                        <span class="badge badge-green flex items-center gap-1">
                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            สำเร็จ
                        </span>
                    @elseif($att->status === 'pending')
                        <span class="badge badge-yellow">รออนุมัติ</span>
                    @elseif($att->status === 'rejected')
                        <span class="badge badge-red">ถูกปฏิเสธ</span>
                    @endif
                </div>
                <h3 class="font-semi line-clamp-1">{{ $att->activity->title }}</h3>
                <p class="text-xs text-muted mt-1" style="display:flex;align-items:center;gap:4px;">
                    <svg style="width:14px;height:14px;display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> {{ $att->activity->activity_date->format('d/m/Y') }} ·
                    <svg style="width:14px;height:14px;display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> {{ $att->activity->location }}
                </p>
            </div>
            <div class="flex gap-2" style="flex-shrink:0;">
                @if($att->activity->feedbacks->where('user_id', auth()->id())->count() > 0)
                    <span class="badge badge-blue flex items-center gap-1" style="padding:.375rem .75rem;font-size:.8rem;">
                        <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        ประเมินแล้ว
                    </span>
                @elseif($att->status === 'approved' && $att->activity->computed_status === 'done')
                    <a href="{{ route('feedback.create', $att->activity_id) }}" class="btn btn-primary btn-sm">ประเมิน</a>
                @endif
            </div>
        </div>
    </div>
@empty
@endforelse

{{-- ── กิจกรรมที่ลงทะเบียนปกติ ── --}}
@php
    $sectionTitle = $todos->isNotEmpty() ? 'รายการลงทะเบียนทั้งหมด' : null;
@endphp

@if($sectionTitle && $registrations->isNotEmpty())
<h2 class="font-bold mb-3 mt-2" style="font-size:.95rem;color:#64748b;">{{ $sectionTitle }}</h2>
@endif

@forelse($registrations as $reg)
    @php
        $att = $attendanceMap->get($reg->activity_id);
        $hasFeedback = in_array($reg->activity_id, $feedbackDoneIds);
        $status = $reg->activity->computed_status;
        $checkinOpen = $reg->activity->allow_early_checkin ||
            (now() >= $reg->activity->checkin_open_at && now() <= $reg->activity->checkin_close_at);
    @endphp
    <div class="card mb-2" style="{{ (!$att && $reg->status === 'approved' && $checkinOpen) ? 'border-left:3px solid #16a34a;' : '' }}">
        <div class="card-body flex items-center justify-between gap-4">
            <div style="flex:1;min-width:0;">
                <div class="flex items-center gap-2 mb-1">
                    @include('components.status-badge', ['status' => $status])
                    @php
                        $sc = ['pending'=>'badge-yellow','approved'=>'badge-green','cancelled'=>'badge-gray','rejected'=>'badge-red', 'waitlisted'=>'badge-yellow'];
                        $sl = ['pending'=>'รออนุมัติลงทะเบียน','approved'=>'ลงทะเบียนสำเร็จ','cancelled'=>'ยกเลิก','rejected'=>'ปฏิเสธ', 'waitlisted'=>'Waitlist (รอคิว)'];
                    @endphp
                    <span class="badge {{ $sc[$reg->status] ?? 'badge-gray' }}" style="font-size:.72rem; padding:.25rem .6rem; border-radius:999px;">{{ $sl[$reg->status] ?? $reg->status }}</span>
                    
                    @if($att && $att->status === 'approved')
                        <span class="badge badge-blue flex items-center gap-1" style="font-size:.72rem; padding:.25rem .6rem; border-radius:999px;">
                            <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            เช็คอินแล้ว
                        </span>
                    @elseif($att && $att->status === 'pending')
                        <span class="badge badge-yellow" style="font-size:.72rem; padding:.25rem .6rem; border-radius:999px;">รออนุมัติเช็คอิน</span>
                    @endif
                </div>
                <h3 class="font-semi line-clamp-1" style="font-size:1.05rem; margin-bottom:.2rem;">{{ $reg->activity->title }}</h3>
                <p class="text-xs text-muted mb-1">
                    <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> {{ $reg->activity->activity_date->format('d/m/Y') }}
                    · <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> {{ $reg->activity->start_time }} – {{ $reg->activity->end_time }}
                    · <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> {{ $reg->activity->location }}
                </p>
            </div>
            <div class="flex gap-1" style="flex-shrink:0;flex-direction:column;align-items:flex-end;">
                {{-- Checkin --}}
                @if(!$att && $reg->status === 'approved' && $checkinOpen)
                <span class="badge badge-blue" style="font-size:.72rem;padding:.3rem .6rem;">สแกน QR หน้างาน</span>
                @elseif($att && $att->status === 'approved' && !$hasFeedback && $status === 'done')
                <a href="{{ route('feedback.create', $reg->activity_id) }}" class="btn btn-primary btn-sm flex items-center gap-1" style="font-size:.75rem;">
                    <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    ประเมิน
                </a>
                @elseif($att && $att->status === 'approved' && $hasFeedback)
                <span class="badge badge-blue" style="font-size:.72rem;padding:.3rem .6rem;">ประเมินแล้ว</span>
                @endif
                {{-- Cancel --}}
                @if($reg->status === 'approved' && !$att && in_array($status, ['upcoming','open','ongoing']))
                <form method="POST" action="{{ route('registrations.destroy', $reg->id) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fca5a5;font-size:.72rem;" onclick="return confirm('ยืนยันยกเลิก?')">ยกเลิก</button>
                </form>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="empty-state">
        <svg class="icon-xl" style="margin:0 auto 1rem;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
        <p>ยังไม่มีกิจกรรมที่ลงทะเบียน</p>
        <a href="{{ route('activities.index') }}" class="btn btn-primary btn-sm mt-4">ดูกิจกรรมทั้งหมด</a>
    </div>
@endforelse
@endsection

@section('scripts')
{{-- qrcode.js CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// No QR Modal needed anymore
</script>
@endsection
