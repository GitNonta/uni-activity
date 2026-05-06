{{-- Admin: แก้ไขประกาศงาน --}}
@extends('layouts.admin')
@section('title', 'แก้ไขประกาศงาน')

@section('content')
<a href="{{ route('admin.jobs.show', $job->id) }}" class="text-sm text-primary mb-2" style="display:inline-block;">&larr; กลับรายละเอียด</a>
<h1 class="font-bold mb-4" style="font-size:1.25rem;">✏️ แก้ไขประกาศงาน</h1>

@if($errors->any())
<div class="alert alert-error">
    <ul style="margin:.25rem 0;padding-left:1rem;">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.jobs.update', $job->id) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">หัวข้อพาดหัวงาน *</label>
                <input type="text" name="title" value="{{ old('title', $job->title) }}" class="form-control" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ประเภทงาน *</label>
                    <select name="job_type" class="form-control" required>
                        <option value="general" {{ old('job_type', $job->job_type) == 'general' ? 'selected' : '' }}>งานทั่วไป</option>
                        <option value="parttime" {{ old('job_type', $job->job_type) == 'parttime' ? 'selected' : '' }}>Part-time</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ตำแหน่งงาน *</label>
                    <input type="text" name="position" value="{{ old('position', $job->position) }}" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">จำนวนรับสมัคร *</label>
                    <input type="number" name="quota" value="{{ old('quota', $job->quota) }}" min="1" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ค่าตอบแทน</label>
                    <input type="text" name="compensation" value="{{ old('compensation', $job->compensation) }}" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ช่วงเวลางาน</label>
                    <input type="text" name="work_period" value="{{ old('work_period', $job->work_period) }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">การแต่งกาย</label>
                    <input type="text" name="dresscode" value="{{ old('dresscode', $job->dresscode) }}" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เพศที่รับสมัคร *</label>
                    <select name="gender" class="form-control" required>
                        <option value="any" {{ old('gender', $job->gender) == 'any' ? 'selected' : '' }}>ไม่จำกัดเพศ</option>
                        <option value="male" {{ old('gender', $job->gender) == 'male' ? 'selected' : '' }}>ชาย</option>
                        <option value="female" {{ old('gender', $job->gender) == 'female' ? 'selected' : '' }}>หญิง</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">สถานที่ปฏิบัติงาน *</label>
                    <input type="text" name="location" value="{{ old('location', $job->location) }}" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">วันเริ่มงาน *</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $job->start_date?->format('Y-m-d')) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">วันสิ้นสุดงาน</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $job->end_date?->format('Y-m-d')) }}" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">รายละเอียดงาน</label>
                <textarea name="description" rows="4" class="form-control">{{ old('description', $job->description) }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">หมายเหตุ</label>
                <textarea name="note" rows="2" class="form-control">{{ old('note', $job->note) }}</textarea>
            </div>

            <div class="form-group">
                <label class="form-label">รูปภาพประกอบ</label>
                @if($job->image_path)
                    <img src="{{ Storage::url($job->image_path) }}" alt="รูปปัจจุบัน" style="max-height:120px;border-radius:8px;margin-bottom:.5rem;">
                @endif
                <input type="file" name="image" accept="image/*" class="form-control">
                <small class="text-xs text-muted">อัปโหลดรูปใหม่เพื่อแทนที่</small>
            </div>

            <hr class="divider">

            {{-- แผนที่และการค้นหา --}}
            <div class="form-group">
                <label class="form-label">📍 ระบุตำแหน่งสถานที่งาน</label>
                
                <div class="flex gap-2" style="margin-bottom:.5rem;">
                    <input type="text" id="mapSearchInput" class="form-control" placeholder="พิมพ์ชื่อสถานที่เพื่อค้นหา... (เช่น มหาวิทยาลัย, ชื่อตึก, ที่อยู่)">
                    <button type="button" id="btnMapSearch" class="btn btn-primary" style="white-space:nowrap;padding:0.5rem 1rem;font-size:0.9rem;">
                        <svg class="icon-sm" style="display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> ค้นหา
                    </button>
                </div>
                <div id="searchResultFeedback" class="text-xs" style="color:#ef4444;display:none;margin-bottom:.5rem;">ไม่พบข้อมูลสถานที่ กรุณาลองค้นหาด้วยคำอื่น</div>

                <div id="adminJobMap" style="height:350px;border-radius:8px;border:1px solid #e2e8f0;margin:.5rem 0;"></div>
                <p class="text-xs text-muted mb-2">เคล็ดลับ: คุณสามารถลากหมุดบนแผนที่ 📍 หรือกรอกพิกัดลงในช่องด้านล่างเพื่อความแม่นยำได้</p>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:0.8rem;">Latitude (ละติจูด)</label>
                        <input type="number" step="any" name="latitude" id="latInput" value="{{ old('latitude', $job->latitude) }}" class="form-control" placeholder="ตัวอย่าง: 13.75633">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label" style="font-size:0.8rem;">Longitude (ลองจิจูด)</label>
                        <input type="number" step="any" name="longitude" id="lngInput" value="{{ old('longitude', $job->longitude) }}" class="form-control" placeholder="ตัวอย่าง: 100.50182">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">บันทึกการแก้ไข</button>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

        searchBtn.innerHTML = '🔄...';
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
});
</script>
@endsection
