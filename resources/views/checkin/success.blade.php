{{-- หน้าแสดงผลเช็คอินสำเร็จ: ชื่อกิจกรรม + วันที่ + ชั่วโมง + สถานะอนุมัติ --}}
@extends('layouts.app')
@section('title', 'เช็คอินสำเร็จ')

@section('content')
<div class="container-sm" style="padding-top:2rem;">
    @if(isset($status) && $status === 'approved')
    <div class="checkin-ok">
        <svg class="icon-xl" style="margin:0 auto 1rem;color:#16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <h1 class="font-bold" style="font-size:1.5rem;">เช็คอินสำเร็จ!</h1>
        <p class="text-sm text-muted" style="margin-top:.25rem;">อนุมัติอัตโนมัติ (อยู่ในบริเวณกิจกรรม)</p>
        <p style="margin-top:.5rem;">{{ $activity->title }}</p>
        <p class="text-sm" style="margin-top:.25rem;">{{ $activity->activity_date->format('d/m/Y') }} &middot; {{ $activity->activity_hours }} ชม.</p>
        @if(isset($distance) && $distance !== null)
            <p class="text-xs text-muted" style="margin-top:.25rem;">ระยะห่าง: {{ number_format($distance, 0) }} เมตร</p>
        @endif
    </div>
    @else
    <div class="checkin-ok" style="background:linear-gradient(135deg,#fbbf24 0%,#f59e0b 100%);">
        <svg class="icon-xl" style="margin:0 auto 1rem;color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <h1 class="font-bold" style="font-size:1.5rem;color:#fff;">บันทึกแล้ว รอการอนุมัติ</h1>
        <p style="margin-top:.5rem;color:#fff;">{{ $activity->title }}</p>
        <p class="text-sm" style="margin-top:.25rem;color:rgba(255,255,255,.8);">{{ $activity->activity_date->format('d/m/Y') }} &middot; {{ $activity->activity_hours }} ชม.</p>
        @if(isset($distance) && $distance !== null)
            <p class="text-xs" style="margin-top:.25rem;color:rgba(255,255,255,.7);">ระยะห่าง: {{ number_format($distance, 0) }} เมตร</p>
        @endif
    </div>
    @endif
    <a href="{{ route('activities.index') }}" class="btn btn-primary btn-block mt-4">กลับหน้ากิจกรรม</a>
</div>
@endsection
