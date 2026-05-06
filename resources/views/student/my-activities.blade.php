{{-- หน้ากิจกรรมของฉัน: แสดงรายการที่ลงทะเบียน, ปุ่มเช็คอิน/ยกเลิก, ป้ายบันทึกแล้ว --}}
@extends('layouts.app')
@section('title', 'กิจกรรมของฉัน')

@section('content')
<h1 class="font-bold mb-4" style="font-size:1.5rem;">กิจกรรมของฉัน</h1>

{{-- รายการกิจกรรมที่ลงทะเบียน: สถานะกิจกรรม + สถานะลงทะเบียน + ปุ่มดำเนินการ --}}
{{-- กิจกรรมที่เข้าร่วมผ่าน Walk-in Check-in --}}
@php
    $walkInAttendances = \App\Models\Attendance::with('activity')
        ->where('user_id', auth()->id())
        ->where('method', 'walk_in')
        ->whereNotIn('activity_id', $registrations->pluck('activity_id'))
        ->orderByDesc('created_at')
        ->get();
@endphp

@forelse($walkInAttendances as $att)
    <div class="card mb-2" style="border-left: 4px solid #f59e0b;">
        <div class="card-body flex items-center justify-between gap-4">
            <div style="flex:1;min-width:0;">
                <div class="flex items-center gap-2 mb-1">
                    @include('components.status-badge', ['status' => $att->activity->computed_status])
                    <span class="badge badge-orange" style="background:#f59e0b;color:white;">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Walk-in
                    </span>
                    @if($att->status === 'approved')
                        <span class="badge badge-green" style="padding:.375rem .75rem;font-size:.8rem;">
                            <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        สำเร็จ
                        </span>
                    @elseif($att->status === 'pending')
                        <span class="badge badge-yellow" style="padding:.375rem .75rem;font-size:.8rem;">รอการอนุมัติ</span>
                    @elseif($att->status === 'rejected')
                        <span class="badge badge-red" style="padding:.375rem .75rem;font-size:.8rem;">ถูกปฏิเสธ</span>
                    @endif
                </div>
                <h3 class="font-semi line-clamp-1">{{ $att->activity->title }}</h3>
                <p class="text-xs text-muted mt-1">
                    <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $att->activity->activity_date->format('d/m/Y') }}
                    &middot; {{ $att->activity->location }}
                    &middot; เช็คอิน {{ $att->created_at->format('d/m/Y H:i') }}
                </p>
            </div>
            <div class="flex gap-2" style="flex-shrink:0;">
                @if($att->activity->feedbacks->where('user_id', auth()->id())->count() > 0)
                    <span class="badge badge-blue" style="padding:.375rem .75rem;font-size:.8rem;">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        ประเมินแล้ว
                    </span>
                @else
                    <a href="{{ route('feedback.create', $att->activity_id) }}" class="btn btn-primary btn-sm">
                        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        ประเมิน
                    </a>
                @endif
            </div>
        </div>
    </div>
@empty
    {{-- ถ้าไม่มี walk-in ก็ไม่ต้องแสดงอะไร --}}
@endforelse

{{-- กิจกรรมที่ลงทะเบียนปกติ --}}
@forelse($registrations as $reg)
    <div class="card mb-2">
        <div class="card-body flex items-center justify-between gap-4">
            <div style="flex:1;min-width:0;">
                <div class="flex items-center gap-2 mb-1">
                    @include('components.status-badge', ['status' => $reg->activity->computed_status])
                    @php
                        $sc = ['pending'=>'badge-yellow','approved'=>'badge-green','cancelled'=>'badge-gray','rejected'=>'badge-red'];
                        $statusLabels = ['pending'=>'รออนุมัติ','approved'=>'ลงทะเบียนแล้ว','cancelled'=>'ยกเลิก','rejected'=>'ปฏิเสธ'];
                    @endphp
                    <span class="badge {{ $sc[$reg->status] ?? 'badge-gray' }}">{{ $statusLabels[$reg->status] ?? $reg->status }}</span>
                </div>
                <h3 class="font-semi line-clamp-1">{{ $reg->activity->title }}</h3>
                <p class="text-xs text-muted mt-1">
                    <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ $reg->activity->activity_date->format('d/m/Y') }}
                    &middot; {{ $reg->activity->location }}
                </p>
            </div>
            <div class="flex gap-2" style="flex-shrink:0;">
                @php
                    $att = \App\Models\Attendance::where('user_id', auth()->id())->where('activity_id', $reg->activity_id)->first();
                @endphp
                @if($att && $att->status === 'approved')
                    <span class="badge badge-green" style="padding:.375rem .75rem;font-size:.8rem;">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        สำเร็จ
                    </span>
                @elseif($att && $att->status === 'pending')
                    <span class="badge badge-yellow" style="padding:.375rem .75rem;font-size:.8rem;">รอการอนุมัติ</span>
                @elseif($att && $att->status === 'rejected')
                    <span class="badge badge-red" style="padding:.375rem .75rem;font-size:.8rem;">ถูกปฏิเสธ</span>
                @elseif(!$att && $reg->status === 'approved' && ($reg->activity->allow_early_checkin || (now() >= $reg->activity->checkin_open_at && now() <= $reg->activity->checkin_close_at)))
                    <form method="POST" action="{{ route('activities.self-checkin', $reg->activity_id) }}" class="checkin-form">
                        @csrf
                        <input type="hidden" name="latitude" class="checkin-lat">
                        <input type="hidden" name="longitude" class="checkin-lng">
                        <button type="submit" class="btn btn-success btn-sm" onclick="return submitCheckinWithLocation(event, this.form)">
                            <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            บันทึกกิจกรรม
                        </button>
                    </form>
                @endif
                @if($reg->status === 'approved' && !$att && in_array($reg->activity->computed_status, ['upcoming','open','ongoing']))
                    <form method="POST" action="{{ route('registrations.destroy', $reg->id) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('ยืนยันยกเลิก?')">ยกเลิก</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="empty-state">
        <svg class="icon-xl" style="margin:0 auto 1rem;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p>ยังไม่มีกิจกรรมที่ลงทะเบียน</p>
        <a href="{{ route('activities.index') }}" class="btn btn-primary btn-sm mt-4">ดูกิจกรรมทั้งหมด</a>
    </div>
@endforelse
@endsection

@section('scripts')
<script>
function submitCheckinWithLocation(e, form) {
    e.preventDefault();
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                form.querySelector('.checkin-lat').value = pos.coords.latitude;
                form.querySelector('.checkin-lng').value = pos.coords.longitude;
                if (confirm('ยืนยันบันทึกกิจกรรม?')) form.submit();
            },
            function() {
                if (confirm('ไม่สามารถดึงพิกัดได้ ต้องการบันทึกต่อหรือไม่? (จะต้องรอผู้จัดอนุมัติ)')) form.submit();
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        if (confirm('เบราว์เซอร์ไม่รองรับ GPS บันทึกต่อหรือไม่? (จะต้องรอผู้จัดอนุมัติ)')) form.submit();
    }
    return false;
}
</script>
@endsection
