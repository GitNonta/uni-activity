{{-- หน้ารายละเอียดกิจกรรม: รูป, ข้อมูล, สถานะลงทะเบียน, ปุ่มเช็คอิน --}}
@extends('layouts.app')
@section('title', $activity->title)

@section('content')
<a href="{{ route('activities.index') }}" class="text-sm text-primary">&larr; กลับ</a>

{{-- การ์ดแสดงข้อมูลกิจกรรม --}}
<div class="card mt-2">
    {{-- รูปภาพกิจกรรม --}}
    @if($activity->image_path)
        <img data-src="{{ Storage::url($activity->image_path) }}" alt="{{ $activity->title }}" class="activity-hero-image lazy-img" style="background:#f1f5f9;">
    @else
        <div class="act-card-img">
            <svg class="icon-xl" style="color:rgba(255,255,255,.3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
    @endif
    <div class="card-body">
        <div class="flex items-center gap-2 mb-2">
            @include('components.status-badge', ['status' => $activity->computed_status])
            @if($activity->is_mandatory)<span class="badge badge-red">บังคับ</span>@endif
            @if($activity->category)<span class="badge badge-blue">{{ $activity->category->name }}</span>@endif
        </div>
        <h1 class="font-bold" style="font-size:1.25rem;">{{ $activity->title }}</h1>

        @if($activity->description)
            <p class="text-muted text-sm mt-2">{{ $activity->description }}</p>
        @endif

        <hr class="divider">

        {{-- ข้อมูลกิจกรรม: วันที่, เวลา, สถานที่, ชั่วโมง --}}
        <div class="grid-2" style="font-size:.875rem;">
            <div>
                <span class="text-muted">
                    <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    วันที่
                </span>
                <p class="font-semi">{{ $activity->activity_date->format('d/m/Y') }}</p>
            </div>
            <div>
                <span class="text-muted">
                    <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    เวลา
                </span>
                <p class="font-semi">{{ \Carbon\Carbon::parse($activity->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($activity->end_time)->format('H:i') }}</p>
            </div>
            <div>
                <span class="text-muted">
                    <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    สถานที่
                </span>
                <p class="font-semi">{{ $activity->location ?? '-' }}</p>
            </div>
            <div>
                <span class="text-muted">ชั่วโมงกิจกรรม</span>
                <p class="font-semi">{{ $activity->activity_hours }} ชม.</p>
            </div>
        </div>

        <hr class="divider">

        {{-- แถบแสดงจำนวนผู้ลงทะเบียน --}}
        <div class="mb-4">
            <div class="flex justify-between text-sm mb-1">
                <span class="text-muted">ผู้ลงทะเบียน</span>
                <span class="font-semi">{{ $activity->getRegisteredCount() }}/{{ $activity->max_participants }}</span>
            </div>
            <div class="progress">
                @php $pct = $activity->max_participants > 0 ? min(100, ($activity->getRegisteredCount()/$activity->max_participants)*100) : 0; @endphp
                <div class="progress-bar {{ $pct >= 100 ? 'red' : ($pct >= 70 ? 'yellow' : 'green') }}" style="width:{{ $pct }}%"></div>
            </div>
        </div>

        {{-- ส่วนแสดงสถานะ: ลงทะเบียนแล้ว / ปุ่มเช็คอิน / ปุ่มลงทะเบียน / ปุ่มยกเลิก --}}
        @auth
            @if($userRegistration)
                <div class="alert alert-success text-sm">
                    <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    คุณลงทะเบียนแล้ว (สถานะ: {{ $userRegistration->status }})
                </div>

                @if($userAttendance && $userAttendance->status === 'approved')
                    <div class="alert alert-info text-sm">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        สำเร็จ (อนุมัติ) เมื่อ {{ $userAttendance->checked_in_at ? $userAttendance->checked_in_at->format('d/m/Y H:i') : '-' }}
                    </div>
                @elseif($userAttendance && $userAttendance->status === 'pending')
                    <div class="alert alert-info text-sm" style="background:#fef3c7;color:#92400e;border-color:#fde68a;">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        บันทึกแล้ว รออนุมัติ
                    </div>
                @elseif($userAttendance && $userAttendance->status === 'rejected')
                    <div class="alert alert-error text-sm">
                        บันทึกกิจกรรมถูกปฏิเสธ
                    </div>
                @elseif(!$userAttendance && $userRegistration->status === 'approved' && ($activity->allow_early_checkin || (now() >= $activity->checkin_open_at && now() <= $activity->checkin_close_at)))
                    <div class="alert alert-info text-sm">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        กรุณาสแกน QR Code หน้างานเพื่อเช็คอิน
                    </div>
                @elseif($userRegistration->status === 'approved' && now() < $activity->checkin_open_at)
                    <div class="alert alert-info text-sm">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        เปิดบันทึกกิจกรรม: {{ $activity->checkin_open_at->format('d/m/Y H:i') }}
                    </div>
                @endif

                @if($userRegistration->status !== 'cancelled' && !$userAttendance)
                    <form method="POST" action="{{ route('registrations.destroy', $userRegistration->id) }}" class="mt-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('ยืนยันยกเลิกการลงทะเบียน?')">ยกเลิกการลงทะเบียน</button>
                    </form>
                @endif
            @elseif($activity->computed_status === 'open' && $activity->getRemainingSlots() > 0)
                <form method="POST" action="{{ route('activities.register', $activity->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-block btn-lg">ลงทะเบียน</button>
                </form>
            @else
                <button disabled class="btn btn-outline btn-block">ไม่สามารถลงทะเบียนได้</button>
            @endif
        @endauth
    </div>
</div>
@endsection

@section('scripts')
<script>
// Lazy Loading Images
document.addEventListener('DOMContentLoaded', function() {
    var lazyImages = document.querySelectorAll('img.lazy-img');
    
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    var src = img.getAttribute('data-src');
                    if (src) {
                        img.src = src;
                        img.classList.add('loaded');
                        img.removeAttribute('data-src');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        lazyImages.forEach(function(img) {
            imageObserver.observe(img);
        });
    } else {
        lazyImages.forEach(function(img) {
            var src = img.getAttribute('data-src');
            if (src) {
                img.src = src;
                img.removeAttribute('data-src');
            }
        });
    }
});
</script>
@endsection
