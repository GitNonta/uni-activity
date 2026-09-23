{{-- หน้าแก้ไขกิจกรรม (Admin): ฟอร์มแก้ไขข้อมูลกิจกรรมที่มีอยู่ --}}
@extends('layouts.admin')
@section('title', 'แก้ไขกิจกรรม')

@section('content')
<div class="activity-edit-container">
    {{-- ส่วนหัวของหน้า (Status เท่านั้น — Back link + Title ให้ Layout Admin จัดการแล้ว) --}}
    <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e2e8f0);">
        <div style="display:flex; align-items:center; gap:0.5rem;">
            @include('components.status-badge', ['status' => $activity->computed_status])
        </div>
    </div>

    <form method="POST" action="{{ route('admin.activities.update', $activity->id) }}" enctype="multipart/form-data" id="activityEditForm">
        @csrf @method('PUT')

        {{-- ── หมวดที่ 1: ข้อมูลทั่วไปของกิจกรรม (Boxless) ── --}}
        <div class="form-section-boxless">
            <div class="section-boxless-header">
                <div class="section-boxless-icon" style="background:rgba(234,88,12,0.12); color:#ea580c;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <h3 class="section-boxless-title">1. ข้อมูลทั่วไปของกิจกรรม</h3>
                    <p class="section-boxless-desc">กำหนดชื่อ รายละเอียด สถานที่ หมวดหมู่ และการจำกัดจำนวนผู้เข้าร่วม</p>
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="form-label" style="font-weight:600;">ชื่อกิจกรรม <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title" value="{{ old('title', $activity->title) }}" class="form-control" required placeholder="ระบุชื่อกิจกรรมให้ชัดเจน">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">หมวดหมู่กิจกรรม <span style="color:#ef4444;">*</span></label>
                    <select name="category_id" class="form-control" required>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $activity->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">สถานที่จัดกิจกรรม <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="location" value="{{ old('location', $activity->location) }}" class="form-control" required placeholder="เช่น ห้องประชุม 1 อาคารเรียนรวม">
                </div>
            </div>

            <div class="form-group mb-4">
                <label class="form-label" style="font-weight:600;">รายละเอียดกิจกรรม</label>
                <textarea name="description" rows="5" class="form-control" placeholder="รายละเอียด วัตถุประสงค์ กำหนดการ หรือข้อปฏิบัติต่างๆ">{{ old('description', $activity->description) }}</textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">ระดับกิจกรรม <span style="color:#ef4444;">*</span></label>
                    <select name="scope" id="scopeSelect" class="form-control" required onchange="toggleScopeFields()">
                        <option value="university" {{ old('scope', $activity->scope) == 'university' ? 'selected' : '' }}>ระดับมหาวิทยาลัย</option>
                        <option value="faculty" {{ old('scope', $activity->scope) == 'faculty' ? 'selected' : '' }}>ระดับคณะ</option>
                        <option value="department" {{ old('scope', $activity->scope) == 'department' ? 'selected' : '' }}>ระดับสาขา</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">สถานะกิจกรรม <span style="color:#ef4444;">*</span></label>
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
                    <select name="status" class="form-control" required>
                        @foreach($statusOptions as $val => $label)
                            <option value="{{ $val }}" {{ $activity->status == $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- แถบคณะและสาขาเมื่อเลือก Scope ย่อย --}}
            <div class="form-row" id="scopeDetailRow" style="display:none;">
                <div class="form-group" id="facultyGroup">
                    <label class="form-label" style="font-weight:600;">คณะที่จัดกิจกรรม</label>
                    <select name="faculty" id="facultyInput" class="form-control" onchange="updateDepartmentsScope()">
                        <option value="">เลือกคณะ</option>
                        @foreach(config('faculties') as $faculty => $deps)
                            <option value="{{ $faculty }}" label="{{ $faculty }}" {{ old('faculty', $activity->faculty) == $faculty ? 'selected' : '' }}>{{ $faculty }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="departmentGroup" style="display:none;">
                    <label class="form-label" style="font-weight:600;">สาขาวิชา</label>
                    <select name="department" id="departmentInput" class="form-control">
                        <option value="">เลือกสาขาวิชา</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">จำนวนผู้เข้าร่วมสูงสุด (คน) <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="max_participants" value="{{ old('max_participants', $activity->max_participants) }}" min="1" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">รูปภาพหน้าปกกิจกรรม (เปลี่ยนได้)</label>
                    <input type="file" name="image" accept="image/*" class="form-control">
                    @if($activity->image_path)
                        <div style="display:flex; align-items:center; gap:0.6rem; margin-top:0.4rem; padding:0.4rem 0.6rem; background:var(--bg-subtle, #f8fafc); border:1px solid var(--border-color, #e2e8f0); border-radius:6px;">
                            <img src="{{ Storage::url($activity->image_path) }}" alt="" style="width:36px; height:36px; object-fit:cover; border-radius:4px;">
                            <span class="text-xs text-muted" style="flex:1;">มีรูปภาพหน้าปกเดิมอยู่แล้ว (เลือกไฟล์ใหม่หากต้องการเปลี่ยน)</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── หมวดที่ 2: กำหนดการและชั่วโมงกิจกรรม (Boxless) ── --}}
        <div class="form-section-boxless">
            <div class="section-boxless-header">
                <div class="section-boxless-icon" style="background:rgba(37,99,235,0.12); color:#2563eb;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <h3 class="section-boxless-title">2. กำหนดการและชั่วโมงกิจกรรม</h3>
                    <p class="section-boxless-desc">กำหนดวัน เวลาเริ่ม-สิ้นสุด และการนับชั่วโมงกิจกรรม</p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.4rem;">
                        <label class="form-label" style="margin-bottom:0; font-weight:600;">วันที่จัดกิจกรรม <span style="color:#ef4444;">*</span></label>
                        <label class="checkbox-label" style="margin:0; font-size:.8rem; font-weight:500;">
                            <input type="checkbox" name="is_multiday" id="isMultidayCheck" value="1" onchange="toggleMultiday()" {{ old('is_multiday', $activity->is_multiday) ? 'checked' : '' }}> จัดหลายวัน (Multi-day)
                        </label>
                    </div>
                    <div style="display:flex; gap:0.5rem; align-items:center;">
                        <input type="date" name="activity_date" id="activityDate" value="{{ old('activity_date', $activity->activity_date->format('Y-m-d')) }}" class="form-control" required style="flex:1;" onchange="autoFillDates(); renderMultidaySchedule();">
                        <span id="endDateSeparator" style="display:{{ old('is_multiday', $activity->is_multiday) ? 'inline' : 'none' }}; font-weight:600; color:#64748b;">ถึง</span>
                        <input type="date" name="end_date" id="endDate" value="{{ old('end_date', $activity->end_date ? $activity->end_date->format('Y-m-d') : '') }}" class="form-control" style="flex:1; display:{{ old('is_multiday', $activity->is_multiday) ? 'block' : 'none' }};" onchange="renderMultidaySchedule();">
                    </div>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.4rem;">
                        <label class="form-label" style="margin-bottom:0; font-weight:600;">ชั่วโมงกิจกรรม <span style="color:#ef4444;">*</span></label>
                        <label class="checkbox-label" style="margin:0; font-size:.8rem; font-weight:500;">
                            <input type="checkbox" id="customHoursCheck" onchange="toggleCustomHours(this)" checked> ระบุชั่วโมงเอง
                        </label>
                    </div>
                    <input type="number" name="activity_hours" id="activityHours" value="{{ old('activity_hours', $activity->activity_hours) }}" step="0.5" min="0.5" class="form-control" required>
                    <p class="text-xs text-muted" style="margin-top:.25rem;" id="hoursHint">ระบุชั่วโมงกิจกรรมด้วยตัวเอง</p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">เวลาเริ่มกิจกรรม <span style="color:#ef4444;">*</span></label>
                    <input type="time" name="start_time" id="startTime" value="{{ old('start_time', \Carbon\Carbon::parse($activity->start_time)->format('H:i')) }}" class="form-control" required onchange="autoCalcHours(); autoFillDates()">
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">เวลาสิ้นสุดกิจกรรม <span style="color:#ef4444;">*</span></label>
                    <input type="time" name="end_time" id="endTime" value="{{ old('end_time', \Carbon\Carbon::parse($activity->end_time)->format('H:i')) }}" class="form-control" required onchange="autoCalcHours(); autoFillDates()">
                    <small id="crossDayHint" class="text-muted" style="display:{{ old('is_multiday', $activity->is_multiday) ? 'block' : 'none' }}; margin-top:4px;">(ข้ามวันได้)</small>
                </div>
            </div>

            {{-- Multi-Day Daily Schedule & Hours Section --}}
            <div id="multidayScheduleSection" class="multiday-schedule-wrap" style="display:none; margin-top:1.25rem;">
                <div class="multiday-header-border" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-color, #e2e8f0);">
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
                    {{-- Daily rows generated dynamically via JavaScript --}}
                </div>
            </div>
        </div>

        {{-- ── หมวดที่ 3: ช่วงเวลาลงทะเบียนและการเช็คอิน (Boxless) ── --}}
        <div class="form-section-boxless">
            <div class="section-boxless-header">
                <div class="section-boxless-icon" style="background:rgba(22,163,74,0.12); color:#16a34a;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="section-boxless-title">3. ช่วงเวลาลงทะเบียนและการเช็คอิน</h3>
                    <p class="section-boxless-desc">กำหนดช่วงเวลาเปิด-ปิดรับสมัคร และช่วงเวลาเปิดให้สแกนเข้า/ออกงาน</p>
                </div>
            </div>

            {{-- แถบแนะนำการตั้งเวลาอัตโนมัติ (Boxless Bar) --}}
            <div class="autofill-boxless-bar mb-4">
                <div style="display:flex; align-items:center; gap:0.45rem; color:#16a34a;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-7 7c0 2.5 1.5 4.5 3 6h8c1.5-1.5 3-3.5 3-6a7 7 0 0 0-7-7z"/></svg>
                    <span class="text-xs" style="font-weight:500;">ตั้งค่าเวลาลงทะเบียน / เช็คอิน / เช็คเอาต์ อัตโนมัติจากวันและเวลาจัดกิจกรรม</span>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="autoFillDates()" style="font-size:0.78rem; font-weight:600; padding:0.35rem 0.75rem;">
                    ตั้งค่าอัตโนมัติ
                </button>
            </div>

            {{-- เวลาเปิด-ปิดลงทะเบียน --}}
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">เปิดลงทะเบียน <span style="color:#ef4444;">*</span></label>
                    <input type="datetime-local" name="register_open_at" id="registerOpenInput" value="{{ old('register_open_at', $activity->register_open_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">ปิดลงทะเบียน <span style="color:#ef4444;">*</span></label>
                    <input type="datetime-local" name="register_close_at" id="registerCloseInput" value="{{ old('register_close_at', $activity->register_close_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                </div>
            </div>

            {{-- ส่วนเวลาเช็คอิน/เช็คเอาต์สำหรับกิจกรรมวันเดียว (ซ่อนอัตโนมัติเมื่อเลือกจัดกิจกรรมหลายวัน) --}}
            <div id="singleDayCheckinCheckoutSection">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600;">เปิดเช็คอิน (เข้างาน) <span style="color:#ef4444;">*</span></label>
                        <input type="datetime-local" name="checkin_open_at" id="checkinOpenInput" value="{{ old('checkin_open_at', $activity->checkin_open_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600;">ปิดเช็คอิน (เข้างาน) <span style="color:#ef4444;">*</span></label>
                        <input type="datetime-local" name="checkin_close_at" id="checkinCloseInput" value="{{ old('checkin_close_at', $activity->checkin_close_at->format('Y-m-d\TH:i')) }}" class="form-control" required>
                    </div>
                </div>

                <div class="form-group" id="noCheckoutGroup" style="display:none; margin-bottom: 0.75rem;">
                    <label class="checkbox-label" style="margin:0; font-size:.85rem; font-weight:500;">
                        <input type="checkbox" name="is_no_checkout" id="isNoCheckoutCheck" value="1" onchange="toggleNoCheckout()" {{ old('is_no_checkout', is_null($activity->checkout_open_at) && is_null($activity->checkout_close_at)) ? 'checked' : '' }}>
                        <span>ไม่ระบุเวลาสแกนออกงาน (บันทึกกิจกรรมได้ตลอดเวลา)</span>
                    </label>
                </div>

                <div class="form-row" id="checkoutTimeRow">
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600;">เปิดบันทึกกิจกรรม (ออกงาน) <span style="color:#ef4444;">*</span></label>
                        <input type="datetime-local" name="checkout_open_at" id="checkoutOpenInput" value="{{ old('checkout_open_at', $activity->checkout_open_at ? $activity->checkout_open_at->format('Y-m-d\TH:i') : '') }}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="font-weight:600;">ปิดบันทึกกิจกรรม (ออกงาน) <span style="color:#ef4444;">*</span></label>
                        <input type="datetime-local" name="checkout_close_at" id="checkoutCloseInput" value="{{ old('checkout_close_at', $activity->checkout_close_at ? $activity->checkout_close_at->format('Y-m-d\TH:i') : '') }}" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-group" id="minHoursGroup" style="display:none; margin-top:0.75rem;">
                <label class="form-label" style="font-weight:600;">ชั่วโมงขั้นต่ำที่ต้องเข้าร่วมก่อนเช็คเอาต์</label>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <input type="number" name="min_hours_before_checkout" id="minHoursInput" value="{{ old('min_hours_before_checkout', $activity->min_hours_before_checkout ?? 0) }}" min="0" step="0.5" class="form-control" style="max-width: 150px;">
                    <span class="text-muted text-sm">ชั่วโมง (0 = ไม่มีขั้นต่ำ, สามารถบันทึกออกงานได้ทันที)</span>
                </div>
            </div>
        </div>

        {{-- ── หมวดที่ 4: พิกัดสถานที่และแผนที่ (Boxless) ── --}}
        <div class="form-section-boxless">
            <div class="section-boxless-header">
                <div class="section-boxless-icon" style="background:rgba(217,70,239,0.12); color:#d946ef;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div>
                    <h3 class="section-boxless-title">4. แผนที่และพิกัดเช็คอิน GPS</h3>
                    <p class="section-boxless-desc">ปักหมุดตำแหน่งจัดกิจกรรมเพื่ออนุมัติการเช็คอินของนักศึกษาที่อยู่ในรัศมีโดยอัตโนมัติ</p>
                </div>
            </div>

            <div style="display:flex; gap:.5rem; margin-bottom:.75rem; flex-wrap:wrap;">
                <div style="flex:1; min-width:220px; position:relative;">
                    <input type="text" id="mapSearch" class="form-control" placeholder="ค้นหาสถานที่..." autocomplete="off">
                    <div id="searchResults" class="location-search-dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:300px; overflow-y:auto; z-index:1000; margin-top:4px; background:#ffffff;"></div>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="goToMyLocation()" style="display:inline-flex; align-items:center; gap:0.35rem;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    ตำแหน่งปัจจุบัน
                </button>
                <button type="button" class="btn btn-outline btn-sm" onclick="clearPin()" style="color:#dc2626; border-color:#fca5a5;">
                    ลบหมุด
                </button>
            </div>

            <div id="map" style="height:380px; border-radius:8px; border:1px solid var(--border-color, #e2e8f0); margin-bottom:1.25rem; z-index:0;"></div>
            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $activity->latitude) }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $activity->longitude) }}">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">รัศมีเช็คอินอัตโนมัติ (เมตร) <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="checkin_radius" id="checkin_radius" value="{{ old('checkin_radius', $activity->checkin_radius ?? 200) }}" min="10" max="5000" class="form-control" oninput="updateRadius()">
                    <p class="text-xs text-muted" style="margin-top:0.25rem;">ระยะที่อนุญาตให้นักศึกษาเช็คอินได้ (แนะนำ 100 - 300 เมตร)</p>
                </div>
                <div class="form-group">
                    <label class="form-label" style="font-weight:600;">พิกัดที่เลือก (Latitude, Longitude)</label>
                    <input type="text" id="coordDisplay" class="form-control" readonly placeholder="ยังไม่ได้ปักหมุด" style="background:var(--bg-subtle, #f8fafc); color:var(--text-muted, #64748b);">
                    <p class="text-xs text-muted" style="margin-top:0.25rem;">คลิกบนแผนที่หรือปักหมุดเพื่ออัปเดตพิกัด</p>
                </div>
            </div>
        </div>

        {{-- ── หมวดที่ 5: เงื่อนไขและการยืนยันตัวตน (Boxless) ── --}}
        <div class="form-section-boxless">
            <div class="section-boxless-header">
                <div class="section-boxless-icon" style="background:rgba(99,102,241,0.12); color:#6366f1;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <div>
                    <h3 class="section-boxless-title">5. เงื่อนไขและการยืนยันตัวตน</h3>
                    <p class="section-boxless-desc">กำหนดข้อบังคับ การเปิดรับ Walk-in และการตรวจสอบใบหน้านักศึกษา</p>
                </div>
            </div>

            <div class="rules-boxless-list mb-5">
                <label class="rule-boxless-item">
                    <input type="checkbox" name="is_mandatory" value="1" {{ $activity->is_mandatory ? 'checked' : '' }} style="margin-top:0.25rem;">
                    <div>
                        <div class="rule-boxless-label">กิจกรรมบังคับ (Mandatory Activity)</div>
                        <p class="text-xs text-muted" style="margin:0.15rem 0 0 0;">กำหนดให้นักศึกษาทุกคนในระดับที่เลือกต้องเข้าร่วมกิจกรรมนี้</p>
                    </div>
                </label>

                <label class="rule-boxless-item">
                    <input type="checkbox" name="allow_walkin" value="1" {{ old('allow_walkin', $activity->allow_walkin) ? 'checked' : '' }} style="margin-top:0.25rem;">
                    <div>
                        <div class="rule-boxless-label">อนุญาตให้สแกนเข้างานโดยไม่ต้องลงทะเบียนล่วงหน้า (เปิดรับ Walk-in)</div>
                        <p class="text-xs text-muted" style="margin:0.15rem 0 0 0;">นักศึกษาสามารถเดินมาสแกน QR หน้างานและบันทึกเวลาได้ทันที</p>
                    </div>
                </label>

                <label class="rule-boxless-item">
                    <input type="checkbox" name="require_attendance_approval" value="1" {{ $activity->require_attendance_approval ? 'checked' : '' }} style="margin-top:0.25rem;">
                    <div>
                        <div class="rule-boxless-label">ต้องตรวจสอบการเช็คอิน (Manual Staff Approval)</div>
                        <p class="text-xs text-muted" style="margin:0.15rem 0 0 0;">เมื่อสแกนแล้ว นักศึกษาจะมีสถานะ "รอตรวจสอบ" จนกว่าเจ้าหน้าที่จะกดอนุมัติ</p>
                    </div>
                </label>
            </div>

            {{-- การยืนยันตัวตนด้วยการสแกนใบหน้า (Boxless) --}}
            <div class="facescan-boxless-wrap">
                <label class="checkbox-label" style="margin-bottom: 0.5rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.5rem;">
                    <input type="checkbox" name="require_face_scan" id="requireFaceScan" value="1" onchange="toggleFaceScanMethod()" {{ old('require_face_scan', $activity->require_face_scan) ? 'checked' : '' }}> 
                    <span style="font-size:0.95rem; color:#ea580c; font-weight:700;">บังคับสแกนใบหน้า (Face Scan Verification)</span>
                </label>
                <p class="text-xs text-muted" style="margin-left: 1.6rem; margin-top: 0.15rem; margin-bottom: 1rem; line-height:1.5;">
                    เมื่อเช็คอิน/เช็คเอาต์ นักศึกษาจะต้องถ่ายรูปใบหน้าเพื่อยืนยันตัวตนเปรียบเทียบกับรูปถ่ายในฐานข้อมูล หากปิดตัวเลือกนี้ ระบบจะบันทึกเวลาให้ทันทีเมื่อตำแหน่ง GPS ถูกต้อง
                </p>
                
                <div id="faceScanMethodDiv" style="margin-left: 1.6rem;">
                    <label class="form-label" style="font-size: 0.88rem; font-weight:600;">ระบบประมวลผลใบหน้า (Face Scan Method)</label>
                    <select name="face_scan_method" class="form-control" style="max-width: 440px; font-size: 0.88rem;">
                        <option value="python" {{ old('face_scan_method', $activity->face_scan_method) == 'python' ? 'selected' : '' }}>Python AI Server (ความแม่นยำสูง, 512-d, แนะนำ)</option>
                        <option value="js" {{ old('face_scan_method', $activity->face_scan_method) == 'js' ? 'selected' : '' }}>Client-side FaceAPI.js (ประมวลผลบนมือถือนักศึกษา, 128-d)</option>
                    </select>
                    <small class="text-muted" style="display: block; margin-top: 0.35rem;">* หากเลือก Python AI แต่เซิร์ฟเวอร์ตอบสนองช้า ระบบจะสลับไปใช้ Client-side JS ให้โดยอัตโนมัติ (Auto-Failover)</small>
                </div>
            </div>
        </div>

        {{-- ── แถบปุ่มบันทึก (Action Footer - Boxless) ── --}}
        <div class="action-footer-boxless">
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <svg width="20" height="20" fill="none" stroke="#16a34a" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs text-muted">ตรวจสอบความถูกต้องของข้อมูลทั้งหมดก่อนกดบันทึก</span>
            </div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <a href="{{ route('admin.activities.index') }}" class="btn btn-outline" style="min-width:100px; justify-content:center;">
                    ยกเลิก
                </a>
                <button type="submit" class="btn btn-primary btn-lg" style="min-width:160px; justify-content:center; gap:0.5rem; font-weight:700;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>บันทึกการแก้ไข</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<style>
/* ── Activity Edit Boxless & Full Width Enhancements ── */
.sb-main:has(.activity-edit-container) {
    max-width: 100% !important;
    padding-left: 2rem !important;
    padding-right: 2rem !important;
}
.activity-edit-container {
    width: 100%;
}
.form-section-boxless {
    padding-bottom: 2.25rem;
    margin-bottom: 2.25rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}
.section-boxless-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}
.section-boxless-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.section-boxless-title {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-main, #0f172a);
}
.section-boxless-desc {
    font-size: 0.82rem;
    color: var(--text-muted, #64748b);
    margin: 0.2rem 0 0 0;
}
.autofill-boxless-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px dashed var(--border-color, #e2e8f0);
}
.rules-boxless-list {
    display: flex;
    flex-direction: column;
}
.rule-boxless-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 0;
    border-bottom: 1px dashed var(--border-color, #e2e8f0);
    cursor: pointer;
}
.rule-boxless-item:last-child {
    border-bottom: none;
}
.rule-boxless-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-main, #1e293b);
}
.facescan-boxless-wrap {
    padding-top: 1.25rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
}
.action-footer-boxless {
    padding-top: 1.75rem;
    margin-top: 1rem;
    margin-bottom: 3rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
@media (max-width: 768px) {
    .sb-main:has(.activity-edit-container) {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
    .form-row {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
}
/* ── Dark Mode ── */
html[data-theme="dark"] .form-section-boxless,
html.dark .form-section-boxless {
    border-bottom-color: #27272a !important;
}
html[data-theme="dark"] .section-boxless-title,
html.dark .section-boxless-title {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .section-boxless-desc,
html.dark .section-boxless-desc {
    color: #a1a1aa !important;
}
html[data-theme="dark"] .rule-boxless-label,
html.dark .rule-boxless-label {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .rule-boxless-item,
html.dark .rule-boxless-item {
    border-bottom-color: #27272a !important;
}
html[data-theme="dark"] .autofill-boxless-bar,
html.dark .autofill-boxless-bar {
    border-bottom-color: #27272a !important;
}
html[data-theme="dark"] .facescan-boxless-wrap,
html.dark .facescan-boxless-wrap {
    border-top-color: #27272a !important;
}
html[data-theme="dark"] #searchResults {
    background: #1c1c1f !important;
    border: 1px solid #27272a !important;
}
html[data-theme="dark"] #coordDisplay {
    background: #18181b !important;
    color: #a1a1aa !important;
    border-color: #27272a !important;
}
/* ── Boxless overrides: Multi-day Schedule (เอากรอบ/เงา/พื้นหลังกล่องออก) ── */
.activity-edit-container .day-card {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 1rem 0 1.25rem 0;
    border-bottom: 1px dashed var(--border-color, #e2e8f0);
    border-radius: 0;
}
.activity-edit-container .day-card:last-child {
    border-bottom: none;
}
.activity-edit-container .day-card-header {
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}
.activity-edit-container .day-box-activity,
.activity-edit-container .day-box-checkin,
.activity-edit-container .day-box-checkout {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0.4rem 0 0 0;
    border-radius: 0;
}
html[data-theme="dark"] .activity-edit-container .day-card,
html.dark .activity-edit-container .day-card {
    background: transparent !important;
    border-bottom-color: #27272a !important;
    box-shadow: none !important;
}
html[data-theme="dark"] .activity-edit-container .day-card-header,
html.dark .activity-edit-container .day-card-header {
    border-bottom-color: #27272a !important;
}
</style>
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
        div.className = 'location-search-item';
        
        var name = item.display_name;
        var distText = '';
        if (item.distance !== undefined) {
            distText = '<span style="color:#64748b;font-size:.8rem;margin-left:8px;">(' + formatDistance(item.distance) + ')</span>';
        }
        
        div.innerHTML = '<div class="item-title" style="font-size:.9rem;color:var(--text-main, #1e293b);">' + escapeHtml(name) + distText + '</div>';
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

@php
    $initialDays = old('days', $activity->days->map(function($d) {
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
    }));
@endphp
const INITIAL_DAYS = @json($initialDays);

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
