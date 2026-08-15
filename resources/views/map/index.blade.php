@extends('layouts.app')
@section('title', 'แผนที่กิจกรรมและงาน - UNI Activity')

@section('content')
<div class="map-explorer-wrapper">
    {{-- Top Header & Filters --}}
    <div class="map-top-bar">
        <div class="flex items-center justify-between mb-2" style="flex-wrap:wrap;gap:8px;">
            <div class="flex items-center gap-2">
                <div class="map-title-icon">
                    <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                </div>
                <div>
                    <h1 class="font-bold" style="font-size:1.15rem;margin:0;line-height:1.2;">แผนที่กิจกรรมและงาน</h1>
                    <span class="text-xs text-muted" id="total-count-label">กำลังโหลดข้อมูลสถานที่...</span>
                </div>
            </div>

            {{-- Mode Switchers --}}
            <div class="flex items-center gap-1">
                <button type="button" class="map-mode-btn active" id="btn-layer-streets" onclick="switchMapLayer('streets')" title="แผนที่ปกติ">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <span>ปกติ</span>
                </button>
                <button type="button" class="map-mode-btn" id="btn-layer-satellite" onclick="switchMapLayer('satellite')" title="ภาพถ่ายดาวเทียม">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>ดาวเทียม</span>
                </button>
                <button type="button" class="map-mode-btn" id="btn-layer-heat" onclick="toggleHeatmap()" title="แผนที่ความหนาแน่น">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/></svg>
                    <span>ความหนาแน่น</span>
                </button>
            </div>
        </div>

        {{-- Type Category Filter Pills --}}
        <div class="sort-scroll-container mb-2">
            <button type="button" class="map-filter-pill active" data-type="all" onclick="filterType('all', this)">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <span>ทั้งหมด</span>
            </button>
            <button type="button" class="map-filter-pill" data-type="activity" onclick="filterType('activity', this)">
                <span class="type-dot dot-orange"></span>
                <span>กิจกรรมมหาวิทยาลัย</span>
            </button>
            <button type="button" class="map-filter-pill" data-type="job" onclick="filterType('job', this)">
                <span class="type-dot dot-blue"></span>
                <span>หางาน / Part-time</span>
            </button>
            <button type="button" class="map-filter-pill" data-type="landmark" onclick="filterType('landmark', this)">
                <span class="type-dot dot-green"></span>
                <span>จุดสำคัญในมหาวิทยาลัย</span>
            </button>
        </div>

        {{-- Radius Radar Filter Pills --}}
        <div class="sort-scroll-container">
            <span class="text-xs text-muted font-bold" style="display:inline-flex;align-items:center;gap:4px;white-space:nowrap;margin-right:2px;">
                <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                รัศมี:
            </span>
            <button type="button" class="map-radius-pill active" data-radius="0" onclick="filterRadius(0, this)">
                <span>ไม่จำกัด</span>
            </button>
            <button type="button" class="map-radius-pill" data-radius="2" onclick="filterRadius(2, this)">
                <span>&lt; 2 กม.</span>
            </button>
            <button type="button" class="map-radius-pill" data-radius="5" onclick="filterRadius(5, this)">
                <span>&lt; 5 กม.</span>
            </button>
            <button type="button" class="map-radius-pill" data-radius="10" onclick="filterRadius(10, this)">
                <span>&lt; 10 กม.</span>
            </button>
            <button type="button" class="map-radius-pill" data-radius="25" onclick="filterRadius(25, this)">
                <span>&lt; 25 กม.</span>
            </button>
        </div>
    </div>

    {{-- Map Area Container --}}
    <div class="map-canvas-container">
        <div id="unifiedMap" style="width:100%;height:100%;"></div>

        {{-- Floating GPS Locate Button --}}
        <button type="button" class="map-locate-btn" onclick="locateUserAndCenter()" title="ระบุตำแหน่งของฉัน">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </button>

        {{-- Routing Turn-by-Turn Panel --}}
        <div id="mapDirectionsPanel" class="map-directions-panel" style="display:none;">
            <div class="map-dir-header">
                <div class="flex items-center gap-2">
                    <svg style="width:16px;height:16px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <span class="font-bold" style="font-size:0.9rem;">เส้นทางนำทาง</span>
                </div>
                <button type="button" onclick="clearNavigationRoute()" class="btn-close-route">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="routeStepsList" class="map-dir-body"></div>
        </div>

        {{-- BottomSheet Preview Card (Apple / Google Maps Style) --}}
        <div id="mapBottomSheet" class="map-bottom-sheet" style="display:none;">
            <div class="bs-handle" onclick="toggleBottomSheetExpand()"></div>
            <div class="bs-content">
                <button type="button" class="bs-close-btn" onclick="closeBottomSheet()">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <div class="flex gap-3 items-start">
                    <div id="bs-img-wrap" class="bs-thumb">
                        <img id="bs-img" src="" alt="Thumbnail" style="display:none;">
                        <div id="bs-icon-fallback" class="bs-icon-fallback">
                            <svg id="bs-fallback-svg" style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        </div>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="flex items-center gap-2 mb-1">
                            <span id="bs-badge" class="badge badge-orange"></span>
                            <span id="bs-distance" class="bs-distance-pill"></span>
                        </div>
                        <h2 id="bs-title" class="bs-title"></h2>
                        <p id="bs-subtitle" class="bs-subtitle"></p>
                    </div>
                </div>

                {{-- ETA Time & Meta Info --}}
                <div class="bs-meta-grid mt-3">
                    <div class="bs-meta-card">
                        <div class="bs-meta-label">
                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span>เดินเท้า</span>
                        </div>
                        <span id="bs-walk-eta" class="bs-meta-val">-</span>
                    </div>
                    <div class="bs-meta-card">
                        <div class="bs-meta-label">
                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            <span>ขับขี่</span>
                        </div>
                        <span id="bs-drive-eta" class="bs-meta-val">-</span>
                    </div>
                    <div class="bs-meta-card" style="grid-column: span 2;">
                        <div class="bs-meta-label">
                            <svg style="width:13px;height:13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>ข้อมูลเพิ่มเติม</span>
                        </div>
                        <span id="bs-meta-info" class="bs-meta-val">-</span>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="bs-actions mt-3">
                    <a id="bs-detail-btn" href="#" class="btn btn-primary btn-sm flex-1">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>ดูรายละเอียด</span>
                    </a>
                    <button type="button" id="bs-route-btn" onclick="startNavigationToActive()" class="btn btn-outline btn-sm flex-1">
                        <svg style="width:14px;height:14px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <span>นำทางในระบบ</span>
                    </button>
                </div>

                {{-- External Navigation Apps --}}
                <div class="flex gap-2 mt-2">
                    <a id="bs-gmaps-btn" href="#" target="_blank" rel="noopener noreferrer" class="bs-app-btn flex-1">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span>Google Maps</span>
                    </a>
                    <a id="bs-applemaps-btn" href="#" target="_blank" rel="noopener noreferrer" class="bs-app-btn flex-1">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <span>Apple Maps</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- Leaflet, Routing, Heatmap, MarkerCluster --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>

<style>
/* ── Unified Map Explorer Layout ── */
.map-explorer-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 120px);
    min-height: 520px;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    position: relative;
}
@media (max-width: 768px) {
    .map-explorer-wrapper {
        height: calc(100vh - 140px);
        min-height: 480px;
        margin: -0.5rem -1rem;
        border-radius: 0;
        border: none;
    }
}
.map-top-bar {
    padding: 0.75rem 1rem;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    z-index: 10;
}
.map-title-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(234, 88, 12, 0.1);
    color: #ea580c;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.map-canvas-container {
    flex: 1;
    position: relative;
    overflow: hidden;
}

/* ── Mode Switchers ── */
.map-mode-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}
.map-mode-btn:hover { background: #f8fafc; color: #0f172a; }
.map-mode-btn.active {
    background: #0f172a;
    color: #fff;
    border-color: #0f172a;
}

/* ── Type & Radius Pills ── */
.map-filter-pill, .map-radius-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #475569;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.map-filter-pill:hover, .map-radius-pill:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.map-filter-pill.active {
    background: #ea580c;
    color: #fff;
    border-color: #ea580c;
    box-shadow: 0 2px 6px rgba(234, 88, 12, 0.25);
}
.map-radius-pill.active {
    background: #0284c7;
    color: #fff;
    border-color: #0284c7;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
}
.type-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot-orange { background: #ea580c; }
.dot-blue { background: #0284c7; }
.dot-green { background: #16a34a; }

/* ── Floating Locate Button ── */
.map-locate-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    z-index: 500;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #fff;
    color: #0284c7;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}
.map-locate-btn:hover {
    transform: scale(1.08);
    background: #f0f9ff;
}

/* ── Custom Markers ── */
.custom-pin-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    color: #fff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
    border: 2.5px solid #fff;
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.custom-pin-marker:hover {
    transform: scale(1.2);
}
.pin-activity { background: linear-gradient(135deg, #ea580c, #f97316); }
.pin-job { background: linear-gradient(135deg, #0284c7, #38bdf8); }
.pin-landmark { background: linear-gradient(135deg, #16a34a, #4ade80); }

/* Pulse User GPS Dot */
.user-gps-dot {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #0284c7;
    border: 3px solid #fff;
    box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.4);
    animation: gps-pulse 2s infinite;
}
@keyframes gps-pulse {
    0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7); }
    70% { box-shadow: 0 0 0 14px rgba(2, 132, 199, 0); }
    100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
}

/* ── BottomSheet Card (Apple Maps style) ── */
.map-bottom-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 600;
    background: #fff;
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -6px 24px rgba(0,0,0,0.16);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 80%;
    overflow-y: auto;
}
.bs-handle {
    width: 36px;
    height: 4px;
    background: #cbd5e1;
    border-radius: 2px;
    margin: 8px auto 0;
    cursor: pointer;
}
.bs-content {
    padding: 0.875rem 1.25rem 1.25rem;
    position: relative;
}
.bs-close-btn {
    position: absolute;
    top: 10px;
    right: 12px;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.bs-thumb {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    flex-shrink: 0;
    background: #f1f5f9;
}
.bs-thumb img { width: 100%; height: 100%; object-fit: cover; }
.bs-icon-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(234, 88, 12, 0.1);
    color: #ea580c;
}
.bs-title { font-size: 1rem; font-weight: 700; margin: 0 0 2px; line-height: 1.3; }
.bs-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }
.bs-distance-pill {
    font-size: 0.72rem;
    font-weight: 700;
    color: #0284c7;
    background: #e0f2fe;
    padding: 2px 8px;
    border-radius: 12px;
}
.bs-meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
}
.bs-meta-card {
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 8px;
    padding: 6px 10px;
}
.bs-meta-label {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    color: #64748b;
    margin-bottom: 2px;
}
.bs-meta-val { font-size: 0.82rem; font-weight: 700; color: #1e293b; }
.bs-actions { display: flex; gap: 8px; }
.bs-app-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    text-decoration: none;
    transition: all 0.15s ease;
}
.bs-app-btn:hover { background: #e2e8f0; text-decoration: none; color: #0f172a; }

/* ── Turn-by-Turn Routing Panel ── */
.map-directions-panel {
    position: absolute;
    top: 16px;
    left: 16px;
    z-index: 550;
    width: 320px;
    max-width: calc(100% - 32px);
    max-height: 60%;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.18);
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.map-dir-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #fafafa;
}
.btn-close-route {
    background: none;
    border: none;
    cursor: pointer;
    color: #64748b;
    padding: 2px;
}
.map-dir-body {
    padding: 10px 14px;
    overflow-y: auto;
    font-size: 0.8rem;
}

/* ── Dark Mode ── */
@media (prefers-color-scheme: dark) {
    .map-explorer-wrapper, .map-top-bar, .map-bottom-sheet, .map-directions-panel, .map-locate-btn {
        background: #202124 !important;
        border-color: #3c4043 !important;
        color: #f1f5f9 !important;
    }
    .map-mode-btn, .map-filter-pill, .map-radius-pill {
        background: #303134 !important;
        border-color: #5f6368 !important;
        color: #e8eaed !important;
    }
    .bs-meta-card, .bs-app-btn, .map-dir-header {
        background: #303134 !important;
        border-color: #3c4043 !important;
        color: #e8eaed !important;
    }
    .bs-meta-val { color: #f8fafc !important; }
}
</style>

<script>
(function() {
    let map = null;
    let markersCluster = null;
    let heatLayer = null;
    let routingControl = null;
    let userMarker = null;
    let radiusCircle = null;
    let userCoords = null; // [lat, lng]

    let allLocations = [];
    let currentFilterType = 'all';
    let currentRadiusKm = 0;
    let activeLocation = null;
    let activeMapLayerType = 'streets';

    const defaultCenter = [16.4745, 102.8235]; // Campus center

    // Tile Layers
    const streetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    });
    const satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri, Earthstar Geographics',
        maxZoom: 19
    });

    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        fetchLocations();
        getUserLocation();
    });

    function initMap() {
        map = L.map('unifiedMap', {
            center: defaultCenter,
            zoom: 14,
            zoomControl: false,
            layers: [streetTile]
        });

        L.control.zoom({ position: 'topright' }).addTo(map);

        markersCluster = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 40,
            iconCreateFunction: function(cluster) {
                const count = cluster.getChildCount();
                return L.divIcon({
                    html: '<div style="background:#ea580c;color:#fff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;border:2.5px solid #fff;box-shadow:0 3px 8px rgba(0,0,0,0.3);">' + count + '</div>',
                    className: 'custom-cluster-icon',
                    iconSize: [34, 34]
                });
            }
        });
        map.addLayer(markersCluster);

        map.on('click', function(e) {
            // Close bottomsheet on blank map tap
            if (e.originalEvent.target.id === 'unifiedMap' || e.originalEvent.target.classList.contains('leaflet-container')) {
                closeBottomSheet();
            }
        });
    }

    // Layer Switcher
    window.switchMapLayer = function(layerType) {
        activeMapLayerType = layerType;
        document.getElementById('btn-layer-streets').classList.toggle('active', layerType === 'streets');
        document.getElementById('btn-layer-satellite').classList.toggle('active', layerType === 'satellite');

        if (layerType === 'satellite') {
            map.removeLayer(streetTile);
            map.addLayer(satelliteTile);
        } else {
            map.removeLayer(satelliteTile);
            map.addLayer(streetTile);
        }
    };

    // Toggle Density Heatmap
    window.toggleHeatmap = function() {
        const btn = document.getElementById('btn-layer-heat');
        const isActive = btn.classList.toggle('active');

        if (isActive) {
            const heatPoints = allLocations.map(loc => [loc.lat, loc.lng, loc.type === 'activity' ? 0.8 : 0.6]);
            if (!heatLayer) {
                heatLayer = L.heatLayer(heatPoints, { radius: 25, blur: 15, maxZoom: 17 });
            }
            map.addLayer(heatLayer);
            map.removeLayer(markersCluster);
        } else {
            if (heatLayer) map.removeLayer(heatLayer);
            map.addLayer(markersCluster);
        }
    };

    // Fetch All Locations from Backend
    function fetchLocations() {
        fetch('{{ route("api.map.locations") }}')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allLocations = [
                        ...(data.activities || []),
                        ...(data.jobs || []),
                        ...(data.landmarks || [])
                    ];
                    document.getElementById('total-count-label').textContent = 'พบ ' + allLocations.length + ' จุดสถานที่';
                    renderMarkers();
                }
            })
            .catch(err => {
                console.error('Failed to load map locations:', err);
                document.getElementById('total-count-label').textContent = 'ไม่สามารถโหลดข้อมูลสถานที่ได้';
            });
    }

    // Render Markers with Filters
    function renderMarkers() {
        markersCluster.clearLayers();

        const filtered = allLocations.filter(item => {
            // Type Filter
            if (currentFilterType !== 'all' && item.type !== currentFilterType) {
                return false;
            }
            // Radius Filter
            if (currentRadiusKm > 0 && userCoords) {
                const distKm = calculateDistance(userCoords[0], userCoords[1], item.lat, item.lng);
                if (distKm > currentRadiusKm) return false;
            }
            return true;
        });

        filtered.forEach(loc => {
            let pinClass = 'pin-activity';
            let iconSvg = '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';

            if (loc.type === 'job') {
                pinClass = 'pin-job';
                iconSvg = '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
            } else if (loc.type === 'landmark') {
                pinClass = 'pin-landmark';
                iconSvg = '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>';
            }

            const customIcon = L.divIcon({
                html: `<div class="custom-pin-marker ${pinClass}">${iconSvg}</div>`,
                className: 'leaflet-pin-wrap',
                iconSize: [38, 38],
                iconAnchor: [19, 19]
            });

            const marker = L.marker([loc.lat, loc.lng], { icon: customIcon });
            marker.on('click', () => showBottomSheet(loc));
            markersCluster.addLayer(marker);
        });

        // Fit bounds if markers exist
        if (filtered.length > 0 && currentRadiusKm === 0) {
            const group = L.featureGroup(filtered.map(l => L.marker([l.lat, l.lng])));
            map.fitBounds(group.getBounds().pad(0.15));
        }
    }

    // Filter by Type
    window.filterType = function(type, btn) {
        currentFilterType = type;
        document.querySelectorAll('.map-filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderMarkers();
    };

    // Filter by Radius
    window.filterRadius = function(radiusKm, btn) {
        currentRadiusKm = radiusKm;
        document.querySelectorAll('.map-radius-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        if (radiusKm > 0) {
            if (!userCoords) {
                getUserLocation(() => updateRadiusCircle(radiusKm));
            } else {
                updateRadiusCircle(radiusKm);
            }
        } else {
            if (radiusCircle) map.removeLayer(radiusCircle);
            renderMarkers();
        }
    };

    function updateRadiusCircle(radiusKm) {
        if (!userCoords) return;
        if (radiusCircle) map.removeLayer(radiusCircle);

        radiusCircle = L.circle(userCoords, {
            radius: radiusKm * 1000,
            color: '#0284c7',
            fillColor: '#0284c7',
            fillOpacity: 0.08,
            weight: 2,
            dashArray: '6, 6'
        }).addTo(map);

        map.fitBounds(radiusCircle.getBounds());
        renderMarkers();
    }

    // Get User GPS Location
    function getUserLocation(callback) {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                userCoords = [pos.coords.latitude, pos.coords.longitude];
                setUserGpsMarker();
                if (callback) callback();
            },
            function(err) {
                console.warn('GPS location access denied or unavailable:', err);
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    }

    function setUserGpsMarker() {
        if (!userCoords) return;
        if (userMarker) map.removeLayer(userMarker);

        const gpsIcon = L.divIcon({
            html: '<div class="user-gps-dot"></div>',
            className: 'user-gps-wrap',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        userMarker = L.marker(userCoords, { icon: gpsIcon, zIndexOffset: 1000 }).addTo(map);
    }

    window.locateUserAndCenter = function() {
        getUserLocation(() => {
            if (userCoords) {
                map.setView(userCoords, 16, { animate: true });
            }
        });
    };

    // BottomSheet Handlers
    function showBottomSheet(loc) {
        activeLocation = loc;
        const sheet = document.getElementById('mapBottomSheet');

        // Thumbnail
        const img = document.getElementById('bs-img');
        const fallback = document.getElementById('bs-icon-fallback');
        if (loc.image) {
            img.src = loc.image;
            img.style.display = 'block';
            fallback.style.display = 'none';
        } else {
            img.style.display = 'none';
            fallback.style.display = 'flex';
        }

        // Title & Badges
        document.getElementById('bs-title').textContent = loc.title;
        document.getElementById('bs-subtitle').textContent = loc.subtitle + ' • ' + loc.location_name;

        const badge = document.getElementById('bs-badge');
        badge.textContent = loc.badge;
        badge.className = 'badge ' + (loc.badge_class || 'badge-orange');

        // Distance & ETAs
        let distText = '-';
        let walkText = '-';
        let driveText = '-';

        if (userCoords) {
            const distKm = calculateDistance(userCoords[0], userCoords[1], loc.lat, loc.lng);
            if (distKm < 1) {
                distText = Math.round(distKm * 1000) + ' ม.';
            } else {
                distText = distKm.toFixed(1) + ' กม.';
            }
            const walkMins = Math.round((distKm / 4.8) * 60);
            const driveMins = Math.max(1, Math.round((distKm / 35) * 60));
            walkText = '~' + walkMins + ' นาที';
            driveText = '~' + driveMins + ' นาที';
        }

        document.getElementById('bs-distance').textContent = distText;
        document.getElementById('bs-walk-eta').textContent = walkText;
        document.getElementById('bs-drive-eta').textContent = driveText;
        document.getElementById('bs-meta-info').textContent = loc.meta_info || loc.location_name;

        // Detail Button
        const detailBtn = document.getElementById('bs-detail-btn');
        if (loc.detail_url) {
            detailBtn.href = loc.detail_url;
            detailBtn.style.display = 'inline-flex';
        } else {
            detailBtn.style.display = 'none';
        }

        // Navigation Apps Links
        document.getElementById('bs-gmaps-btn').href = `https://www.google.com/maps/dir/?api=1&destination=${loc.lat},${loc.lng}`;
        document.getElementById('bs-applemaps-btn').href = `https://maps.apple.com/?daddr=${loc.lat},${loc.lng}`;

        sheet.style.display = 'block';
    }

    window.closeBottomSheet = function() {
        document.getElementById('mapBottomSheet').style.display = 'none';
        activeLocation = null;
    };

    window.toggleBottomSheetExpand = function() {
        const sheet = document.getElementById('mapBottomSheet');
        sheet.classList.toggle('expanded');
    };

    // In-App Turn-by-Turn Navigation (Leaflet Routing Machine)
    window.startNavigationToActive = function() {
        if (!activeLocation) return;
        if (!userCoords) {
            alert('กรุณาเปิด GPS เพื่อเริ่มต้นนำทาง');
            getUserLocation(() => startNavigationToActive());
            return;
        }

        closeBottomSheet();
        if (routingControl) map.removeControl(routingControl);

        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(userCoords[0], userCoords[1]),
                L.latLng(activeLocation.lat, activeLocation.lng)
            ],
            routeWhileDragging: false,
            showAlternatives: false,
            addWaypoints: false,
            createMarker: function() { return null; },
            lineOptions: {
                styles: [{ color: '#ea580c', weight: 6, opacity: 0.85 }]
            }
        }).addTo(map);

        const dirPanel = document.getElementById('mapDirectionsPanel');
        const stepsList = document.getElementById('routeStepsList');
        dirPanel.style.display = 'flex';

        routingControl.on('routesfound', function(e) {
            const routes = e.routes;
            const summary = routes[0].summary;
            const totalDistKm = (summary.totalDistance / 1000).toFixed(1);
            const totalTimeMin = Math.round(summary.totalTime / 60);

            let html = `<div style="margin-bottom:8px;font-weight:700;color:#ea580c;">ระยะทาง ${totalDistKm} กม. (~${totalTimeMin} นาที)</div><ol style="padding-left:18px;margin:0;line-height:1.6;">`;
            routes[0].instructions.forEach(step => {
                html += `<li>${step.text} <span class="text-muted" style="font-size:0.75rem;">(${Math.round(step.distance)} ม.)</span></li>`;
            });
            html += '</ol>';
            stepsList.innerHTML = html;
        });
    };

    window.clearNavigationRoute = function() {
        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
        }
        document.getElementById('mapDirectionsPanel').style.display = 'none';
    };

    // Haversine Distance Formula (km)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }
})();
</script>
@endsection
