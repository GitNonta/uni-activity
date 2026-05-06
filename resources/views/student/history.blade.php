{{-- หน้าประวัติการเข้าร่วมกิจกรรม: แสดงชื่อ, วันที่, หมวด, ชั่วโมง, เวลาเช็คอิน --}}
@extends('layouts.app')
@section('title', 'ประวัติเข้าร่วม')

@section('content')
<h1 class="font-bold mb-4" style="font-size:1.5rem;">ประวัติเข้าร่วมกิจกรรม</h1>

@forelse($attendances as $att)
    <div class="card mb-2">
        <div class="card-body">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semi">{{ $att->activity->title }}</h3>
                    <p class="text-xs text-muted mt-1">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $att->activity->activity_date->format('d/m/Y') }}
                        &middot; {{ $att->activity->category->name ?? '-' }}
                    </p>
                </div>
                <div class="text-right">
                    @if($att->status === 'approved')
                        <span class="badge badge-green" style="font-size:.7rem;">สำเร็จ</span>
                    @elseif($att->status === 'pending')
                        <span class="badge badge-yellow" style="font-size:.7rem;">รออนุมัติ</span>
                    @elseif($att->status === 'rejected')
                        <span class="badge badge-red" style="font-size:.7rem;">ปฏิเสธ</span>
                    @endif
                    <span class="font-bold text-primary">{{ $att->activity->activity_hours }} ชม.</span>
                    <p class="text-xs text-muted">{{ $att->checked_in_at ? $att->checked_in_at->format('H:i') : '-' }}</p>
                    
                    @if($att->status === 'approved')
                        @php
                            $hasFeedback = $att->activity->feedbacks()->where('user_id', auth()->id())->exists();
                        @endphp
                        @if($hasFeedback)
                            <span class="badge" style="background:#dcfce7;color:#166534;font-size:.7rem;margin-top:.25rem;">ประเมินแล้ว ✓</span>
                        @else
                            <a href="{{ route('feedback.create', $att->activity_id) }}" class="btn btn-sm btn-primary" style="font-size:.7rem;padding:4px 10px;margin-top:.25rem;">ประเมิน</a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="empty-state">
        <svg class="icon-xl" style="margin:0 auto 1rem;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p>ยังไม่มีประวัติ</p>
    </div>
@endforelse
@endsection
