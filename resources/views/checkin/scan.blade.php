{{-- หน้ายืนยันเช็คอินจาก QR: แสดงชื่อกิจกรรม + ปุ่มยืนยัน --}}
@extends('layouts.app')
@section('title', 'เช็คอิน')

@section('content')
<div class="container-sm" style="padding-top:2rem;">
    <div class="card">
        <div class="card-body text-center">
            <svg class="icon-xl" style="margin:0 auto 1rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h1 class="font-bold" style="font-size:1.25rem;">ยืนยันเช็คอิน</h1>
            <p class="text-muted text-sm mt-1">{{ $activity->title }}</p>
            <p class="text-xs text-muted mt-1">
                {{ $activity->activity_date->format('d/m/Y') }} &middot; {{ $activity->location }}
            </p>
            <hr class="divider">
            <form method="POST" action="{{ route('checkin.store', $token) }}" id="qrCheckinForm">
                @csrf
                <input type="hidden" name="latitude" id="qr_lat">
                <input type="hidden" name="longitude" id="qr_lng">
                <button type="submit" class="btn btn-success btn-lg btn-block" onclick="return submitQrWithLocation(event)">เช็คอิน</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function submitQrWithLocation(e) {
    e.preventDefault();
    var form = document.getElementById('qrCheckinForm');
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('qr_lat').value = pos.coords.latitude;
                document.getElementById('qr_lng').value = pos.coords.longitude;
                form.submit();
            },
            function() { form.submit(); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        form.submit();
    }
    return false;
}
</script>
@endsection
