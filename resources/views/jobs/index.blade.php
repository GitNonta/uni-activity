{{-- หน้ารายการประกาศงานทั้งหมด: ค้นหา + กรอง + Grid Card + แผนที่ --}}
@extends('layouts.app')
@section('title', 'หางาน / Part-time')

@section('content')
<h1 class="font-bold mb-4" style="font-size:1.5rem;">
    <svg class="icon" style="display:inline;vertical-align:-3px;margin-right:.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    ประกาศรับสมัครงาน
</h1>

{{-- ฟอร์มค้นหาและกรอง --}}
<form method="GET" action="{{ route('jobs.index') }}" class="flex gap-2 mb-4" style="flex-wrap:wrap;">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="ค้นหาชื่องาน / ตำแหน่ง / สถานที่..." class="form-control flex-1" style="min-width:200px;">
    <select name="job_type" class="form-control" style="width:auto;">
        <option value="">ทุกประเภท</option>
        <option value="general" {{ request('job_type') == 'general' ? 'selected' : '' }}>งานทั่วไป</option>
        <option value="parttime" {{ request('job_type') == 'parttime' ? 'selected' : '' }}>Part-time</option>
    </select>
    <select name="status" class="form-control" style="width:auto;">
        <option value="">ทุกสถานะ</option>
        <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>เปิดรับสมัคร</option>
        <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>ปิดรับสมัคร</option>
        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>เสร็จสิ้น</option>
    </select>
    <select name="gender" class="form-control" style="width:auto;">
        <option value="">ทุกเพศ</option>
        <option value="male" {{ request('gender') == 'male' ? 'selected' : '' }}>ชาย</option>
        <option value="female" {{ request('gender') == 'female' ? 'selected' : '' }}>หญิง</option>
    </select>
    <button type="submit" class="btn btn-primary">ค้นหา</button>
    @if($geoJobs->count())
    <button type="button" class="btn btn-outline" onclick="openJobMap()" style="white-space:nowrap;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:-2px;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        แผนที่งาน
    </button>
    @endif
</form>

{{-- แสดงการ์ดงาน --}}
<div class="grid-3">
    @forelse($jobs as $job)
        @include('components.job-card', [
            'job' => $job,
            'isApplied' => in_array($job->id, $appliedJobIds ?? []),
        ])
    @empty
        <div class="empty-state" style="grid-column:1/-1;">
            <svg class="icon-xl" style="margin:0 auto 1rem;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <p>ไม่พบประกาศงาน</p>
        </div>
    @endforelse
</div>

{{-- Pagination --}}
<div class="mt-4">{{ $jobs->links() }}</div>

{{-- Map Modal Overlay --}}
<div id="jobMapModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);">
    <div style="position:absolute;inset:0;display:flex;flex-direction:column;">
        <div style="background:#fff;padding:.75rem 1rem;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 8px rgba(0,0,0,.1);z-index:10;">
            <span style="font-weight:700;font-size:1rem;" id="mapTitle">แผนที่ประกาศงาน</span>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <button id="btnClearRoute" onclick="clearRoute()" style="display:none;padding:4px 12px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:6px;font-size:.8rem;font-weight:500;cursor:pointer;">✕ ปิดนำทาง</button>
                <button onclick="closeJobMap()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;padding:0 .5rem;line-height:1;">&times;</button>
            </div>
        </div>
        <div style="flex:1;display:flex;position:relative;">
            <div id="jobMapContainer" style="flex:1;"></div>
            <div id="directionsPanel" style="display:none;width:320px;background:#fff;overflow-y:auto;border-left:1px solid #e2e8f0;flex-shrink:0;"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
<style>
    .act-map-btn {
        display:inline-flex;align-items:center;justify-content:center;
        width:28px;height:28px;border-radius:50%;border:none;
        background:#e0f2fe;color:#0284c7;cursor:pointer;
        transition:background .2s,transform .15s;margin-left:auto;flex-shrink:0;
    }
    .act-map-btn:hover { background:#bae6fd;transform:scale(1.15); }
    .map-marker-img {
        width:44px;height:44px;border-radius:50%;object-fit:cover;
        border:3px solid #4f46e5;box-shadow:0 2px 8px rgba(0,0,0,.3);
        background:#fff;
    }
    .map-marker-name {
        background:#4f46e5;color:#fff;padding:3px 8px;border-radius:12px;
        font-size:.7rem;font-weight:600;white-space:nowrap;
        box-shadow:0 2px 6px rgba(0,0,0,.25);border:2px solid #fff;
        max-width:120px;overflow:hidden;text-overflow:ellipsis;
    }
    .map-marker-highlight .map-marker-img { border-color:#f59e0b;box-shadow:0 0 0 4px rgba(245,158,11,.4); }
    .map-marker-highlight .map-marker-name { background:#f59e0b; }
    .leaflet-popup-content { min-width:200px; }
    .map-popup-img { width:100%;height:100px;object-fit:cover;border-radius:8px;margin-bottom:8px; }
    .map-popup-title { font-weight:700;font-size:.9rem;margin-bottom:4px; }
    .map-popup-meta { font-size:.8rem;color:#64748b;margin-bottom:2px; }
    .map-popup-dist { font-size:.8rem;color:#4f46e5;font-weight:600;margin-top:6px; }
    .map-popup-link { display:inline-block;margin-top:8px;padding:4px 12px;background:#4f46e5;color:#fff;border-radius:6px;text-decoration:none;font-size:.8rem;font-weight:500; }
    .map-popup-link:hover { background:#4338ca; }
    .map-dist-label {
        background:rgba(79,70,229,.85);color:#fff;padding:2px 8px;border-radius:10px;
        font-size:.7rem;font-weight:600;white-space:nowrap;
        box-shadow:0 1px 4px rgba(0,0,0,.2);
    }
    .map-popup-dir { display:flex;gap:6px;margin-top:8px; }
    .map-dir-btn {
        flex:1;text-align:center;padding:5px 8px;border-radius:6px;
        font-size:.75rem;font-weight:600;text-decoration:none;color:#fff;
        transition:opacity .2s;
    }
    .map-dir-btn:hover { opacity:.85;text-decoration:none; }
    .map-dir-google { background:#4285f4; }
    .map-dir-apple { background:#333; }
    .map-nav-btn {
        display:block;width:100%;margin-top:8px;padding:6px 12px;background:#16a34a;color:#fff;
        border:none;border-radius:6px;font-size:.8rem;font-weight:600;cursor:pointer;text-align:center;
        transition:background .2s;
    }
    .map-nav-btn:hover { background:#15803d; }
    /* Directions panel */
    .dir-header { padding:1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0; }
    .dir-header h3 { font-size:.95rem;font-weight:700;margin:0 0 .5rem; }
    .dir-summary { display:flex;gap:1rem;margin-bottom:.5rem; }
    .dir-summary-item { text-align:center; }
    .dir-summary-value { font-size:1.1rem;font-weight:700;color:#4f46e5; }
    .dir-summary-label { font-size:.7rem;color:#64748b; }
    .dir-steps { list-style:none;padding:0;margin:0; }
    .dir-step { padding:.75rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;gap:.75rem;align-items:flex-start;font-size:.8rem; }
    .dir-step:hover { background:#f8fafc; }
    .dir-step-icon { width:28px;height:28px;background:#e0f2fe;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.75rem; }
    .dir-step-text { flex:1;line-height:1.4; }
    .dir-step-dist { color:#64748b;font-size:.75rem;margin-top:2px; }
    .dir-ext-links { padding:1rem;display:flex;gap:.5rem; }
    .dir-ext-links a { flex:1; }
    /* Hide default LRM container */
    .leaflet-routing-container { display:none !important; }
    @media (max-width:768px) {
        #directionsPanel { position:absolute;bottom:0;left:0;right:0;width:100% !important;max-height:45%;border-left:none !important;border-top:2px solid #e2e8f0;z-index:20;border-radius:12px 12px 0 0; }
    }
    /* ── Real-time Navigation ── */
    .nav-me-dot {
        width:20px;height:20px;position:relative;
    }
    .nav-me-dot-inner {
        width:20px;height:20px;background:#3b82f6;border:3px solid #fff;border-radius:50%;
        box-shadow:0 0 0 3px rgba(59,130,246,.35);position:relative;z-index:2;
    }
    .nav-me-dot-pulse {
        position:absolute;top:50%;left:50%;width:40px;height:40px;margin:-20px 0 0 -20px;
        background:rgba(59,130,246,.2);border-radius:50%;
        animation:navPulse 2s ease-out infinite;
    }
    @keyframes navPulse { 0%{transform:scale(.5);opacity:1} 100%{transform:scale(2.5);opacity:0} }
    .nav-heading-arrow {
        position:absolute;top:-14px;left:50%;margin-left:-8px;width:0;height:0;
        border-left:8px solid transparent;border-right:8px solid transparent;border-bottom:14px solid #3b82f6;
        filter:drop-shadow(0 1px 2px rgba(0,0,0,.3));z-index:3;transition:transform .3s ease;
    }
    .nav-accuracy-ring {
        border:2px solid rgba(59,130,246,.15);background:rgba(59,130,246,.05);border-radius:50%;
        position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;
    }
    /* Navigation arrow icon */
    .nav-arrow-icon {
        transition:transform .3s ease;
        filter:drop-shadow(0 2px 8px rgba(0,0,0,.3));
    }
    /* HUD overlay */
    .nav-hud {
        position:absolute;top:12px;left:50%;transform:translateX(-50%);z-index:1000;
        background:rgba(0,0,0,.85);color:#fff;border-radius:16px;padding:10px 20px;
        display:flex;gap:16px;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,.3);
        backdrop-filter:blur(8px);min-width:280px;justify-content:center;
    }
    .nav-hud-item { text-align:center; }
    .nav-hud-value { font-size:1.2rem;font-weight:700;line-height:1.2; }
    .nav-hud-label { font-size:.65rem;opacity:.7; }
    .nav-hud-divider { width:1px;height:32px;background:rgba(255,255,255,.2); }
    .nav-hud-instruction {
        position:absolute;top:100px;left:50%;transform:translateX(-50%);z-index:1000;
        background:rgba(79,70,229,.95);color:#fff;border-radius:12px;padding:8px 18px;
        font-size:.85rem;font-weight:600;box-shadow:0 4px 16px rgba(79,70,229,.3);
        max-width:90%;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }
    .nav-tunnel-badge {
        position:absolute;top:12px;right:12px;z-index:1000;
        background:#fbbf24;color:#92400e;border-radius:8px;padding:4px 10px;
        font-size:.75rem;font-weight:600;box-shadow:0 2px 8px rgba(0,0,0,.15);
        display:none;
    }
    .nav-speed-badge {
        position:absolute;bottom:20px;left:12px;z-index:1000;
        background:rgba(0,0,0,.75);color:#fff;border-radius:50%;width:52px;height:52px;
        display:none;align-items:center;justify-content:center;flex-direction:column;
        box-shadow:0 2px 8px rgba(0,0,0,.3);
    }
    .nav-speed-value { font-size:1rem;font-weight:700;line-height:1; }
    .nav-speed-unit { font-size:.55rem;opacity:.7; }
    .nav-arrived {
        position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1100;
        background:#fff;border-radius:16px;padding:2rem;text-align:center;
        box-shadow:0 8px 40px rgba(0,0,0,.3);max-width:320px;
    }
    .nav-arrived h2 { font-size:1.2rem;margin-bottom:.5rem; }
    .dir-step-active { background:#eff6ff;border-left:3px solid #4f46e5; }
</style>
<script>
var geoJobs = {!! json_encode($geoJobs->values()) !!};
var jobMap = null;
var jobMarkers = {};
var jobLines = [];
var jobDistLabels = [];
var userLat = null, userLng = null;
var highlightId = null;
var routingControl = null;
var meMarker = null;
var isRouting = false;

// ── Real-time Navigation State ──
var nav = {
    active: false,
    watchId: null,
    destLat: null, destLng: null,
    jobData: null,
    routeCoords: [],       // decoded route polyline coordinates
    routeSteps: [],        // turn-by-turn instructions
    currentStepIdx: 0,
    routeLine: null,       // L.polyline on map
    traveledLine: null,    // gray line for traveled portion
    heading: 0,
    speed: 0,              // m/s
    lastGpsTime: 0,
    gpsLostSince: 0,       // timestamp when GPS signal lost
    offRouteCount: 0,
    rerouteThrottle: 0,
    startTime: 0,
    hudEl: null, instrEl: null, tunnelEl: null, speedEl: null,
    // Adaptive GPS frequency
    gpsInterval: 2000,     // ms — start at 2s
    gpsFast: 1000,         // when navigating fast (>30km/h)
    gpsSlow: 3000,         // when slow/stationary
    gpsTimeout: null
};

// ══════════════════════════════════════
//  Kalman Filter for GPS smoothing
// ══════════════════════════════════════
var kalman = {
    lat: null, lng: null,
    variance: 1,           // initial uncertainty
    processNoise: 0.00001, // increase = more responsive, decrease = smoother
    reset: function() { this.lat = null; this.lng = null; this.variance = 1; },
    filter: function(lat, lng, accuracy) {
        // accuracy in meters → convert to approximate degree variance
        var accDeg = accuracy / 111320;
        var measurement_variance = accDeg * accDeg;
        if (this.lat === null) {
            this.lat = lat; this.lng = lng;
            this.variance = measurement_variance;
            return { lat: lat, lng: lng };
        }
        // Prediction step
        this.variance += this.processNoise;
        // Update step (Kalman gain)
        var K = this.variance / (this.variance + measurement_variance);
        this.lat = this.lat + K * (lat - this.lat);
        this.lng = this.lng + K * (lng - this.lng);
        this.variance = (1 - K) * this.variance;
        return { lat: this.lat, lng: this.lng };
    }
};

// ══════════════════════════════
//  Map & Marker basics
// ══════════════════════════════
function openJobMap(focusId) {
    var modal = document.getElementById('jobMapModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    highlightId = focusId || null;

    if (!jobMap) {
        jobMap = L.map('jobMapContainer', { zoomControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(jobMap);
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            buildMarkers();
        }, function() { buildMarkers(); }, { timeout: 5000 });
    } else { buildMarkers(); }
}

function buildMarkers() {
    Object.values(jobMarkers).forEach(function(m) { jobMap.removeLayer(m); });
    jobMarkers = {};
    jobLines.forEach(function(l) { jobMap.removeLayer(l); });
    jobLines = [];
    jobDistLabels.forEach(function(l) { jobMap.removeLayer(l); });
    jobDistLabels = [];
    if (meMarker && !nav.active) { jobMap.removeLayer(meMarker); meMarker = null; }

    var bounds = [];
    geoJobs.forEach(function(j) {
        var isHL = (highlightId && j.id === highlightId);
        
        var color = j.type === 'parttime' ? '#f97316' : '#3b82f6';
        var iconHtml = '<div style="width:32px;height:32px;background:'+color+';border:3px solid #fff;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);"><svg width="16" height="16" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>';
        
        var icon = L.divIcon({ className: '', html: iconHtml, iconSize: [32, 32], iconAnchor: [16, 16] });

        var marker = L.marker([j.lat, j.lng], { icon: icon }).addTo(jobMap);

        var dist = '', dirBtn = '', navBtn = '';
        if (userLat !== null && userLng !== null) {
            var d = haversine(userLat, userLng, j.lat, j.lng);
            dist = '<div class="map-popup-dist">ระยะตรง: ' + formatDist(d) + '</div>';

            var line = L.polyline([[userLat, userLng], [j.lat, j.lng]], {
                color: isHL ? '#f59e0b' : '#4f46e5', weight: 2, dashArray: '6, 6', opacity: 0.4
            }).addTo(jobMap);
            jobLines.push(line);

            var midLat = (userLat + j.lat) / 2, midLng = (userLng + j.lng) / 2;
            var distLabel = L.marker([midLat, midLng], {
                icon: L.divIcon({ className: '', html: '<span class="map-dist-label">' + formatDist(d) + '</span>', iconSize: [80, 20], iconAnchor: [40, 10] }),
                interactive: false
            }).addTo(jobMap);
            jobDistLabels.push(distLabel);

            navBtn = '<button class="map-nav-btn" onclick="startRealtimeNav(' + j.id + ',' + j.lat + ',' + j.lng + ')"><svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>นำทางแบบเรียลไทม์</button>';
            var gUrl = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + j.lat + ',' + j.lng;
            var aUrl = 'https://maps.apple.com/?saddr=' + userLat + ',' + userLng + '&daddr=' + j.lat + ',' + j.lng;
            dirBtn = '<div class="map-popup-dir">'
                + '<a href="' + gUrl + '" target="_blank" class="map-dir-btn map-dir-google">Google Maps</a>'
                + '<a href="' + aUrl + '" target="_blank" class="map-dir-btn map-dir-apple">Apple Maps</a></div>';
        }
        var imgHtml = j.image ? '<img src="' + j.image + '" style="width:100%;height:80px;object-fit:cover;border-radius:8px;margin-bottom:8px;">' : '';
        var typeLabel = j.type === 'parttime' ? '<span style="background:#fed7aa;color:#c2410c;padding:1px 6px;border-radius:10px;font-size:.7rem;font-weight:600;">Part-time</span>' : '<span style="background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:10px;font-size:.7rem;font-weight:600;">งานทั่วไป</span>';

        marker.bindPopup(
            imgHtml
            + '<div class="map-popup-title">' + typeLabel + ' ' + escHtml(j.title) + '</div>'
            + '<div class="map-popup-meta"><svg style="width:12px;height:12px;display:inline;margin-right:2px;vertical-align:-1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> ' + escHtml(j.position) + '</div>'
            + '<div class="map-popup-meta"><svg style="width:12px;height:12px;display:inline;margin-right:2px;vertical-align:-1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> ' + escHtml(j.location) + '</div>'
            + (j.compensation ? '<div class="map-popup-meta"><svg style="width:12px;height:12px;display:inline;margin-right:2px;vertical-align:-1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> ' + escHtml(j.compensation) + '</div>' : '')
            + dist + dirBtn + navBtn
            + '<a href="' + j.url + '" class="map-popup-link">ดูรายละเอียดทั้งหมด</a>'
        , { maxWidth: 280 });

        if (isHL) {
            marker.openPopup();
        }
        jobMarkers[j.id] = marker;
        bounds.push([j.lat, j.lng]);
    });

    if (userLat !== null && userLng !== null && !nav.active) {
        var meIcon = L.divIcon({ className: '', html: '<div style="width:16px;height:16px;background:#3b82f6;border:3px solid #fff;border-radius:50%;box-shadow:0 0 0 3px rgba(59,130,246,.35);"></div>', iconSize: [16, 16], iconAnchor: [8, 8] });
        meMarker = L.marker([userLat, userLng], { icon: meIcon, zIndexOffset: 1000 }).addTo(jobMap).bindPopup('<b>ตำแหน่งของคุณ</b>');
        bounds.push([userLat, userLng]);
    }

    if (highlightId && jobMarkers[highlightId]) {
        jobMap.setView(jobMarkers[highlightId].getLatLng(), 16);
    } else if (bounds.length > 1) {
        jobMap.fitBounds(bounds, { padding: [40, 40] });
    } else if (bounds.length === 1) {
        jobMap.setView(bounds[0], 15);
    }
    setTimeout(function() { jobMap.invalidateSize(); }, 200);
}

// ══════════════════════════════════════════
//  START Real-time Navigation
// ══════════════════════════════════════════
function startRealtimeNav(actId, destLat, destLng) {
    var act = geoJobs.find(function(a) { return a.id === actId; });
    if (!act) return;
    
    if (userLat === null || userLng === null) {
        if (navigator.geolocation) {
            document.getElementById('mapTitle').textContent = 'กำลังหาพิกัดของคุณ...';
            navigator.geolocation.getCurrentPosition(function(pos) {
                userLat = pos.coords.latitude;
                userLng = pos.coords.longitude;
                startRealtimeNav(actId, destLat, destLng);
            }, function() {
                alert('กรุณาอนุญาตเพื่อเข้าถึงตำแหน่งของคุณสำหรับการเริ่มคำนวณเส้นทาง');
                document.getElementById('mapTitle').textContent = 'แผนที่ประกาศงาน';
            }, { enableHighAccuracy: true, timeout: 5000 });
        } else {
            alert('เบราว์เซอร์ไม่รองรับ GPS');
        }
        return;
    }

    // ── ล้าง state การนำทางเก่า (กรณีเปลี่ยนกิจกรรมระหว่างนำทาง) ──
    stopGpsWatch();
    removeNavHUD();
    if (nav.routeLine) { jobMap.removeLayer(nav.routeLine); nav.routeLine = null; }
    if (nav.traveledLine) { jobMap.removeLayer(nav.traveledLine); nav.traveledLine = null; }
    if (routingControl) { jobMap.removeControl(routingControl); routingControl = null; }
    var arrivedEl = document.getElementById('navArrived');
    if (arrivedEl) arrivedEl.remove();
    matchBuffer = [];

    jobMap.closePopup();
    nav.active = true;
    nav.destLat = destLat;
    nav.destLng = destLng;
    nav.jobData = act;
    nav.currentStepIdx = 0;
    nav.offRouteCount = 0;
    nav.startTime = Date.now();
    nav.gpsLostSince = 0;
    nav.rerouteThrottle = 0;
    kalman.reset();

    // ซ่อนเส้นตรง
    jobLines.forEach(function(l) { l.setStyle({ opacity: 0 }); });
    jobDistLabels.forEach(function(l) { jobMap.removeLayer(l); });

    // เปลี่ยน UI
    document.getElementById('btnClearRoute').style.display = '';
    document.getElementById('btnClearRoute').textContent = '✕ หยุดนำทาง';
    document.getElementById('mapTitle').textContent = 'กำลังนำทาง...';

    // สร้าง user marker แบบใช้รูป navigation arrow
    if (meMarker) { jobMap.removeLayer(meMarker); meMarker = null; }
    var navIcon = L.icon({
        iconUrl: '/images/nav-arrow.png',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
        className: 'nav-arrow-icon'
    });
    meMarker = L.marker([userLat, userLng], { icon: navIcon, zIndexOffset: 2000, rotationAngle: 0 }).addTo(jobMap);

    // สร้าง HUD overlays
    createNavHUD();

    // Zoom เข้าไปตำแหน่งผู้ใช้
    jobMap.setView([userLat, userLng], 17, { animate: true, duration: 1 });

    // คำนวณเส้นทางแรก แล้วเริ่ม watchPosition
    fetchRoute(userLat, userLng, destLat, destLng, function() {
        startGpsWatch();
    });
}

// ══════════════════════════════════════
//  Fetch Route from OSRM
// ══════════════════════════════════════
function fetchRoute(fromLat, fromLng, toLat, toLng, cb) {
    var url = 'https://router.project-osrm.org/route/v1/driving/'
        + fromLng + ',' + fromLat + ';' + toLng + ',' + toLat
        + '?overview=full&geometries=geojson&steps=true';

    fetch(url).then(function(r) { return r.json(); }).then(function(data) {
        if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
            showNavError();
            return;
        }
        var route = data.routes[0];
        nav.routeCoords = route.geometry.coordinates.map(function(c) { return [c[1], c[0]]; });

        // Parse steps
        nav.routeSteps = [];
        route.legs[0].steps.forEach(function(step) {
            nav.routeSteps.push({
                text: step.maneuver.type.replace(/_/g, ' ') + (step.name ? ' — ' + step.name : ''),
                type: maneuverToIcon(step.maneuver.type, step.maneuver.modifier),
                distance: step.distance,
                duration: step.duration,
                coord: [step.maneuver.location[1], step.maneuver.location[0]]
            });
        });

        // วาดเส้นทาง
        if (nav.routeLine) jobMap.removeLayer(nav.routeLine);
        if (nav.traveledLine) jobMap.removeLayer(nav.traveledLine);
        nav.routeLine = L.polyline(nav.routeCoords, {
            color: '#4f46e5', weight: 6, opacity: 0.8
        }).addTo(jobMap);
        nav.traveledLine = L.polyline([], {
            color: '#94a3b8', weight: 6, opacity: 0.5
        }).addTo(jobMap);

        // แสดง directions panel
        var totalDist = route.legs[0].distance;
        var totalTime = route.legs[0].duration;
        showNavDirectionsPanel(totalDist, totalTime);

        jobMap.fitBounds(nav.routeLine.getBounds(), { padding: [60, 60] });
        setTimeout(function() { jobMap.invalidateSize(); }, 200);

        if (cb) cb();
    }).catch(function() {
        showNavError();
    });
}

function showNavError() {
    alert("ไม่สามารถคำนวณเส้นทางได้ กรุณาลองใหม่อีกครั้ง");
    document.getElementById('mapTitle').textContent = 'ข้อผิดพลาดในการคำนวณเส้นทาง';
    if (nav.instrEl) nav.instrEl.textContent = 'คำนวณเส้นทางล้มเหลว';
    stopGpsWatch();
}

// ══════════════════════════════════════
//  GPS Watch — adaptive frequency
// ══════════════════════════════════════
function startGpsWatch() {
    if (!navigator.geolocation || nav.watchId) return;
    nav.watchId = navigator.geolocation.watchPosition(
        onGpsUpdate, onGpsError,
        { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 }
    );
}

function stopGpsWatch() {
    if (nav.watchId !== null) {
        navigator.geolocation.clearWatch(nav.watchId);
        nav.watchId = null;
    }
}

function onGpsUpdate(pos) {
    var rawLat = pos.coords.latitude;
    var rawLng = pos.coords.longitude;
    var accuracy = pos.coords.accuracy || 20;
    var heading = pos.coords.heading;
    var speed = pos.coords.speed || 0;

    nav.lastGpsTime = Date.now();
    nav.gpsLostSince = 0;
    nav.speed = speed;

    // ซ่อน tunnel badge
    if (nav.tunnelEl) nav.tunnelEl.style.display = 'none';

    // Kalman filter
    var filtered = kalman.filter(rawLat, rawLng, accuracy);
    userLat = filtered.lat;
    userLng = filtered.lng;

    // Heading
    if (heading !== null && !isNaN(heading)) {
        nav.heading = heading;
    } else if (speed > 1) {
        // คำนวณ heading จาก 2 จุดล่าสุด
        var prev = meMarker ? meMarker.getLatLng() : null;
        if (prev) {
            nav.heading = calcBearing(prev.lat, prev.lng, userLat, userLng);
        }
    }

    // อัปเดต speed badge
    updateSpeedBadge(speed);

    // Adaptive GPS frequency
    adjustGpsFrequency(speed);

    // อัปเดต marker position (smooth animation)
    smoothMoveMarker(userLat, userLng, nav.heading);

    // ── Core navigation logic ──
    if (nav.active && nav.routeCoords.length > 0) {
        // หาจุดที่ใกล้ที่สุดบนเส้นทาง
        var snap = snapToRoute(userLat, userLng);

        // Off-route detection (>50m จากเส้นทาง)
        if (snap.dist > 50) {
            nav.offRouteCount++;
            if (nav.offRouteCount >= 3 && Date.now() - nav.rerouteThrottle > 5000) {
                // Re-route!
                nav.rerouteThrottle = Date.now();
                nav.offRouteCount = 0;
                reroute();
                return;
            }
        } else {
            nav.offRouteCount = 0;
        }

        // อัปเดต traveled line
        if (snap.idx >= 0) {
            var traveled = nav.routeCoords.slice(0, snap.idx + 1);
            traveled.push([userLat, userLng]);
            nav.traveledLine.setLatLngs(traveled);

            // อัปเดตเส้นที่เหลือ
            var remaining = [[userLat, userLng]].concat(nav.routeCoords.slice(snap.idx + 1));
            nav.routeLine.setLatLngs(remaining);
        }

        // อัปเดต current step
        updateCurrentStep(userLat, userLng);

        // อัปเดต HUD
        var remainDist = calcRemainingDist(snap.idx, userLat, userLng);
        var remainTime = estimateTime(remainDist, speed);
        updateHUD(remainDist, remainTime);

        // ตรวจสอบถึงจุดหมาย (<30m)
        var distToDest = haversine(userLat, userLng, nav.destLat, nav.destLng) * 1000;
        if (distToDest < 30) {
            onArrived();
        }
    }
}

function onGpsError(err) {
    // GPS lost — tunnel handling
    if (!nav.gpsLostSince) nav.gpsLostSince = Date.now();

    var lostMs = Date.now() - nav.gpsLostSince;
    if (nav.tunnelEl && lostMs > 3000) {
        nav.tunnelEl.style.display = '';
    }

    // Dead reckoning: estimate position from last known speed & heading
    if (nav.active && nav.speed > 0.5 && lostMs < 30000) {
        var dt = Math.min(lostMs / 1000, 5); // max 5s prediction
        var distM = nav.speed * dt;
        var newPos = destPoint(userLat, userLng, nav.heading, distM);
        userLat = newPos.lat;
        userLng = newPos.lng;
        smoothMoveMarker(userLat, userLng, nav.heading);
    }
}

// ══════════════════════════════════════
//  Map Matching via OSRM /match
// ══════════════════════════════════════
var matchBuffer = [];
var matchThrottle = 0;

function tryMapMatch(lat, lng, timestamp) {
    matchBuffer.push({ lat: lat, lng: lng, t: timestamp });
    if (matchBuffer.length < 3) return; // ต้องมีอย่างน้อย 3 จุด
    if (Date.now() - matchThrottle < 3000) return; // throttle 3 วิ
    matchThrottle = Date.now();

    var coords = matchBuffer.slice(-5).map(function(p) { return p.lng + ',' + p.lat; }).join(';');
    var timestamps = matchBuffer.slice(-5).map(function(p) { return Math.round(p.t / 1000); }).join(';');

    fetch('https://router.project-osrm.org/match/v1/driving/' + coords + '?timestamps=' + timestamps + '&geometries=geojson&overview=false')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 'Ok' && data.matchings && data.matchings.length > 0) {
                var lastTracepoint = data.tracepoints.filter(function(t) { return t !== null; }).pop();
                if (lastTracepoint) {
                    var matched = lastTracepoint.location;
                    userLat = matched[1];
                    userLng = matched[0];
                    smoothMoveMarker(userLat, userLng, nav.heading);
                }
            }
        }).catch(function() {});
}

// ══════════════════════════════════════
//  Re-routing
// ══════════════════════════════════════
function reroute() {
    if (!nav.active) return;
    document.getElementById('mapTitle').textContent = 'กำลังคำนวณเส้นทางใหม่...';
    fetchRoute(userLat, userLng, nav.destLat, nav.destLng, function() {
        document.getElementById('mapTitle').textContent = 'กำลังนำทาง...';
        nav.currentStepIdx = 0;
    });
}

// ══════════════════════════════════════
//  Snap to route & off-route detection
// ══════════════════════════════════════
function snapToRoute(lat, lng) {
    var minDist = Infinity, bestIdx = 0;
    for (var i = 0; i < nav.routeCoords.length; i++) {
        var d = haversine(lat, lng, nav.routeCoords[i][0], nav.routeCoords[i][1]) * 1000;
        if (d < minDist) { minDist = d; bestIdx = i; }
    }
    return { dist: minDist, idx: bestIdx };
}

// ══════════════════════════════════════
//  Turn-by-turn updates
// ══════════════════════════════════════
function updateCurrentStep(lat, lng) {
    if (!nav.routeSteps.length) return;
    // หา step ที่ใกล้ที่สุด
    for (var i = nav.currentStepIdx; i < nav.routeSteps.length; i++) {
        var step = nav.routeSteps[i];
        var d = haversine(lat, lng, step.coord[0], step.coord[1]) * 1000;
        if (d < 30 && i > nav.currentStepIdx) {
            nav.currentStepIdx = i;
            break;
        }
    }

    // อัปเดต instruction
    var nextIdx = Math.min(nav.currentStepIdx + 1, nav.routeSteps.length - 1);
    var nextStep = nav.routeSteps[nextIdx];
    if (nextStep && nav.instrEl) {
        var distToNext = haversine(lat, lng, nextStep.coord[0], nextStep.coord[1]) * 1000;
        var distStr = distToNext >= 1000 ? (distToNext/1000).toFixed(1) + ' กม.' : Math.round(distToNext) + ' ม.';
        nav.instrEl.innerHTML = nextStep.type + ' อีก ' + distStr + ' — ' + escHtml(nextStep.text);
    }

    // Highlight active step in panel
    var steps = document.querySelectorAll('.dir-step');
    steps.forEach(function(el, idx) {
        el.classList.toggle('dir-step-active', idx === nav.currentStepIdx);
    });
    // Auto-scroll
    var activeEl = document.querySelector('.dir-step-active');
    if (activeEl) activeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ══════════════════════════════════════
//  Smooth marker animation
// ══════════════════════════════════════
function smoothMoveMarker(lat, lng, heading) {
    if (!meMarker) return;
    var cur = meMarker.getLatLng();
    var frames = 10;
    var dLat = (lat - cur.lat) / frames;
    var dLng = (lng - cur.lng) / frames;
    var frame = 0;
    function step() {
        frame++;
        meMarker.setLatLng([cur.lat + dLat * frame, cur.lng + dLng * frame]);
        if (frame < frames) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);

    // Heading - หมุนรูป arrow icon
    if (heading !== null && !isNaN(heading) && meMarker.setRotationAngle) {
        meMarker.setRotationAngle(heading);
    } else if (heading !== null && !isNaN(heading)) {
        // Fallback: ใช้ CSS transform
        var icon = meMarker.getElement();
        if (icon) {
            icon.style.transform = 'rotate(' + heading + 'deg)';
            icon.style.transformOrigin = 'center center';
        }
    }

    // Follow user (map pan)
    if (nav.active) {
        jobMap.panTo([lat, lng], { animate: true, duration: 0.5 });
    }
}

// ══════════════════════════════════════
//  HUD
// ══════════════════════════════════════
function createNavHUD() {
    var container = document.getElementById('jobMapContainer');

    // HUD bar
    nav.hudEl = document.createElement('div');
    nav.hudEl.className = 'nav-hud';
    nav.hudEl.id = 'navHud';
    nav.hudEl.innerHTML = '<div class="nav-hud-item"><div class="nav-hud-value" id="hudDist">-</div><div class="nav-hud-label">ระยะทาง</div></div>'
        + '<div class="nav-hud-divider"></div>'
        + '<div class="nav-hud-item"><div class="nav-hud-value" id="hudEta">-</div><div class="nav-hud-label">ETA</div></div>'
        + '<div class="nav-hud-divider"></div>'
        + '<div class="nav-hud-item"><div class="nav-hud-value" id="hudTime">-</div><div class="nav-hud-label">ถึงโดยประมาณ</div></div>';
    container.appendChild(nav.hudEl);

    // Instruction bar
    nav.instrEl = document.createElement('div');
    nav.instrEl.className = 'nav-hud-instruction';
    nav.instrEl.id = 'navInstr';
    nav.instrEl.textContent = 'กำลังคำนวณ...';
    container.appendChild(nav.instrEl);

    // Tunnel badge
    nav.tunnelEl = document.createElement('div');
    nav.tunnelEl.className = 'nav-tunnel-badge';
    nav.tunnelEl.id = 'navTunnel';
    nav.tunnelEl.innerHTML = '<svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>สัญญาณ GPS อ่อน';
    container.appendChild(nav.tunnelEl);

    // Speed badge
    nav.speedEl = document.createElement('div');
    nav.speedEl.className = 'nav-speed-badge';
    nav.speedEl.id = 'navSpeed';
    nav.speedEl.innerHTML = '<div class="nav-speed-value">0</div><div class="nav-speed-unit">km/h</div>';
    container.appendChild(nav.speedEl);
    nav.speedEl.style.display = 'flex';
}

function removeNavHUD() {
    ['navHud', 'navInstr', 'navTunnel', 'navSpeed'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.remove();
    });
    nav.hudEl = null; nav.instrEl = null; nav.tunnelEl = null; nav.speedEl = null;
}

function updateHUD(remainDistM, remainTimeSec) {
    var distEl = document.getElementById('hudDist');
    var etaEl = document.getElementById('hudEta');
    var timeEl = document.getElementById('hudTime');
    if (!distEl) return;

    distEl.textContent = remainDistM >= 1000 ? (remainDistM/1000).toFixed(1) + ' กม.' : Math.round(remainDistM) + ' ม.';
    etaEl.textContent = formatTime(remainTimeSec);

    var arrival = new Date(Date.now() + remainTimeSec * 1000);
    timeEl.textContent = arrival.getHours().toString().padStart(2,'0') + ':' + arrival.getMinutes().toString().padStart(2,'0');

    // อัปเดต panel summary ด้วย
    var panelDist = document.querySelector('.dir-summary-value');
    if (panelDist) panelDist.textContent = distEl.textContent;
}

function updateSpeedBadge(speedMs) {
    var el = document.getElementById('navSpeed');
    if (!el) return;
    var kmh = Math.round(speedMs * 3.6);
    el.querySelector('.nav-speed-value').textContent = kmh;
}

// ══════════════════════════════════════
//  Adaptive GPS frequency (battery)
// ══════════════════════════════════════
function adjustGpsFrequency(speedMs) {
    // ความเร็วสูง → poll บ่อยขึ้น, ความเร็วต่ำ → poll น้อยลง
    // watchPosition ไม่ control ได้โดยตรง แต่เราใช้ processNoise ของ Kalman
    var kmh = speedMs * 3.6;
    if (kmh > 30) {
        kalman.processNoise = 0.00003; // responsive มากขึ้น
    } else if (kmh < 5) {
        kalman.processNoise = 0.000005; // smooth มากขึ้น ประหยัด battery
    } else {
        kalman.processNoise = 0.00001;
    }
}

// ══════════════════════════════════════
//  Directions Panel (for nav mode)
// ══════════════════════════════════════
function showNavDirectionsPanel(totalDist, totalTime) {
    var panel = document.getElementById('directionsPanel');
    panel.style.display = '';

    var distStr = totalDist >= 1000 ? (totalDist / 1000).toFixed(1) + ' กม.' : Math.round(totalDist) + ' ม.';
    var timeStr = formatTime(totalTime);
    var act = nav.jobData;

    var gUrl = 'https://www.google.com/maps/dir/?api=1&origin=' + userLat + ',' + userLng + '&destination=' + nav.destLat + ',' + nav.destLng;
    var aUrl = 'https://maps.apple.com/?saddr=' + userLat + ',' + userLng + '&daddr=' + nav.destLat + ',' + nav.destLng;

    var html = '<div class="dir-header">'
        + '<h3><svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>' + escHtml(act.title) + '</h3>'
        + '<div class="dir-summary">'
        + '<div class="dir-summary-item"><div class="dir-summary-value">' + distStr + '</div><div class="dir-summary-label">ระยะทาง</div></div>'
        + '<div class="dir-summary-item"><div class="dir-summary-value">' + timeStr + '</div><div class="dir-summary-label">เวลาเดินทาง</div></div>'
        + '</div>'
        + '<div class="dir-ext-links">'
        + '<a href="' + gUrl + '" target="_blank" class="map-dir-btn map-dir-google">เปิด Google Maps</a>'
        + '<a href="' + aUrl + '" target="_blank" class="map-dir-btn map-dir-apple">เปิด Apple Maps</a>'
        + '</div></div>';

    if (nav.routeSteps.length > 0) {
        html += '<ul class="dir-steps">';
        nav.routeSteps.forEach(function(step, i) {
            var stepDist = step.distance >= 1000 ? (step.distance / 1000).toFixed(1) + ' กม.' : Math.round(step.distance) + ' ม.';
            html += '<li class="dir-step' + (i === 0 ? ' dir-step-active' : '') + '">'
                + '<span class="dir-step-icon">' + step.type + '</span>'
                + '<div class="dir-step-text">' + escHtml(step.text) + '<div class="dir-step-dist">' + stepDist + '</div></div>'
                + '</li>';
        });
        html += '</ul>';
    }
    panel.innerHTML = html;
    setTimeout(function() { jobMap.invalidateSize(); }, 200);
}


// ══════════════════════════════════════
//  Arrived
// ══════════════════════════════════════
function onArrived() {
    stopGpsWatch();
    removeNavHUD();
    var container = document.getElementById('jobMapContainer');
    var el = document.createElement('div');
    el.className = 'nav-arrived';
    el.id = 'navArrived';
    el.innerHTML = '<h2><svg class="icon-sm" style="display:inline;vertical-align:-2px;margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>ถึงจุดหมายแล้ว!</h2>'
        + '<p style="color:#64748b;font-size:.85rem;margin-bottom:1rem;">' + escHtml(nav.jobData.title) + '</p>'
        + '<a href="' + nav.jobData.url + '" class="map-popup-link" style="font-size:.9rem;padding:8px 20px;">ดูรายละเอียดกิจกรรม</a>'
        + '<br><button onclick="clearRoute()" style="margin-top:.75rem;padding:6px 16px;border:1px solid #e2e8f0;background:#fff;border-radius:8px;cursor:pointer;font-size:.85rem;">กลับแผนที่</button>';
    container.appendChild(el);
}

// ══════════════════════════════════════
//  Clear / Stop Navigation
// ══════════════════════════════════════
function clearRoute() {
    stopGpsWatch();
    nav.active = false;
    isRouting = false;
    kalman.reset();
    matchBuffer = [];

    if (routingControl) { jobMap.removeControl(routingControl); routingControl = null; }
    if (nav.routeLine) { jobMap.removeLayer(nav.routeLine); nav.routeLine = null; }
    if (nav.traveledLine) { jobMap.removeLayer(nav.traveledLine); nav.traveledLine = null; }

    removeNavHUD();
    var arrived = document.getElementById('navArrived');
    if (arrived) arrived.remove();

    document.getElementById('directionsPanel').style.display = 'none';
    document.getElementById('directionsPanel').innerHTML = '';
    document.getElementById('btnClearRoute').style.display = 'none';
    document.getElementById('btnClearRoute').textContent = '✕ หยุดนำทาง';
    document.getElementById('mapTitle').textContent = 'แผนที่ประกาศงาน';

    buildMarkers();
}

// ══════════════════════════════════════
//  Helper functions
// ══════════════════════════════════════
function calcRemainingDist(snapIdx, lat, lng) {
    var total = 0;
    if (nav.routeCoords.length === 0) return 0;
    // distance from current pos to snap point
    total += haversine(lat, lng, nav.routeCoords[snapIdx][0], nav.routeCoords[snapIdx][1]) * 1000;
    // distance along remaining route
    for (var i = snapIdx; i < nav.routeCoords.length - 1; i++) {
        total += haversine(nav.routeCoords[i][0], nav.routeCoords[i][1], nav.routeCoords[i+1][0], nav.routeCoords[i+1][1]) * 1000;
    }
    return total;
}

function estimateTime(distM, speedMs) {
    if (speedMs > 1) return distM / speedMs;
    // default ~30km/h
    return distM / 8.33;
}

function calcBearing(lat1, lng1, lat2, lng2) {
    var dLng = (lng2 - lng1) * Math.PI / 180;
    var y = Math.sin(dLng) * Math.cos(lat2 * Math.PI / 180);
    var x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180)
          - Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLng);
    return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
}

function destPoint(lat, lng, bearing, distM) {
    var R = 6371000;
    var d = distM / R;
    var br = bearing * Math.PI / 180;
    var lat1 = lat * Math.PI / 180;
    var lng1 = lng * Math.PI / 180;
    var lat2 = Math.asin(Math.sin(lat1) * Math.cos(d) + Math.cos(lat1) * Math.sin(d) * Math.cos(br));
    var lng2 = lng1 + Math.atan2(Math.sin(br) * Math.sin(d) * Math.cos(lat1), Math.cos(d) - Math.sin(lat1) * Math.sin(lat2));
    return { lat: lat2 * 180 / Math.PI, lng: lng2 * 180 / Math.PI };
}

function maneuverToIcon(type, modifier) {
    // SVG icons for navigation directions
    var icons = {
        'straight': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>',
        'left': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>',
        'slight left': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/></svg>',
        'sharp left': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>',
        'right': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>',
        'slight right': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>',
        'sharp right': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10H11a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"/></svg>',
        'uturn': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>',
        'depart': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>',
        'arrive': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'merge': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
        'fork': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
        'roundabout': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
        'default': '<svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>'
    };
    
    // Check modifier first
    if (modifier && icons[modifier]) return icons[modifier];
    // Check type
    if (icons[type]) return icons[type];
    // Default
    return icons['default'];
}

function createCustomIcon(a, isHighlight) {
    var hlClass = isHighlight ? ' map-marker-highlight' : '';
    var html;
    if (a.image) {
        html = '<div class="' + hlClass + '"><img src="' + a.image + '" class="map-marker-img"></div>';
    } else {
        html = '<div class="' + hlClass + '"><span class="map-marker-name">' + escHtml(a.title) + '</span></div>';
    }
    return L.divIcon({
        className: '', html: html,
        iconSize: a.image ? [44, 44] : [100, 24],
        iconAnchor: a.image ? [22, 22] : [50, 12],
        popupAnchor: [0, a.image ? -26 : -16]
    });
}

function closeJobMap() {
    clearRoute();
    document.getElementById('jobMapModal').style.display = 'none';
    document.body.style.overflow = '';
}

function haversine(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2)
          + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
          * Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDist(km) {
    if (km < 1) return Math.round(km * 1000) + ' เมตร';
    return km.toFixed(1) + ' กม.';
}

function formatTime(seconds) {
    if (seconds < 60) return '< 1 นาที';
    var mins = Math.round(seconds / 60);
    if (mins < 60) return mins + ' นาที';
    var hrs = Math.floor(mins / 60);
    var rm = mins % 60;
    return hrs + ' ชม.' + (rm > 0 ? ' ' + rm + ' น.' : '');
}

function escHtml(t) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(t || ''));
    return d.innerHTML;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeJobMap();
});

// ══════════════════════════════════════
//  Lazy Loading Images
// ══════════════════════════════════════
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
        // Fallback for browsers without IntersectionObserver
        lazyImages.forEach(function(img) {
            var src = img.getAttribute('data-src');
            if (src) {
                img.src = src;
                img.removeAttribute('data-src');
            }
        });
    }

    // Auto-open map and start navigation if redirected from job detail page
    const urlParams = new URLSearchParams(window.location.search);
    const showMapObj = urlParams.get('showMap');
    const autoNavObj = urlParams.get('autoNav');
    
    if (showMapObj) {
        var mapId = parseInt(showMapObj);
        setTimeout(() => {
            openJobMap(mapId);
            if (autoNavObj) {
                setTimeout(() => {
                    var targetJob = geoJobs.find(function(j) { return j.id === mapId; });
                    if (targetJob) {
                        startRealtimeNav(targetJob.id, targetJob.lat, targetJob.lng);
                    }
                }, 1000); // Wait for map and markers to build
            }
        }, 300); // Slight delay for smoother UI load
    }
});
</script>
@endsection
