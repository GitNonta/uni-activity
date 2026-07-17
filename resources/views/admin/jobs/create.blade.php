{{-- Admin: สร้างประกาศงานใหม่ --}}
@extends('layouts.admin')
@section('title', 'สร้างประกาศงาน')

@section('content')
<a href="{{ route('admin.jobs.index') }}" class="text-sm text-primary mb-2" style="display:inline-block;">&larr; กลับรายการ</a>
<h1 class="font-bold mb-4 flex items-center gap-2" style="font-size:1.25rem;">
    <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    สร้างประกาศงานใหม่
</h1>

@if($errors->any())
<div class="alert alert-error">
    <ul style="margin:.25rem 0;padding-left:1rem;">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.jobs.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">หัวข้อพาดหัวงาน *</label>
                <input type="text" name="title" value="{{ old('title') }}" class="form-control" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ประเภทงาน *</label>
                    <select name="job_type" class="form-control" required>
                        <option value="general" {{ old('job_type') == 'general' ? 'selected' : '' }}>งานทั่วไป</option>
                        <option value="parttime" {{ old('job_type') == 'parttime' ? 'selected' : '' }}>Part-time</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ตำแหน่งงาน *</label>
                    <input type="text" name="position" value="{{ old('position') }}" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">จำนวนรับสมัคร *</label>
                    <input type="number" name="quota" value="{{ old('quota', 1) }}" min="1" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ค่าตอบแทน</label>
                    <input type="text" name="compensation" value="{{ old('compensation') }}" class="form-control" placeholder="เช่น 400 บาท/วัน">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ช่วงเวลางาน</label>
                    <input type="text" name="work_period" value="{{ old('work_period') }}" class="form-control" placeholder="เช่น 08:00 – 17:00 น.">
                </div>
                <div class="form-group">
                    <label class="form-label">การแต่งกาย</label>
                    <input type="text" name="dresscode" value="{{ old('dresscode') }}" class="form-control" placeholder="เช่น ชุดสุภาพ">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เพศที่รับสมัคร *</label>
                    <select name="gender" class="form-control" required>
                        <option value="any" {{ old('gender') == 'any' ? 'selected' : '' }}>ไม่จำกัดเพศ</option>
                        <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>ชาย</option>
                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>หญิง</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">สถานที่ปฏิบัติงาน *</label>
                    <input type="text" name="location" value="{{ old('location') }}" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">วันเริ่มงาน *</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">วันสิ้นสุดงาน</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">รายละเอียดงาน</label>
                <textarea name="description" rows="4" class="form-control">{{ old('description') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">หมายเหตุ (ถ้ามี)</label>
                <textarea name="note" rows="2" class="form-control">{{ old('note') }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">รูปภาพประกอบ</label>
                <input type="file" name="image" accept="image/*" class="form-control">
                <small class="text-xs text-muted">ขนาดไม่เกิน 5 MB (JPG, PNG, WEBP)</small>
            </div>

            <hr class="divider">

            {{-- แผนที่และการค้นหา --}}
            <div class="form-group">
                <label class="form-label flex items-center gap-1">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    ระบุตำแหน่งสถานที่งาน
                </label>
                
                <div class="flex gap-2" style="margin-bottom:.5rem;">
                    <input type="text" id="mapSearchInput" class="form-control" placeholder="พิมพ์ชื่อสถานที่เพื่อค้นหา... (เช่น มหาวิทยาลัย, ชื่อตึก, ที่อยู่)">
                    <button type="button" id="btnMapSearch" class="btn btn-primary" style="white-space:nowrap;padding:0.5rem 1rem;font-size:0.9rem;">
                        <svg class="icon-sm" style="display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> ค้นหา
                    </button>
                </div>
                <div id="searchResultFeedback" class="text-xs" style="color:#ef4444;display:none;margin-bottom:.5rem;">ไม่พบข้อมูลสถานที่ กรุณาลองค้นหาด้วยคำอื่น</div>

                <div id="adminJobMap" style="height:350px;border-radius:8px;border:1px solid #e2e8f0;margin:.5rem 0;"></div>
                <p class="text-xs text-muted mb-2">เคล็ดลับ: คุณสามารถลากหมุดบนแผนที่ <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> หรือกรอกพิกัดลงในช่องด้านล่างเพื่อความแม่นยำได้</p>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:0.8rem;">Latitude (ละติจูด)</label>
                        <input type="number" step="any" name="latitude" id="latInput" value="{{ old('latitude') }}" class="form-control" placeholder="ตัวอย่าง: 13.75633">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:0.8rem;">Longitude (ลองจิจูด)</label>
                        <input type="number" step="any" name="longitude" id="lngInput" value="{{ old('longitude') }}" class="form-control" placeholder="ตัวอย่าง: 100.50182">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">สร้างประกาศงาน</button>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var latInput = document.getElementById('latInput');
    var lngInput = document.getElementById('lngInput');
    
    var initLat = latInput.value ? parseFloat(latInput.value) : 13.7563;
    var initLng = lngInput.value ? parseFloat(lngInput.value) : 100.5018;

    var map = L.map('adminJobMap').setView([initLat, initLng], latInput.value ? 16 : 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM', maxZoom: 19 }).addTo(map);

    var marker = null;
    if (latInput.value && lngInput.value) {
        marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
        bindMarkerEvents(marker);
    }

    function updateInputs(lat, lng) {
        latInput.value = lat.toFixed(7);
        lngInput.value = lng.toFixed(7);
    }

    function bindMarkerEvents(m) {
        m.on('dragend', function(e) {
            updateInputs(e.target.getLatLng().lat, e.target.getLatLng().lng);
        });
    }

    function updateMapFromCoords() {
        var lat = parseFloat(latInput.value);
        var lng = parseFloat(lngInput.value);
        if(!isNaN(lat) && !isNaN(lng)) {
            if(marker) map.removeLayer(marker);
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            bindMarkerEvents(marker);
            map.flyTo([lat, lng], 16);
        }
    }

    // คลิกเพื่อปักหมุด
    map.on('click', function(e) {
        if (marker) map.removeLayer(marker);
        marker = L.marker(e.latlng, { draggable: true }).addTo(map);
        bindMarkerEvents(marker);
        updateInputs(e.latlng.lat, e.latlng.lng);
    });

    // ค้นหาสถานที่ผ่าน Nominatim OSM แบบเรียลไทม์
    var searchInput = document.getElementById('mapSearchInput');
    var searchBtn = document.getElementById('btnMapSearch');
    var searchFeedback = document.getElementById('searchResultFeedback');

    function performSearch() {
        var q = searchInput.value.trim();
        if(!q) return;

        searchBtn.innerHTML = '<svg style="width:14px;height:14px;display:inline;animation:spin 1s linear infinite;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>...';
        searchBtn.disabled = true;
        searchFeedback.style.display = 'none';

        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&countrycodes=th&limit=1')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                searchBtn.innerHTML = '<svg class="icon-sm" style="display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> ค้นหา';
                searchBtn.disabled = false;

                if(data && data.length > 0) {
                    var lat = parseFloat(data[0].lat);
                    var lng = parseFloat(data[0].lon);
                    
                    if (marker) map.removeLayer(marker);
                    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                    marker.bindPopup('<b>' + data[0].display_name.split(',')[0] + '</b>').openPopup();
                    bindMarkerEvents(marker);
                    
                    updateInputs(lat, lng);
                    map.flyTo([lat, lng], 17);
                } else {
                    searchFeedback.style.display = 'block';
                }
            })
            .catch(function() {
                searchBtn.innerHTML = '<svg class="icon-sm" style="display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> ค้นหา';
                searchBtn.disabled = false;
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์ค้นหาแผนที่');
            });
    }

    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });

    // อัปเดตแผนที่อัตโนมัติเมื่อแอดมินแก้ไขพิกัดในช่อง Input 
    latInput.addEventListener('change', updateMapFromCoords);
    lngInput.addEventListener('change', updateMapFromCoords);

    // ดึงตำแหน่งปัจจุบัน
    if (navigator.geolocation && !latInput.value) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            map.flyTo([pos.coords.latitude, pos.coords.longitude], 15);
        }, function(){
            // do nothing fallback to center bkk
        }, { enableHighAccuracy: true });
    }
});
</script>
@endsection
