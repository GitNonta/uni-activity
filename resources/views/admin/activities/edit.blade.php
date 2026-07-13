{{-- หน้าแก้ไขกิจกรรม (Admin): ฟอร์มแก้ไขข้อมูลกิจกรรมที่มีอยู่ --}}
@extends('layouts.admin')
@section('title', 'แก้ไขกิจกรรม')

@section('content')
<a href="{{ route('admin.activities.index') }}" class="text-sm text-primary">&larr; กลับ</a>
<h1 class="font-bold mt-2 mb-4" style="font-size:1.5rem;">แก้ไข: {{ $activity->title }}</h1>

{{-- ฟอร์มแก้ไขกิจกรรม: เหมือนฟอร์มสร้างแต่เพิ่มสถานะและใช้ PUT method --}}
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.activities.update', $activity->id) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="form-group">
                <label class="form-label">ชื่อกิจกรรม</label>
                <input type="text" name="title" value="{{ old('title', $activity->title) }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">รายละเอียด</label>
                <textarea name="description" rows="6" class="form-control">{{ old('description', $activity->description) }}</textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">สถานที่</label>
                    <input type="text" name="location" value="{{ old('location', $activity->location) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">หมวดหมู่</label>
                    <select name="category_id" class="form-control" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $activity->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            {{-- ระดับกิจกรรม: มหาวิทยาลัย / คณะ / สาขา --}}
            <div class="form-group">
                <label class="form-label">ระดับกิจกรรม</label>
                <select name="scope" id="scopeSelect" class="form-control" required onchange="toggleScopeFields()">
                    <option value="university" {{ old('scope', $activity->scope) == 'university' ? 'selected' : '' }}>ระดับมหาวิทยาลัย</option>
                    <option value="faculty" {{ old('scope', $activity->scope) == 'faculty' ? 'selected' : '' }}>ระดับคณะ</option>
                    <option value="department" {{ old('scope', $activity->scope) == 'department' ? 'selected' : '' }}>ระดับสาขา</option>
                </select>
            </div>
            <div class="form-row" id="scopeDetailRow" style="display:none;">
                <div class="form-group" id="facultyGroup">
                    <label class="form-label">คณะ</label>
                    <select name="faculty" id="facultyInput" class="form-control" onchange="updateDepartmentsScope()">
                        <option value="">เลือกคณะ</option>
                        @foreach(config('faculties') as $faculty => $deps)
                            <option value="{{ $faculty }}" label="{{ $faculty }}" {{ old('faculty', $activity->faculty) == $faculty ? 'selected' : '' }}>{{ $faculty }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="departmentGroup" style="display:none;">
                    <label class="form-label">สาขา</label>
                    <select name="department" id="departmentInput" class="form-control">
                        <option value="">เลือกสาขาวิชา</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                        <label class="form-label" style="margin-bottom:0;">วันที่จัดกิจกรรม</label>
                        <label class="checkbox-label" style="margin:0; font-size:.8rem; color:#475569; font-weight:500;">
                            <input type="checkbox" name="is_multiday" id="isMultidayCheck" value="1" onchange="toggleMultiday()" {{ old('is_multiday', $activity->is_multiday) ? 'checked' : '' }}> จัดหลายวัน
                        </label>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <input type="date" name="activity_date" id="activityDate" value="{{ old('activity_date', $activity->activity_date->format('Y-m-d')) }}" class="form-control" required style="flex:1;">
                        <span id="endDateSeparator" style="display:{{ old('is_multiday', $activity->is_multiday) ? 'inline' : 'none' }};">ถึง</span>
                        <input type="date" name="end_date" id="endDate" value="{{ old('end_date', $activity->end_date ? $activity->end_date->format('Y-m-d') : '') }}" class="form-control" style="flex:1; display:{{ old('is_multiday', $activity->is_multiday) ? 'block' : 'none' }};">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">ชั่วโมงกิจกรรม</label>
                    {{-- auto-calc จากเวลาเริ่ม-สิ้นสุด เว้นแต่จะติ๊ก 'ระบุเอง' --}}
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                        <label class="checkbox-label" style="margin:0;font-size:.8rem;color:#475569;font-weight:500;">
                            <input type="checkbox" id="customHoursCheck" onchange="toggleCustomHours(this)" checked> ระบุชั่วโมงกิจกรรมเอง
                        </label>
                    </div>
                    <input type="number" name="activity_hours" id="activityHours"
                        value="{{ old('activity_hours', $activity->activity_hours) }}" step="0.5" min="0.5" class="form-control" required>
                    <p class="text-xs text-muted" style="margin-top:.2rem;" id="hoursHint">ระบุชั่วโมงกิจกรรมด้วยตัวเอง</p>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เวลาเริ่ม</label>
                    <input type="time" name="start_time" id="startTime" value="{{ old('start_time', \Carbon\Carbon::parse($activity->start_time)->format('H:i')) }}" class="form-control" required onchange="autoCalcHours()">
                </div>
                <div class="form-group">
                    <label class="form-label">เวลาสิ้นสุด</label>
                    <input type="time" name="end_time" id="endTime" value="{{ old('end_time', \Carbon\Carbon::parse($activity->end_time)->format('H:i')) }}" class="form-control" required onchange="autoCalcHours()">
                    <small class="text-muted" style="display:block; margin-top:4px;">(ข้ามวันได้)</small>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">จำนวนผู้เข้าร่วมสูงสุด</label>
                <input type="number" name="max_participants" value="{{ old('max_participants', $activity->max_participants) }}" min="1" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เปิดลงทะเบียน</label>
                    <input type="datetime-local" name="register_open_at" value="{{ old('register_open_at', $activity->register_open_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ปิดลงทะเบียน</label>
                    <input type="datetime-local" name="register_close_at" value="{{ old('register_close_at', $activity->register_close_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เปิดเช็คอิน</label>
                    <input type="datetime-local" name="checkin_open_at" value="{{ old('checkin_open_at', $activity->checkin_open_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ปิดเช็คอิน</label>
                    <input type="datetime-local" name="checkin_close_at" value="{{ old('checkin_close_at', $activity->checkin_close_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เปิดบันทึกกิจกรรม (ออกงาน)</label>
                    <input type="datetime-local" name="checkout_open_at" value="{{ old('checkout_open_at', $activity->checkout_open_at ? $activity->checkout_open_at->format('Y-m-d\TH:i') : '') }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ปิดบันทึกกิจกรรม (ออกงาน)</label>
                    <input type="datetime-local" name="checkout_close_at" value="{{ old('checkout_close_at', $activity->checkout_close_at ? $activity->checkout_close_at->format('Y-m-d\TH:i') : '') }}" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">ชั่วโมงขั้นต่ำที่ต้องเข้าร่วมก่อนเช็คเอาต์</label>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <input type="number" name="min_hours_before_checkout" value="{{ old('min_hours_before_checkout', $activity->min_hours_before_checkout ?? 0) }}" min="0" step="0.5" class="form-control" style="max-width: 150px;">
                    <span class="text-muted text-sm">ชั่วโมง (0 = ไม่มีขั้นต่ำ, สามารถบันทึกออกงานได้ทันที)</span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">สถานะ</label>
                @php
                    $statusOptions = [
                        'upcoming' => 'กำลังจะเปิด',
                        'open' => 'เปิดรับสมัคร',
                        'full' => 'เต็มแล้ว',
                        'ongoing' => 'กำลังจัดกิจกรรม',
                        'done' => 'เสร็จสิ้น',
                        'cancelled' => 'ยกเลิก'
                    ];
                @endphp
                <select name="status" class="form-control">
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}" {{ $activity->status == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0;">
                <p class="font-semi text-sm mb-2">ปักพิกัดสถานที่จัดกิจกรรม (สำหรับเช็คอินอัตโนมัติ)</p>
                <p class="text-xs text-muted mb-3">คลิกบนแผนที่เพื่อปักหมุด หรือกดปุ่มตำแหน่งปัจจุบัน — นักศึกษาที่อยู่ในรัศมีจะได้รับอนุมัติอัตโนมัติ</p>
                <div style="display:flex;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap;">
                    <div style="flex:1;min-width:180px;position:relative;">
                        <input type="text" id="mapSearch" class="form-control" placeholder="ค้นหาสถานที่..." autocomplete="off">
                        <div id="searchResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);max-height:300px;overflow-y:auto;z-index:1000;margin-top:4px;"></div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="goToMyLocation()">
                        <svg class="icon-sm" style="display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        ตำแหน่งปัจจุบัน
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="clearPin()" style="color:#dc2626;border-color:#fca5a5;">ลบหมุด</button>
                </div>
                <div id="map" style="height:350px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:.75rem;z-index:0;"></div>
                <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $activity->latitude) }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $activity->longitude) }}">
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">รัศมีเช็คอินอัตโนมัติ (เมตร)</label>
                        <input type="number" name="checkin_radius" id="checkin_radius" value="{{ old('checkin_radius', $activity->checkin_radius ?? 200) }}" min="10" max="5000" class="form-control" oninput="updateRadius()">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label">พิกัดที่เลือก</label>
                        <input type="text" id="coordDisplay" class="form-control" readonly placeholder="ยังไม่ได้ปักหมุด" style="background:#f8fafc;color:#64748b;">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">รูปภาพ (เปลี่ยนได้)</label>
                <input type="file" name="image" accept="image/*" class="form-control">
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_mandatory" value="1" {{ $activity->is_mandatory ? 'checked' : '' }}> กิจกรรมบังคับ
                </label>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="allow_walkin" value="1" {{ old('allow_walkin', $activity->allow_walkin) ? 'checked' : '' }}> อนุญาตให้สแกนเข้างานโดยไม่ต้องลงทะเบียนล่วงหน้า (เปิดรับ Walk-in)
                </label>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="require_attendance_approval" value="1" {{ $activity->require_attendance_approval ? 'checked' : '' }}> ต้องตรวจสอบการเช็คอิน (Manual Approval)
                </label>
                <p class="text-xs text-muted" style="margin-left: 1.5rem; margin-top: 0.15rem;">หากติ๊กเลือก นักศึกษาที่สแกน QR จะมีสถานะ "รอตรวจสอบ" จนกว่าผู้จัดจะกดอนุมัติ</p>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="require_selfie_verification" value="1" {{ old('require_selfie_verification', $activity->require_selfie_verification) ? 'checked' : '' }}> เปิดยืนยันตัวตนด้วย Selfie (AI Face Compare)
                </label>
                <p class="text-xs text-muted" style="margin-left: 1.5rem; margin-top: 0.15rem;">เมื่อเช็คอินเข้างาน นักศึกษาจะต้องถ่ายรูปหน้าเพื่อยืนยันตัวตนกับรูปในระบบ หากไม่ตรงจะส่งให้ผู้จัดตรวจสอบ</p>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">บันทึก</button>
        </form>
    </div>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
var map, marker, circle;
var savedLat = {!! json_encode($activity->latitude) !!};
var savedLng = {!! json_encode($activity->longitude) !!};
var hasPin = (savedLat !== null && savedLng !== null);
var initLat = hasPin ? parseFloat(savedLat) : 13.7563;
var initLng = hasPin ? parseFloat(savedLng) : 100.5018;

document.addEventListener('DOMContentLoaded', function() {
    map = L.map('map').setView([initLat, initLng], hasPin ? 16 : 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    if (hasPin) placePin(initLat, initLng);

    map.on('click', function(e) {
        placePin(e.latlng.lat, e.latlng.lng);
    });

    document.getElementById('mapSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); searchPlace(); }
    });
});

function placePin(lat, lng) {
    lat = parseFloat(lat); lng = parseFloat(lng);
    document.getElementById('latitude').value = lat.toFixed(7);
    document.getElementById('longitude').value = lng.toFixed(7);
    document.getElementById('coordDisplay').value = lat.toFixed(5) + ', ' + lng.toFixed(5);

    if (marker) { marker.setLatLng([lat, lng]); }
    else { marker = L.marker([lat, lng], { draggable: true }).addTo(map); marker.on('dragend', function(e) { placePin(e.target.getLatLng().lat, e.target.getLatLng().lng); }); }

    var r = parseInt(document.getElementById('checkin_radius').value) || 200;
    if (circle) { circle.setLatLng([lat, lng]).setRadius(r); }
    else { circle = L.circle([lat, lng], { radius: r, color: '#4f46e5', fillColor: '#818cf8', fillOpacity: 0.15, weight: 2 }).addTo(map); }

    map.setView([lat, lng], Math.max(map.getZoom(), 15));
}

function updateRadius() {
    if (!circle) return;
    var r = parseInt(document.getElementById('checkin_radius').value) || 200;
    circle.setRadius(r);
}

function goToMyLocation() {
    if (!navigator.geolocation) { alert('เบราว์เซอร์ไม่รองรับ GPS'); return; }
    navigator.geolocation.getCurrentPosition(
        function(pos) { placePin(pos.coords.latitude, pos.coords.longitude); map.setView([pos.coords.latitude, pos.coords.longitude], 17); },
        function(err) { alert('ไม่สามารถดึงพิกัดได้: ' + err.message); },
        { enableHighAccuracy: true }
    );
}

function clearPin() {
    if (marker) { map.removeLayer(marker); marker = null; }
    if (circle) { map.removeLayer(circle); circle = null; }
    document.getElementById('latitude').value = '';
    document.getElementById('longitude').value = '';
    document.getElementById('coordDisplay').value = '';
}

// ── Autocomplete Search ──
var searchTimeout;
var userLat = null, userLng = null;

// Get user location for sorting
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
        function(pos) { userLat = pos.coords.latitude; userLng = pos.coords.longitude; },
        function() {}, { enableHighAccuracy: false }
    );
}

document.getElementById('mapSearch').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    var q = e.target.value.trim();
    if (q.length < 3) {
        document.getElementById('searchResults').style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(function() { searchPlaceAutocomplete(q); }, 300);
});

// Hide dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('#mapSearch') && !e.target.closest('#searchResults')) {
        document.getElementById('searchResults').style.display = 'none';
    }
});

function searchPlaceAutocomplete(q) {
    var url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q) + '&limit=10&countrycodes=th&addressdetails=1';
    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.length === 0) {
                document.getElementById('searchResults').style.display = 'none';
                return;
            }
            // Sort by distance if user location available
            if (userLat !== null && userLng !== null) {
                data.forEach(function(item) {
                    var lat = parseFloat(item.lat), lng = parseFloat(item.lon);
                    item.distance = calcDistance(userLat, userLng, lat, lng);
                });
                data.sort(function(a, b) { return a.distance - b.distance; });
            }
            displaySearchResults(data);
        })
        .catch(function() {});
}

function displaySearchResults(data) {
    var container = document.getElementById('searchResults');
    container.innerHTML = '';
    data.forEach(function(item) {
        var div = document.createElement('div');
        div.style.cssText = 'padding:10px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .15s;';
        div.onmouseover = function() { this.style.background = '#f8fafc'; };
        div.onmouseout = function() { this.style.background = '#fff'; };
        
        var name = item.display_name;
        var distText = '';
        if (item.distance !== undefined) {
            distText = '<span style="color:#64748b;font-size:.8rem;margin-left:8px;">(' + formatDistance(item.distance) + ')</span>';
        }
        
        div.innerHTML = '<div style="font-size:.9rem;color:#1e293b;">' + escapeHtml(name) + distText + '</div>';
        div.onclick = function() {
            selectPlace(parseFloat(item.lat), parseFloat(item.lon), name);
        };
        container.appendChild(div);
    });
    container.style.display = 'block';
}

function selectPlace(lat, lng, name) {
    placePin(lat, lng);
    map.setView([lat, lng], 17);
    document.getElementById('mapSearch').value = name;
    document.getElementById('searchResults').style.display = 'none';
}

function calcDistance(lat1, lng1, lat2, lng2) {
    var R = 6371e3;
    var p1 = lat1 * Math.PI / 180, p2 = lat2 * Math.PI / 180;
    var dp = (lat2 - lat1) * Math.PI / 180, dl = (lng2 - lng1) * Math.PI / 180;
    var a = Math.sin(dp/2) * Math.sin(dp/2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dl/2) * Math.sin(dl/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function formatDistance(m) {
    return m >= 1000 ? (m/1000).toFixed(1) + ' กม.' : Math.round(m) + ' ม.';
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function searchPlace() {
    var q = document.getElementById('mapSearch').value.trim();
    if (!q) return;
    searchPlaceAutocomplete(q);
}

// ── Scope toggle: แสดง/ซ่อนช่องคณะ/สาขาตามระดับ ──
const facultyData = @json(config('faculties'));
const oldDepartment = "{{ old('department', $activity->department) }}";

function toggleScopeFields() {
    var scope = document.getElementById('scopeSelect').value;
    var detailRow = document.getElementById('scopeDetailRow');
    var deptGroup = document.getElementById('departmentGroup');
    var facultyInput = document.getElementById('facultyInput');
    var deptInput = document.getElementById('departmentInput');

    if (scope === 'university') {
        detailRow.style.display = 'none';
        facultyInput.removeAttribute('required');
        deptInput.removeAttribute('required');
    } else if (scope === 'faculty') {
        detailRow.style.display = '';
        deptGroup.style.display = 'none';
        facultyInput.setAttribute('required', 'required');
        deptInput.removeAttribute('required');
    } else {
        detailRow.style.display = '';
        deptGroup.style.display = '';
        facultyInput.setAttribute('required', 'required');
        deptInput.setAttribute('required', 'required');
    }
}

function updateDepartmentsScope() {
    var facultyInput = document.getElementById('facultyInput');
    var deptInput = document.getElementById('departmentInput');
    var selectedFaculty = facultyInput.value;
    
    deptInput.innerHTML = '<option value="">เลือกสาขาวิชา</option>';
    
    if (selectedFaculty && facultyData[selectedFaculty]) {
        facultyData[selectedFaculty].forEach(function(dep) {
            var opt = document.createElement('option');
            opt.value = dep;
            opt.textContent = dep;
            if (dep === oldDepartment) opt.selected = true;
            deptInput.appendChild(opt);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleScopeFields();
    if (document.getElementById('facultyInput').value) updateDepartmentsScope();
    autoCalcHours();
    toggleMultiday();
});

function toggleMultiday() {
    var isMulti = document.getElementById('isMultidayCheck').checked;
    var endDateInput = document.getElementById('endDate');
    var separator = document.getElementById('endDateSeparator');
    
    if (isMulti) {
        endDateInput.style.display = '';
        separator.style.display = 'inline';
        endDateInput.setAttribute('required', 'required');
    } else {
        endDateInput.style.display = 'none';
        separator.style.display = 'none';
        endDateInput.removeAttribute('required');
        endDateInput.value = '';
    }
}

function autoCalcHours() {
    var isCustom = document.getElementById('customHoursCheck').checked;
    if (isCustom) return;
    var start = document.getElementById('startTime').value;
    var end   = document.getElementById('endTime').value;
    if (!start || !end) return;
    var startMin = timeToMin(start);
    var endMin   = timeToMin(end);
    var diff = endMin - startMin;
    if (diff <= 0) return;
    var hrs = Math.round(diff / 30) * 0.5;
    hrs = Math.max(0.5, hrs);
    document.getElementById('activityHours').value = hrs.toFixed(1);
}

function timeToMin(t) {
    var parts = t.split(':');
    return parseInt(parts[0]) * 60 + parseInt(parts[1]);
}

function toggleCustomHours(cb) {
    var input = document.getElementById('activityHours');
    var hint  = document.getElementById('hoursHint');
    if (cb.checked) {
        input.removeAttribute('readonly');
        input.style.background = '';
        input.style.color = '';
        hint.textContent = 'ระบุชั่วโมงกิจกรรมด้วยตัวเอง';
        input.focus();
    } else {
        input.setAttribute('readonly', 'readonly');
        input.style.background = '#f8fafc';
        input.style.color = '#475569';
        hint.textContent = 'คำนวณอัตโนมัติจากเวลาเริ่ม–สิ้นสุด';
        autoCalcHours();
    }
}
</script>
@endsection
