{{-- หน้ายืนยันเช็คอินจาก QR: แสดงชื่อกิจกรรม + ปุ่มยืนยัน --}}
@extends('layouts.app')
@section('title', 'เช็คอิน')

@section('content')
<div class="container-sm" style="padding-top:2rem;">
    <div class="card">
        <div class="card-body text-center">
            <svg class="icon-xl" style="margin:0 auto 1rem;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            @if(isset($isCheckoutToken) && $isCheckoutToken)
                <h1 class="font-bold" style="font-size:1.25rem;">บันทึกเวลาออกงาน</h1>
            @else
                <h1 class="font-bold" style="font-size:1.25rem;">ยืนยันเช็คอินเข้างาน</h1>
            @endif
            <p class="text-muted text-sm mt-1">{{ $activity->title }}</p>
            <p class="text-xs text-muted mt-1">
                {{ $activity->activity_date->format('d/m/Y') }} &middot; {{ $activity->location }}
            </p>
            <hr class="divider">
            <form method="POST" action="{{ route('checkin.store', $token) }}" id="qrCheckinForm">
                @csrf
                <input type="hidden" name="latitude" id="qr_lat">
                <input type="hidden" name="longitude" id="qr_lng">
                <input type="hidden" name="geo_telemetry" id="geo_telemetry">
                @if(isset($isCheckoutToken) && $isCheckoutToken)
                    <button type="submit" class="btn btn-primary btn-lg btn-block" onclick="return submitQrWithLocation(event)">บันทึกออกงาน (รับชั่วโมง)</button>
                @else
                    <button type="submit" class="btn btn-success btn-lg btn-block" onclick="return submitQrWithLocation(event)">เช็คอินเข้างาน</button>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/geo-security.js') }}?v=1"></script>
<script>
function submitQrWithLocation(e) {
    e.preventDefault();
    var form = document.getElementById('qrCheckinForm');
    var btn = e.currentTarget || form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'กำลังตรวจสอบพิกัด...'; }

    var submitted = false;
    var doSubmit = function() {
        if (submitted) return;
        submitted = true;
        form.submit();
    };

    if (window.GeoSecurity && navigator.geolocation) {
        var safetyTimer = setTimeout(doSubmit, 3500);
        window.GeoSecurity.getSecurePosition(
            function(pos, telemetry) {
                clearTimeout(safetyTimer);
                document.getElementById('qr_lat').value = pos.coords.latitude;
                document.getElementById('qr_lng').value = pos.coords.longitude;
                if (document.getElementById('geo_telemetry') && telemetry) {
                    document.getElementById('geo_telemetry').value = JSON.stringify(telemetry);
                }
                doSubmit();
            },
            function() {
                clearTimeout(safetyTimer);
                doSubmit();
            },
            { timeout: 3000, minSamples: 2 }
        );
    } else if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('qr_lat').value = pos.coords.latitude;
                document.getElementById('qr_lng').value = pos.coords.longitude;
                doSubmit();
            },
            function() { doSubmit(); },
            { enableHighAccuracy: true, timeout: 5000 }
        );
    } else {
        doSubmit();
    }
    return false;
}
</script>
@endsection
