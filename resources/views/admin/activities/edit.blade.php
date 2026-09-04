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
                        <input type="date" name="activity_date" id="activityDate" value="{{ old('activity_date', $activity->activity_date->format('Y-m-d')) }}" class="form-control" required style="flex:1;" onchange="autoFillDates(); renderMultidaySchedule();">
                        <span id="endDateSeparator" style="display:{{ old('is_multiday', $activity->is_multiday) ? 'inline' : 'none' }};">ถึง</span>
                        <input type="date" name="end_date" id="endDate" value="{{ old('end_date', $activity->end_date ? $activity->end_date->format('Y-m-d') : '') }}" class="form-control" style="flex:1; display:{{ old('is_multiday', $activity->is_multiday) ? 'block' : 'none' }};" onchange="renderMultidaySchedule();">
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
                    <input type="time" name="start_time" id="startTime" value="{{ old('start_time', \Carbon\Carbon::parse($activity->start_time)->format('H:i')) }}" class="form-control" required onchange="autoCalcHours(); autoFillDates()">
                </div>
                <div class="form-group">
                    <label class="form-label">เวลาสิ้นสุด</label>
                    <input type="time" name="end_time" id="endTime" value="{{ old('end_time', \Carbon\Carbon::parse($activity->end_time)->format('H:i')) }}" class="form-control" required onchange="autoCalcHours(); autoFillDates()">
                    <small id="crossDayHint" class="text-muted" style="display:{{ old('is_multiday', $activity->is_multiday) ? 'block' : 'none' }}; margin-top:4px;">(ข้ามวันได้)</small>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">จำนวนผู้เข้าร่วมสูงสุด</label>
                <input type="number" name="max_participants" value="{{ old('max_participants', $activity->max_participants) }}" min="1" class="form-control" required>
            </div>
            <div class="form-row">
            <div class="form-group" style="padding:.6rem .8rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:.75rem;display:flex;align-items:center;justify-content:space-between;">
                <p class="text-xs" style="color:#16a34a;margin:0;display:flex;align-items:center;gap:0.35rem;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-7 7c0 2.5 1.5 4.5 3 6h8c1.5-1.5 3-3.5 3-6a7 7 0 0 0-7-7z"/></svg> คลิกปุ่มด้านขวาเพื่อตั้งค่าเวลาลงทะเบียน / เช็คอิน / เช็คเอาต์ อัตโนมัติจากวันที่จัดกิจกรรม</p>
                <button type="button" class="btn btn-outline btn-sm" onclick="autoFillDates()" style="white-space:nowrap;margin-left:.5rem;flex-shrink:0;">ตั้งค่าอัตโนมัติ</button>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">เปิดลงทะเบียน</label>
                    <input type="datetime-local" name="register_open_at" id="registerOpenInput" value="{{ old('register_open_at', $activity->register_open_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">ปิดลงทะเบียน</label>
                    <input type="datetime-local" name="register_close_at" id="registerCloseInput" value="{{ old('register_close_at', $activity->register_close_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
            </div>
            {{-- ส่วนเวลาเช็คอิน/เช็คเอาต์สำหรับกิจกรรมวันเดียว (ซ่อนอัตโนมัติเมื่อเลือกจัดกิจกรรมหลายวัน เพื่อไม่ให้ทับซ้อน) --}}
            <div id="singleDayCheckinCheckoutSection">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">เปิดเช็คอิน</label>
                        <input type="datetime-local" name="checkin_open_at" id="checkinOpenInput" value="{{ old('checkin_open_at', $activity->checkin_open_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ปิดเช็คอิน</label>
                        <input type="datetime-local" name="checkin_close_at" id="checkinCloseInput" value="{{ old('checkin_close_at', $activity->checkin_close_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                    </div>
                </div>
                <div class="form-group" id="noCheckoutGroup" style="display:none; margin-bottom: 0.5rem;">
                    <label class="checkbox-label" style="margin:0; font-size:.8rem; color:#475569; font-weight:500;">
                        <input type="checkbox" name="is_no_checkout" id="isNoCheckoutCheck" value="1" onchange="toggleNoCheckout()" {{ old('is_no_checkout', is_null($activity->checkout_open_at) && is_null($activity->checkout_close_at)) ? 'checked' : '' }}> ไม่ระบุเวลาสแกนออกงาน (บันทึกกิจกรรมได้ตลอดเวลา)
                    </label>
                </div>
                <div class="form-row" id="checkoutTimeRow">
                    <div class="form-group">
                        <label class="form-label">เปิดบันทึกกิจกรรม (ออกงาน)</label>
                        <input type="datetime-local" name="checkout_open_at" id="checkoutOpenInput" value="{{ old('checkout_open_at', $activity->checkout_open_at ? $activity->checkout_open_at->format('Y-m-d\TH:i') : '') }}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ปิดบันทึกกิจกรรม (ออกงาน)</label>
                        <input type="datetime-local" name="checkout_close_at" id="checkoutCloseInput" value="{{ old('checkout_close_at', $activity->checkout_close_at ? $activity->checkout_close_at->format('Y-m-d\TH:i') : '') }}" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="form-group" id="minHoursGroup" style="display:none;">
                <label class="form-label">ชั่วโมงขั้นต่ำที่ต้องเข้าร่วมก่อนเช็คเอาต์</label>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <input type="number" name="min_hours_before_checkout" id="minHoursInput" value="{{ old('min_hours_before_checkout', $activity->min_hours_before_checkout ?? 0) }}" min="0" step="0.5" class="form-control" style="max-width: 150px;">
                    <span class="text-muted text-sm">ชั่วโมง (0 = ไม่มีขั้นต่ำ, สามารถบันทึกออกงานได้ทันที)</span>
                </div>
            </div>

            <!-- Multi-Day Daily Schedule & Hours Section -->
            <div id="multidayScheduleSection" class="multiday-schedule-wrap" style="display:none;">
                <div class="multiday-header-border" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; padding-bottom:0.75rem;">
                    <div>
                        <h4 class="multiday-title" style="margin:0; font-size:0.95rem; font-weight:700; display:flex; align-items:center; gap:0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            กำหนดการเช็คอิน-เช็คเอาต์ และชั่วโมงกิจกรรมแยกรายวัน (Daily Schedule & Hours)
                        </h4>
                        <p class="text-xs text-muted" style="margin:0.25rem 0 0 0;">
                            ระบุเวลาเปิด-ปิดเช็คอิน เช็คเอาต์ และชั่วโมงกิจกรรมสำหรับแต่ละวันของกิจกรรม
                        </p>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <button type="button" onclick="applyDay1ToAll()" class="btn btn-outline btn-sm" style="font-size:0.75rem; display:inline-flex; align-items:center; gap:0.35rem; padding:0.35rem 0.65rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            ใช้เวลาวันแรกกับทุกวัน
                        </button>
                        <span id="multidayTotalHoursBadge" style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:0.75rem; font-weight:700; padding:0.35rem 0.65rem; border-radius:8px; display:inline-flex; align-items:center; gap:0.35rem;">
                            รวมชั่วโมงกิจกรรม: <span id="multidayTotalHoursText" style="font-weight:800; font-size:0.85rem; margin-left:2px;">0</span> ชม.
                        </span>
                    </div>
                </div>

                <div id="multidayDaysContainer" style="display:flex; flex-direction:column; gap:0.85rem;">
                    <!-- Daily rows generated dynamically via JavaScript -->
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
            <div class="form-group" style="padding: 1rem; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
                <label class="checkbox-label" style="font-weight: bold; margin-bottom: 0.5rem;">
                    <input type="checkbox" name="require_face_scan" id="requireFaceScan" value="1" onchange="toggleFaceScanMethod()" {{ old('require_face_scan', $activity->require_face_scan) ? 'checked' : '' }}> 
                    บังคับสแกนใบหน้า (Face Scan Verification)
                </label>
                <p class="text-xs text-muted" style="margin-left: 1.5rem; margin-top: 0.15rem; margin-bottom: 1rem;">เมื่อเช็คอิน/เช็คเอาท์ นักศึกษาจะต้องถ่ายรูปหน้าเพื่อยืนยันตัวตนกับรูปในระบบ หากนำติ๊กออก ระบบจะบันทึกเวลาให้ทันทีเมื่อตำแหน่ง GPS ถูกต้อง</p>
                
                <div id="faceScanMethodDiv" style="margin-left: 1.5rem;">
                    <label class="form-label" style="font-size: 0.9rem;">ระบบประมวลผลใบหน้า (Face Scan Method)</label>
                    <select name="face_scan_method" class="form-control" style="max-width: 400px; font-size: 0.9rem;">
                        <option value="python" {{ old('face_scan_method', $activity->face_scan_method) == 'python' ? 'selected' : '' }}>Python AI Server (ความแม่นยำสูง, 512-d, แนะนำ)</option>
                        <option value="js" {{ old('face_scan_method', $activity->face_scan_method) == 'js' ? 'selected' : '' }}>Client-side FaceAPI.js (ประมวลผลบนมือถือนักศึกษา, 128-d)</option>
                    </select>
                    <small class="text-muted" style="display: block; margin-top: 0.25rem;">* หากเลือก Python AI แต่เซิร์ฟเวอร์ตอบสนองช้า ระบบจะสลับไปใช้ Client-side JS ให้โดยอัตโนมัติ (Auto-Failover)</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">บันทึก</button>
        </form>
    </div>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
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
    else { circle = L.circle([lat, lng], { radius: r, color: '#ea580c', fillColor: '#fb923c', fillOpacity: 0.15, weight: 2 }).addTo(map); }

    map.setView([lat, lng], Math.max(map.getZoom(), 15));
}

function updateRadius() {
    if (!circle) return;
    var r = parseInt(document.getElementById('checkin_radius').value) || 200;
    circle.setRadius(r);
}

function toggleFaceScanMethod() {
    var isChecked = document.getElementById('requireFaceScan').checked;
    document.getElementById('faceScanMethodDiv').style.display = isChecked ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    toggleFaceScanMethod();
});

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
    toggleNoCheckout();
});

const INITIAL_DAYS = @json(old('days', $activity->days->map(function($d) {
    return [
        'day_number' => $d->day_number,
        'date' => $d->date->format('Y-m-d'),
        'start_time' => $d->start_time ? \Carbon\Carbon::parse($d->start_time)->format('H:i') : null,
        'end_time' => $d->end_time ? \Carbon\Carbon::parse($d->end_time)->format('H:i') : null,
        'activity_hours' => (float)$d->activity_hours,
        'checkin_open_at' => $d->checkin_open_at ? $d->checkin_open_at->format('Y-m-d\TH:i') : null,
        'checkin_close_at' => $d->checkin_close_at ? $d->checkin_close_at->format('Y-m-d\TH:i') : null,
        'checkout_open_at' => $d->checkout_open_at ? $d->checkout_open_at->format('Y-m-d\TH:i') : null,
        'checkout_close_at' => $d->checkout_close_at ? $d->checkout_close_at->format('Y-m-d\TH:i') : null,
    ];
})));

function getDatesBetween(startDateStr, endDateStr) {
    let dates = [];
    if (!startDateStr || !endDateStr) return dates;
    let curr = new Date(startDateStr);
    let last = new Date(endDateStr);
    if (curr > last) return dates;
    
    let count = 0;
    while (curr <= last && count < 31) {
        let y = curr.getFullYear();
        let m = String(curr.getMonth() + 1).padStart(2, '0');
        let d = String(curr.getDate()).padStart(2, '0');
        dates.push(`${y}-${m}-${d}`);
        curr.setDate(curr.getDate() + 1);
        count++;
    }
    return dates;
}

function offsetTime(timeStr, offsetMinutes) {
    if (!timeStr) return '09:00';
    var p = timeStr.split(':');
    var mins = parseInt(p[0]) * 60 + parseInt(p[1]) + offsetMinutes;
    if (mins < 0) mins += 1440;
    if (mins >= 1440) mins -= 1440;
    var h = String(Math.floor(mins / 60)).padStart(2, '0');
    var m = String(mins % 60).padStart(2, '0');
    return h + ':' + m;
}

function renderMultidaySchedule() {
    var isMulti = document.getElementById('isMultidayCheck').checked;
    var section = document.getElementById('multidayScheduleSection');
    var container = document.getElementById('multidayDaysContainer');
    var startDateStr = document.getElementById('activityDate').value;
    var endDateStr = document.getElementById('endDate').value;
    
    if (!isMulti || !startDateStr || !endDateStr) {
        if (section) section.style.display = 'none';
        return;
    }
    
    var dates = getDatesBetween(startDateStr, endDateStr);
    if (dates.length <= 0) {
        if (section) section.style.display = 'none';
        return;
    }
    
    if (section) section.style.display = 'block';
    
    var defaultStartTime = document.getElementById('startTime').value || '09:00';
    var defaultEndTime   = document.getElementById('endTime').value || '12:00';
    var defaultHours     = parseFloat(document.getElementById('activityHours').value) || 3.0;

    var daysData = (typeof INITIAL_DAYS !== 'undefined' && Array.isArray(INITIAL_DAYS)) ? INITIAL_DAYS : [];

    var html = '';
    dates.forEach(function(dateStr, idx) {
        var dayNum = idx + 1;
        var existing = daysData.find(function(d) { return d.date === dateStr; }) || daysData[idx] || {};
        
        var startTime = existing.start_time || defaultStartTime;
        var endTime   = existing.end_time || defaultEndTime;
        var hours     = (existing.activity_hours !== undefined && existing.activity_hours !== null && existing.activity_hours !== '') ? existing.activity_hours : defaultHours;
        
        var checkinOpen  = existing.checkin_open_at || (dateStr + 'T' + offsetTime(startTime, -30));
        var checkinClose = existing.checkin_close_at || (dateStr + 'T' + offsetTime(startTime, 60));
        var checkoutOpen = existing.checkout_open_at || (dateStr + 'T' + startTime);
        var checkoutClose = existing.checkout_close_at || (dateStr + 'T' + offsetTime(endTime, 60));
        
        var dParts = dateStr.split('-');
        var displayDate = dParts[2] + '/' + dParts[1] + '/' + dParts[0];
        
        html += `
        <div class="day-card">
            <div class="day-card-header">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="background:#2563eb; color:#ffffff; font-size:0.75rem; font-weight:700; padding:0.2rem 0.55rem; border-radius:6px; display:inline-flex; align-items:center; gap:0.35rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                        </svg>
                        วันที่ ${dayNum}
                    </span>
                    <strong class="day-card-date" style="font-size:0.85rem;">${displayDate}</strong>
                </div>
                <div style="display:flex; align-items:center; gap:0.4rem;">
                    <label class="text-xs text-muted" style="margin:0; font-weight:600;">ชั่วโมงกิจกรรมวันนี้:</label>
                    <input type="number" name="days[${idx}][activity_hours]" value="${hours}" step="0.5" min="0" max="100" 
                        class="form-control day-hours-input" style="width:80px; padding:0.25rem 0.5rem; font-size:0.8rem; font-weight:700;" 
                        oninput="calculateMultidayTotalHours()" required>
                    <span class="text-xs text-muted">ชม.</span>
                </div>
            </div>

            <input type="hidden" name="days[${idx}][day_number]" value="${dayNum}">
            <input type="hidden" name="days[${idx}][date]" value="${dateStr}">

            <div class="day-card-grid">
                <div class="day-box-activity">
                    <div style="font-size:0.72rem; font-weight:700; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.3rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        เวลาจัดกิจกรรม
                    </div>
                    <div style="display:flex; gap:0.35rem; align-items:center;">
                        <input type="time" name="days[${idx}][start_time]" value="${startTime}" class="form-control" style="font-size:0.75rem; padding:0.25rem 0.4rem;">
                        <span class="text-muted" style="font-size:0.7rem;">ถึง</span>
                        <input type="time" name="days[${idx}][end_time]" value="${endTime}" class="form-control" style="font-size:0.75rem; padding:0.25rem 0.4rem;">
                    </div>
                </div>

                <div class="day-box-checkin">
                    <div style="font-size:0.72rem; font-weight:700; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.3rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        ช่วงเวลาเช็คอิน (เข้างาน)
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.3rem;">
                        <input type="datetime-local" name="days[${idx}][checkin_open_at]" value="${checkinOpen}" class="form-control" style="font-size:0.75rem; padding:0.25rem 0.4rem;" onchange="syncPrimaryTimes()" required>
                        <input type="datetime-local" name="days[${idx}][checkin_close_at]" value="${checkinClose}" class="form-control" style="font-size:0.75rem; padding:0.25rem 0.4rem;" onchange="syncPrimaryTimes()" required>
                    </div>
                </div>

                <div class="day-box-checkout">
                    <div style="font-size:0.72rem; font-weight:700; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.3rem;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        ช่วงเวลาเช็คเอาต์ (ออกงาน)
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.3rem;">
                        <input type="datetime-local" name="days[${idx}][checkout_open_at]" value="${checkoutOpen}" class="form-control" style="font-size:0.75rem; padding:0.25rem 0.4rem;" onchange="syncPrimaryTimes()">
                        <input type="datetime-local" name="days[${idx}][checkout_close_at]" value="${checkoutClose}" class="form-control" style="font-size:0.75rem; padding:0.25rem 0.4rem;" onchange="syncPrimaryTimes()">
                    </div>
                </div>
            </div>
        </div>
        `;
    });
    
    container.innerHTML = html;
    calculateMultidayTotalHours();
    syncPrimaryTimes();
}

function syncPrimaryTimes() {
    var isMulti = document.getElementById('isMultidayCheck').checked;
    if (!isMulti) return;
    
    var day0CheckinOpen = document.querySelector('input[name="days[0][checkin_open_at]"]')?.value;
    var day0CheckinClose = document.querySelector('input[name="days[0][checkin_close_at]"]')?.value;
    var day0CheckoutOpen = document.querySelector('input[name="days[0][checkout_open_at]"]')?.value;
    
    var allCheckoutCloses = document.querySelectorAll('input[name$="[checkout_close_at]"]');
    var lastCheckoutClose = allCheckoutCloses.length ? allCheckoutCloses[allCheckoutCloses.length - 1].value : '';
    
    var checkinOpenInput = document.getElementById('checkinOpenInput');
    var checkinCloseInput = document.getElementById('checkinCloseInput');
    var checkoutOpenInput = document.getElementById('checkoutOpenInput');
    var checkoutCloseInput = document.getElementById('checkoutCloseInput');
    
    if (checkinOpenInput && day0CheckinOpen) checkinOpenInput.value = day0CheckinOpen;
    if (checkinCloseInput && day0CheckinClose) checkinCloseInput.value = day0CheckinClose;
    if (checkoutOpenInput && day0CheckoutOpen) checkoutOpenInput.value = day0CheckoutOpen;
    if (checkoutCloseInput && lastCheckoutClose) checkoutCloseInput.value = lastCheckoutClose;
}

function calculateMultidayTotalHours() {
    var isMulti = document.getElementById('isMultidayCheck').checked;
    if (!isMulti) return;
    var inputs = document.querySelectorAll('.day-hours-input');
    var total = 0;
    inputs.forEach(function(inp) {
        var v = parseFloat(inp.value);
        if (!isNaN(v)) total += v;
    });
    var txt = document.getElementById('multidayTotalHoursText');
    if (txt) txt.textContent = total.toFixed(1);
    
    var mainHoursInput = document.getElementById('activityHours');
    if (mainHoursInput && total > 0) {
        mainHoursInput.value = total.toFixed(1);
    }
}

function applyDay1ToAll() {
    var cards = document.querySelectorAll('.day-card');
    if (cards.length <= 1) return;
    
    var day0Hours = document.querySelector('input[name="days[0][activity_hours]"]')?.value;
    var day0Start = document.querySelector('input[name="days[0][start_time]"]')?.value;
    var day0End   = document.querySelector('input[name="days[0][end_time]"]')?.value;
    var day0CheckinOpen = document.querySelector('input[name="days[0][checkin_open_at]"]')?.value;
    var day0CheckinClose = document.querySelector('input[name="days[0][checkin_close_at]"]')?.value;
    var day0CheckoutOpen = document.querySelector('input[name="days[0][checkout_open_at]"]')?.value;
    var day0CheckoutClose = document.querySelector('input[name="days[0][checkout_close_at]"]')?.value;

    cards.forEach(function(card, idx) {
        if (idx === 0) return;
        var dateVal = card.querySelector(`input[name="days[${idx}][date]"]`)?.value;
        if (!dateVal) return;
        
        if (day0Hours !== undefined) {
            var hInp = card.querySelector(`input[name="days[${idx}][activity_hours]"]`);
            if (hInp) hInp.value = day0Hours;
        }
        if (day0Start) {
            var sInp = card.querySelector(`input[name="days[${idx}][start_time]"]`);
            if (sInp) sInp.value = day0Start;
        }
        if (day0End) {
            var eInp = card.querySelector(`input[name="days[${idx}][end_time]"]`);
            if (eInp) eInp.value = day0End;
        }
        if (day0CheckinOpen) {
            var timePart = day0CheckinOpen.split('T')[1] || '08:30';
            var inp = card.querySelector(`input[name="days[${idx}][checkin_open_at]"]`);
            if (inp) inp.value = dateVal + 'T' + timePart;
        }
        if (day0CheckinClose) {
            var timePart = day0CheckinClose.split('T')[1] || '10:00';
            var inp = card.querySelector(`input[name="days[${idx}][checkin_close_at]"]`);
            if (inp) inp.value = dateVal + 'T' + timePart;
        }
        if (day0CheckoutOpen) {
            var timePart = day0CheckoutOpen.split('T')[1] || '09:00';
            var inp = card.querySelector(`input[name="days[${idx}][checkout_open_at]"]`);
            if (inp) inp.value = dateVal + 'T' + timePart;
        }
        if (day0CheckoutClose) {
            var timePart = day0CheckoutClose.split('T')[1] || '13:00';
            var inp = card.querySelector(`input[name="days[${idx}][checkout_close_at]"]`);
            if (inp) inp.value = dateVal + 'T' + timePart;
        }
    });
    
    calculateMultidayTotalHours();
    syncPrimaryTimes();
}

function toggleMultiday() {
    var isMulti = document.getElementById('isMultidayCheck').checked;
    var endDateInput = document.getElementById('endDate');
    var separator = document.getElementById('endDateSeparator');
    var crossDayHint = document.getElementById('crossDayHint');
    var minHoursGroup = document.getElementById('minHoursGroup');
    var minHoursInput = document.getElementById('minHoursInput');
    var singleSection = document.getElementById('singleDayCheckinCheckoutSection');
    var checkinOpenInput = document.getElementById('checkinOpenInput');
    var checkinCloseInput = document.getElementById('checkinCloseInput');
    var checkoutOpenInput = document.getElementById('checkoutOpenInput');
    var checkoutCloseInput = document.getElementById('checkoutCloseInput');
    var noCheckoutGroup = document.getElementById('noCheckoutGroup');
    var isNoCheckoutCheck = document.getElementById('isNoCheckoutCheck');
    var section = document.getElementById('multidayScheduleSection');
    
    if (isMulti) {
        endDateInput.style.display = '';
        separator.style.display = 'inline';
        endDateInput.setAttribute('required', 'required');
        if (crossDayHint) crossDayHint.style.display = 'block';
        if (minHoursGroup) minHoursGroup.style.display = 'block';
        
        // ซ่อนส่วนเวลาเช็คอิน/เช็คเอาต์วันเดียว เพื่อไม่ให้ทับซ้อนกับกำหนดการรายวัน
        if (singleSection) singleSection.style.display = 'none';
        if (checkinOpenInput) checkinOpenInput.removeAttribute('required');
        if (checkinCloseInput) checkinCloseInput.removeAttribute('required');
        if (checkoutOpenInput) checkoutOpenInput.removeAttribute('required');
        if (checkoutCloseInput) checkoutCloseInput.removeAttribute('required');
        
        renderMultidaySchedule();
    } else {
        endDateInput.style.display = 'none';
        separator.style.display = 'none';
        endDateInput.removeAttribute('required');
        endDateInput.value = '';
        if (crossDayHint) crossDayHint.style.display = 'none';
        if (minHoursGroup) {
            minHoursGroup.style.display = 'none';
            minHoursInput.value = '0';
        }
        
        // แสดงส่วนเวลาเช็คอิน/เช็คเอาต์วันเดียวกลับมา
        if (singleSection) singleSection.style.display = 'block';
        if (checkinOpenInput) checkinOpenInput.setAttribute('required', 'required');
        if (checkinCloseInput) checkinCloseInput.setAttribute('required', 'required');
        toggleNoCheckout();
        
        if (section) section.style.display = 'none';
    }
}

function toggleNoCheckout() {
    var isNoCheckout = document.getElementById('isNoCheckoutCheck').checked;
    var row = document.getElementById('checkoutTimeRow');
    var openInput = document.getElementById('checkoutOpenInput');
    var closeInput = document.getElementById('checkoutCloseInput');
    
    if (isNoCheckout) {
        row.style.display = 'none';
        openInput.removeAttribute('required');
        closeInput.removeAttribute('required');
    } else {
        row.style.display = '';
        openInput.setAttribute('required', 'required');
        closeInput.setAttribute('required', 'required');
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

// ── Auto-fill dates: ตั้งค่าเวลาลงทะเบียน/เช็คอิน/เช็คเอาต์อัตโนมัติจากวันที่จัดกิจกรรม ──
function autoFillDates() {
    var dateVal = document.getElementById('activityDate').value;
    var startVal = document.getElementById('startTime').value;
    var endVal = document.getElementById('endTime').value;
    if (!dateVal || !startVal || !endVal) return;

    // Parse date (YYYY-MM-DD)
    var dp = dateVal.split('-');
    var y = parseInt(dp[0]), m = parseInt(dp[1]) - 1, d = parseInt(dp[2]);

    // Parse times (HH:MM)
    var sp = startVal.split(':'), ep = endVal.split(':');
    var sh = parseInt(sp[0]), sm = parseInt(sp[1]);
    var eh = parseInt(ep[0]), em = parseInt(ep[1]);

    // Build Date objects
    var startDate = new Date(y, m, d, sh, sm);
    var endDate   = new Date(y, m, d, eh, em);

    // Cross-day: end time on the next day
    if (endDate <= startDate) endDate.setDate(endDate.getDate() + 1);

    var now = new Date();

    // register_open_at  = ตอนนี้
    // register_close_at = วันที่จัด − 1 ชม. (ก่อนเริ่มกิจกรรม)
    // checkin_open_at   = วันที่จัด + เวลาเริ่ม − 30 นาที
    // checkin_close_at  = วันที่จัด + เวลาสิ้นสุด + 30 นาที
    // checkout_open_at  = วันที่จัด + เวลาเริ่ม
    // checkout_close_at = วันที่จัด + เวลาสิ้นสุด
    var regOpen  = now;
    var regClose = new Date(startDate.getTime() - 3600000);
    var ciOpen   = new Date(startDate.getTime() - 1800000);
    var ciClose  = new Date(endDate.getTime()   + 1800000);
    var coOpen   = new Date(startDate.getTime());
    var coClose  = new Date(endDate.getTime());

    function fmt(dt) {
        return dt.getFullYear() + '-' +
            String(dt.getMonth()+1).padStart(2,'0') + '-' +
            String(dt.getDate()).padStart(2,'0') + 'T' +
            String(dt.getHours()).padStart(2,'0') + ':' +
            String(dt.getMinutes()).padStart(2,'0');
    }

    var ids = ['registerOpenInput','registerCloseInput','checkinOpenInput','checkinCloseInput','checkoutOpenInput','checkoutCloseInput'];
    var vals = [fmt(regOpen), fmt(regClose), fmt(ciOpen), fmt(ciClose), fmt(coOpen), fmt(coClose)];

    ids.forEach(function(id, i) {
        var el = document.getElementById(id);
        if (el) {
            el.value = vals[i];
            el.style.background = '#f0fdf4';
            el.style.borderColor = '#86efac';
            el.style.transition = 'background .3s, border-color .3s';
        }
    });
}
</script>
@endsection
