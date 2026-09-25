{{-- หน้าบรอดแคสต์ข้อความด่วน และ Auto-Reminder (Admin) --}}
@extends('layouts.admin')
@section('title', 'บรอดแคสต์ & แจ้งเตือน: ' . $activity->title)

@section('content')
<div class="mb-4">
    <div class="flex items-center gap-2 text-sm text-muted mb-2">
        <a href="{{ route('admin.activities.index') }}" class="text-primary hover:underline">กิจกรรมทั้งหมด</a>
        <span>/</span>
        <a href="{{ route('admin.activities.show', $activity) }}" class="text-primary hover:underline">{{ Str::limit($activity->title, 30) }}</a>
        <span>/</span>
        <span class="text-dark font-medium">บรอดแคสต์ & แจ้งเตือน</span>
    </div>

    <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 class="font-bold flex items-center gap-2" style="font-size:1.35rem;color:#0f172a;">
                <span style="display:inline-flex;padding:0.4rem;background:#e0e7ff;border-radius:0.5rem;color:#4338ca;">
                    <svg style="width:1.35rem;height:1.35rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                    </svg>
                </span>
                <span>ระบบบรอดแคสต์และแจ้งเตือนอัตโนมัติ</span>
            </h1>
            <p class="text-xs text-muted mt-1">ส่งประกาศด่วนผ่าน In-App / Reverb / LINE และจัดการระบบ Auto-Reminder ล่วงหน้าลด No-show rate</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.activities.show', $activity) }}" class="btn btn-outline btn-sm flex items-center gap-1">
                <svg style="width:0.9rem;height:0.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                <span>กลับหน้ารายละเอียด</span>
            </a>
            <a href="{{ route('admin.activities.participants', $activity) }}" class="btn btn-outline btn-sm flex items-center gap-1">
                <svg style="width:0.9rem;height:0.9rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span>รายชื่อผู้เข้าร่วม</span>
            </a>
        </div>
    </div>
</div>

@if(session('success'))
<div style="background:#ecfdf5;border-left:4px solid #10b981;padding:0.9rem 1.2rem;border-radius:0.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;">
    <svg style="width:1.25rem;height:1.25rem;color:#059669;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <div style="font-size:0.875rem;color:#065f46;font-weight:500;">
        {{ session('success') }}
    </div>
</div>
@endif

@if($errors->any())
<div style="background:#fef2f2;border-left:4px solid #ef4444;padding:0.9rem 1.2rem;border-radius:0.5rem;margin-bottom:1.5rem;">
    <div class="flex items-center gap-2 mb-1" style="color:#991b1b;font-weight:600;font-size:0.875rem;">
        <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>พบข้อผิดพลาดในการส่งข้อมูล:</span>
    </div>
    <ul class="text-xs" style="color:#b91c1c;margin:0;padding-left:1.25rem;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- สถิติความพร้อมของผู้รับสาร (Audience Overview) --}}
<div class="grid-4 mb-4" style="gap:1rem;">
    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">ผู้ลงทะเบียนทั้งหมด</span>
            <span style="color:#4f46e5;padding:0.3rem;background:#eef2ff;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </span>
        </div>
        <p class="font-bold text-dark mt-2" style="font-size:1.4rem;">{{ $stats['total_registered'] }} <span style="font-size:0.8rem;font-weight:normal;color:#64748b;">/ {{ $activity->max_participants }} คน</span></p>
    </div>

    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">สถานะอนุมัติแล้ว (สิทธิ์เข้าร่วม)</span>
            <span style="color:#059669;padding:0.3rem;background:#ecfdf5;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </span>
        </div>
        <p class="font-bold text-success mt-2" style="font-size:1.4rem;">{{ $stats['approved_count'] }} <span style="font-size:0.8rem;font-weight:normal;color:#64748b;">คน</span></p>
    </div>

    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">ผูก LINE แจ้งเตือนแล้ว</span>
            <span style="color:#06c755;padding:0.3rem;background:#f0fdf4;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 5.92 2 10.75c0 2.98 1.77 5.6 4.46 7.15-.2.74-.72 2.7-1.03 3.86-.06.24.18.44.38.31 1.78-1.18 4.25-2.82 4.79-3.21.46.06.93.1 1.4.1 5.52 0 10-3.92 10-8.75S17.52 2 12 2z"/></svg>
            </span>
        </div>
        <p class="font-bold mt-2" style="font-size:1.4rem;color:#06c755;">{{ $stats['line_linked_count'] }} <span style="font-size:0.8rem;font-weight:normal;color:#64748b;">บัญชี</span></p>
    </div>

    <div class="card stat-card" style="padding:1rem;background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;">
        <div class="flex items-center justify-between">
            <span class="stat-label text-xs text-muted">กำหนดการจัดกิจกรรม</span>
            <span style="color:#0284c7;padding:0.3rem;background:#f0f9ff;border-radius:0.375rem;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </span>
        </div>
        <p class="font-bold text-dark mt-2" style="font-size:0.95rem;">{{ $activity->activity_date->format('d/m/Y') }}</p>
        <p class="text-xs text-muted">{{ \Carbon\Carbon::parse($activity->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($activity->end_time)->format('H:i') }} น. ({{ $activity->location ?? '-' }})</p>
    </div>
</div>

<div class="grid-2 mb-4" style="gap:1.5rem;align-items:start;">
    {{-- ซีกซ้าย: แบบฟอร์มส่งข้อความบรอดแคสต์ --}}
    <div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;">
        <div class="flex items-center justify-between mb-4 pb-3" style="border-bottom:1px solid #f1f5f9;">
            <div class="flex items-center gap-2">
                <span style="display:inline-flex;padding:0.35rem;background:#fef3c7;border-radius:0.375rem;color:#d97706;">
                    <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </span>
                <h2 class="font-bold" style="font-size:1.1rem;color:#0f172a;margin:0;">ส่งประกาศบรอดแคสต์ด่วน</h2>
            </div>
            <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:0.75rem;">Multi-Channel</span>
        </div>

        {{-- แม่แบบข้อความด่วน (Quick Presets) --}}
        <div class="mb-4">
            <label class="block text-xs font-semibold text-muted mb-2">ข้อความสำเร็จรูป (คลิกเพื่อใส่ข้อความด่วน):</label>
            <div class="flex gap-2" style="flex-wrap:wrap;">
                <button type="button" onclick="setPreset('venue')" class="btn btn-sm btn-outline text-xs flex items-center gap-1" style="background:#f8fafc;">
                    <svg style="width:0.85rem;height:0.85rem;color:#ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    <span>แจ้งย้ายสถานที่</span>
                </button>
                <button type="button" onclick="setPreset('time')" class="btn btn-sm btn-outline text-xs flex items-center gap-1" style="background:#f8fafc;">
                    <svg style="width:0.85rem;height:0.85rem;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>แจ้งเปลี่ยนแปลงเวลา</span>
                </button>
                <button type="button" onclick="setPreset('urgent')" class="btn btn-sm btn-outline text-xs flex items-center gap-1" style="background:#f8fafc;">
                    <svg style="width:0.85rem;height:0.85rem;color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>ประกาศด่วน</span>
                </button>
                <button type="button" onclick="setPreset('prep')" class="btn btn-sm btn-outline text-xs flex items-center gap-1" style="background:#f8fafc;">
                    <svg style="width:0.85rem;height:0.85rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    <span>สิ่งที่ต้องเตรียมตัว</span>
                </button>
            </div>
        </div>

        <form action="{{ route('admin.activities.broadcast.send', $activity) }}" method="POST" id="broadcastForm">
            @csrf

            {{-- 1. ประเภทประกาศ --}}
            <div class="mb-3">
                <label class="block text-xs font-semibold text-dark mb-1">ประเภทประกาศ <span class="text-danger">*</span></label>
                <select name="type" id="broadcastType" class="form-control" style="width:100%;border-radius:0.5rem;font-size:0.875rem;" required onchange="updatePreview()">
                    <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>ข่าวสารทั่วไป (General Announcement)</option>
                    <option value="urgent" {{ old('type') == 'urgent' ? 'selected' : '' }}>ประกาศด่วนสำคัญ (Urgent Notice)</option>
                    <option value="venue_change" {{ old('type') == 'venue_change' ? 'selected' : '' }}>แจ้งย้ายสถานที่จัดงาน (Venue Relocation)</option>
                    <option value="time_change" {{ old('type') == 'time_change' ? 'selected' : '' }}>แจ้งเปลี่ยนแปลงเวลา (Rescheduling)</option>
                    <option value="reminder" {{ old('type') == 'reminder' ? 'selected' : '' }}>แจ้งเตือนเตรียมตัว (Preparation Reminder)</option>
                </select>
            </div>

            {{-- 2. หัวข้อประกาศ --}}
            <div class="mb-3">
                <label class="block text-xs font-semibold text-dark mb-1">หัวข้อประกาศ <span class="text-danger">*</span></label>
                <input type="text" name="title" id="broadcastTitle" class="form-control" placeholder="เช่น แจ้งย้ายห้องจัดกิจกรรมเป็น อาคาร 5 ชั้น 3" value="{{ old('title') }}" required maxlength="150" style="width:100%;border-radius:0.5rem;font-size:0.875rem;" oninput="updatePreview()">
            </div>

            {{-- 3. เนื้อหาประกาศ --}}
            <div class="mb-3">
                <div class="flex justify-between items-center mb-1">
                    <label class="text-xs font-semibold text-dark">ข้อความประกาศ <span class="text-danger">*</span></label>
                    <span class="text-xs text-muted" id="charCount">0/1000</span>
                </div>
                <textarea name="message" id="broadcastMessage" rows="4" class="form-control" placeholder="ระบุรายละเอียดสำคัญ เช่น เนื่องจากจำนวนผู้เข้าร่วมมากกว่าที่กำหนด ขอเปลี่ยนสถานที่ไปยังห้อง 501..." required maxlength="1000" style="width:100%;border-radius:0.5rem;font-size:0.875rem;" oninput="updatePreview()">{{ old('message') }}</textarea>
            </div>

            {{-- 4. กลุ่มเป้าหมาย --}}
            <div class="mb-3">
                <label class="block text-xs font-semibold text-dark mb-1">กลุ่มนักศึกษาเป้าหมาย <span class="text-danger">*</span></label>
                <select name="target_audience" id="targetAudience" class="form-control" style="width:100%;border-radius:0.5rem;font-size:0.875rem;" required onchange="updatePreview()">
                    <option value="approved" {{ old('target_audience', 'approved') == 'approved' ? 'selected' : '' }}>
                        เฉพาะผู้ที่ได้รับการอนุมัติแล้วเท่านั้น ({{ $stats['approved_count'] }} คน) [แนะนำ]
                    </option>
                    <option value="all" {{ old('target_audience') == 'all' ? 'selected' : '' }}>
                        นักศึกษาทุกคนที่ลงทะเบียน (รวมรออนุมัติ/ตัวสำรอง) ({{ $stats['total_registered'] }} คน)
                    </option>
                    @if($stats['waitlisted_count'] > 0)
                    <option value="waitlisted" {{ old('target_audience') == 'waitlisted' ? 'selected' : '' }}>
                        เฉพาะผู้ที่อยู่ในรายชื่อสำรอง (Waitlisted: {{ $stats['waitlisted_count'] }} คน)
                    </option>
                    @endif
                </select>
            </div>

            {{-- 5. ช่องทางการส่ง --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold text-dark mb-2">ช่องทางการส่ง (Multi-Channel Dispatch):</label>
                <div class="flex gap-4" style="flex-wrap:wrap;">
                    <label class="flex items-center gap-2" style="font-size:0.875rem;cursor:pointer;">
                        <input type="checkbox" name="channels[]" value="in_app" checked style="width:1rem;height:1rem;accent-color:#4f46e5;">
                        <span class="flex items-center gap-1">
                            <svg style="width:1rem;height:1rem;color:#4f46e5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            <span>In-App & Reverb Push</span>
                        </span>
                    </label>

                    <label class="flex items-center gap-2" style="font-size:0.875rem;cursor:pointer;">
                        <input type="checkbox" name="channels[]" value="line" checked style="width:1rem;height:1rem;accent-color:#06c755;">
                        <span class="flex items-center gap-1">
                            <svg style="width:1rem;height:1rem;color:#06c755;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 5.92 2 10.75c0 2.98 1.77 5.6 4.46 7.15-.2.74-.72 2.7-1.03 3.86-.06.24.18.44.38.31 1.78-1.18 4.25-2.82 4.79-3.21.46.06.93.1 1.4.1 5.52 0 10-3.92 10-8.75S17.52 2 12 2z"/></svg>
                            <span>LINE Official Push ({{ $stats['line_linked_count'] }} คนที่เปิดรับ)</span>
                        </span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:0.75rem;font-size:0.95rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:0.5rem;" onclick="return confirm('ยืนยันส่งข้อความบรอดแคสต์ไปยังนักศึกษาในกิจกรรมนี้ทันที?')">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                <span>บรอดแคสต์ส่งข้อความด่วนทันที</span>
            </button>
        </form>
    </div>

    {{-- ซีกขวา: พรีวิวสด & ระบบ Auto-Reminder --}}
    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        {{-- การ์ดระบบ Auto-Reminder 24h & 2h --}}
        <div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;">
            <div class="flex items-center justify-between mb-3 pb-2" style="border-bottom:1px solid #f1f5f9;">
                <div class="flex items-center gap-2">
                    <span style="display:inline-flex;padding:0.35rem;background:#dcfce7;border-radius:0.375rem;color:#166534;">
                        <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </span>
                    <h2 class="font-bold" style="font-size:1.1rem;color:#0f172a;margin:0;">ระบบแจ้งเตือนอัตโนมัติ (Auto-Reminder)</h2>
                </div>
                <span class="badge" style="background:#ecfdf5;color:#059669;font-size:0.75rem;">ลด No-show</span>
            </div>
            <p class="text-xs text-muted mb-4">ระบบจะรันส่งแจ้งเตือนทั้ง In-App และ LINE อัตโนมัติทุก 15 นาทีตามหน้าต่างเวลาล่วงหน้า หรือแอดมินสามารถกดส่งทันทีได้</p>

            <div style="display:flex;flex-direction:column;gap:1rem;">
                {{-- 1. แจ้งเตือนล่วงหน้า 1 วัน (24 ชั่วโมง) --}}
                <div style="padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;">
                    <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:0.5rem;">
                        <div>
                            <div class="flex items-center gap-1 font-semibold text-sm" style="color:#0f172a;">
                                <svg style="width:1rem;height:1rem;color:#0284c7;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span>เตือนล่วงหน้า 1 วัน (24 ชั่วโมงก่อนเริ่ม)</span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                @if($stats['reminder_24h'])
                                    <span class="text-success font-medium flex items-center gap-1">
                                        <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        ส่งแล้วเมื่อ {{ \Carbon\Carbon::parse($stats['reminder_24h']->sent_at)->format('d/m/Y H:i') }} (ถึง {{ $stats['reminder_24h']->recipients_count }} คน)
                                    </span>
                                @else
                                    <span class="text-muted">ยังไม่เคยส่ง — รอคิวอัตโนมัติ หรือกดส่งได้ทันที</span>
                                @endif
                            </p>
                        </div>
                        <form action="{{ route('admin.activities.reminders.trigger', $activity) }}" method="POST" style="margin:0;">
                            @csrf
                            <input type="hidden" name="window" value="24h">
                            <button type="submit" class="btn btn-outline btn-sm text-xs flex items-center gap-1" onclick="return confirm('ต้องการส่งแจ้งเตือนล่วงหน้า 1 วัน (24h) ไปยังผู้ลงทะเบียนกิจกรรมนี้ตอนนี้ใช่หรือไม่?')">
                                <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span>ส่ง 24h ตอนนี้</span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- 2. แจ้งเตือนล่วงหน้า 2 ชั่วโมง --}}
                <div style="padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:0.5rem;">
                    <div class="flex items-center justify-between" style="flex-wrap:wrap;gap:0.5rem;">
                        <div>
                            <div class="flex items-center gap-1 font-semibold text-sm" style="color:#0f172a;">
                                <svg style="width:1rem;height:1rem;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span>เตือนล่วงหน้า 2 ชั่วโมง (ก่อนกิจกรรมเริ่ม)</span>
                            </div>
                            <p class="text-xs text-muted mt-1">
                                @if($stats['reminder_2h'])
                                    <span class="text-success font-medium flex items-center gap-1">
                                        <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        ส่งแล้วเมื่อ {{ \Carbon\Carbon::parse($stats['reminder_2h']->sent_at)->format('d/m/Y H:i') }} (ถึง {{ $stats['reminder_2h']->recipients_count }} คน)
                                    </span>
                                @else
                                    <span class="text-muted">ยังไม่เคยส่ง — รอคิวอัตโนมัติ หรือกดส่งได้ทันที</span>
                                @endif
                            </p>
                        </div>
                        <form action="{{ route('admin.activities.reminders.trigger', $activity) }}" method="POST" style="margin:0;">
                            @csrf
                            <input type="hidden" name="window" value="2h">
                            <button type="submit" class="btn btn-outline btn-sm text-xs flex items-center gap-1" onclick="return confirm('ต้องการส่งแจ้งเตือนล่วงหน้า 2 ชั่วโมง (2h) ไปยังผู้ลงทะเบียนกิจกรรมนี้ตอนนี้ใช่หรือไม่?')">
                                <svg style="width:0.85rem;height:0.85rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <span>ส่ง 2h ตอนนี้</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- กล่องพรีวิวการแจ้งเตือนสด (Live Notification Preview) --}}
        <div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;">
            <div class="flex items-center justify-between mb-3 pb-2" style="border-bottom:1px solid #f1f5f9;">
                <div class="flex items-center gap-2">
                    <span style="display:inline-flex;padding:0.35rem;background:#f1f5f9;border-radius:0.375rem;color:#475569;">
                        <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    </span>
                    <h2 class="font-bold" style="font-size:1.1rem;color:#0f172a;margin:0;">ตัวอย่างการแสดงผลฝั่งนักศึกษา</h2>
                </div>
                <span class="text-xs text-muted">In-App Notification Preview</span>
            </div>

            <div id="previewCard" style="border:1px solid #cbd5e1;border-radius:0.75rem;padding:1rem;background:#f8fafc;transition:all 0.2s;">
                <div class="flex items-start gap-3">
                    <div id="previewBadgeIcon" style="width:2.25rem;height:2.25rem;border-radius:0.5rem;background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:1.25rem;height:1.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <div style="flex:1;">
                        <div class="flex items-center justify-between">
                            <span class="badge" id="previewTypeBadge" style="background:#e0e7ff;color:#4338ca;font-size:0.7rem;">ประกาศทั่วไป</span>
                            <span class="text-xs text-muted">เมื่อสักครู่</span>
                        </div>
                        <h4 id="previewTitle" class="font-bold text-sm mt-1" style="color:#0f172a;">[ประกาศด่วน] หัวข้อประกาศจะแสดงที่นี่</h4>
                        <p id="previewMessage" class="text-xs text-muted mt-1" style="line-height:1.4;white-space:pre-wrap;">เนื้อหาข้อความประกาศที่คุณพิมพ์จะแสดงตัวอย่างสดในกล่องนี้...</p>
                        <div class="mt-2 pt-2 text-xs flex items-center justify-between" style="border-top:1px dashed #e2e8f0;color:#64748b;">
                            <span>กิจกรรม: {{ Str::limit($activity->title, 25) }}</span>
                            <span class="text-primary font-medium">แตะเพื่อดูกิจกรรม &rarr;</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ประวัติการบรอดแคสต์ข้อความ (Broadcast History) --}}
<div class="card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:0.75rem;padding:1.5rem;">
    <div class="flex items-center justify-between mb-4 pb-3" style="border-bottom:1px solid #f1f5f9;">
        <div class="flex items-center gap-2">
            <span style="display:inline-flex;padding:0.35rem;background:#f1f5f9;border-radius:0.375rem;color:#475569;">
                <svg style="width:1.1rem;height:1.1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </span>
            <h2 class="font-bold" style="font-size:1.1rem;color:#0f172a;margin:0;">ประวัติการบรอดแคสต์ในกิจกรรมนี้</h2>
        </div>
        <span class="text-xs text-muted">ทั้งหมด {{ $stats['broadcast_history']->count() }} รายการ</span>
    </div>

    @if($stats['broadcast_history']->isEmpty())
        <div class="text-center py-8 text-muted" style="font-size:0.875rem;">
            <svg style="width:2.5rem;height:2.5rem;margin:0 auto 0.75rem;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
            <p>ยังไม่มีประวัติการส่งบรอดแคสต์สำหรับกิจกรรมนี้</p>
        </div>
    @else
        <div style="overflow-x:auto;">
            <table class="responsive-table" style="width:100%;font-size:0.875rem;">
                <thead>
                    <tr style="border-bottom:1px solid #e2e8f0;text-align:left;">
                        <th style="padding:0.75rem;">วัน/เวลา</th>
                        <th style="padding:0.75rem;">ประเภท</th>
                        <th style="padding:0.75rem;">หัวข้อ & ข้อความ</th>
                        <th style="padding:0.75rem;">กลุ่มเป้าหมาย</th>
                        <th style="padding:0.75rem;" class="text-center">ผู้รับ In-App</th>
                        <th style="padding:0.75rem;" class="text-center">ผู้รับ LINE</th>
                        <th style="padding:0.75rem;">ผู้ส่ง</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats['broadcast_history'] as $b)
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.75rem;white-space:nowrap;" class="text-xs text-muted">
                            {{ $b->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td style="padding:0.75rem;">
                            @if($b->type === 'urgent')
                                <span class="badge badge-red" style="font-size:0.7rem;">ประกาศด่วน</span>
                            @elseif($b->type === 'venue_change')
                                <span class="badge" style="background:#fee2e2;color:#b91c1c;font-size:0.7rem;">ย้ายสถานที่</span>
                            @elseif($b->type === 'time_change')
                                <span class="badge" style="background:#fef3c7;color:#b45309;font-size:0.7rem;">เลื่อนเวลา</span>
                            @elseif($b->type === 'reminder')
                                <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:0.7rem;">เตือนเตรียมตัว</span>
                            @else
                                <span class="badge" style="background:#e0e7ff;color:#4338ca;font-size:0.7rem;">ทั่วไป</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem;">
                            <div class="font-bold text-dark" style="font-size:0.875rem;">{{ $b->title }}</div>
                            <div class="text-xs text-muted mt-1" style="max-width:350px;white-space:pre-wrap;">{{ Str::limit($b->message, 120) }}</div>
                        </td>
                        <td style="padding:0.75rem;">
                            @if($b->target_audience === 'approved')
                                <span class="text-xs font-medium text-success">ผู้ได้รับอนุมัติ</span>
                            @elseif($b->target_audience === 'waitlisted')
                                <span class="text-xs font-medium" style="color:#d97706;">รายชื่อสำรอง</span>
                            @else
                                <span class="text-xs font-medium text-muted">ผู้ลงทะเบียนทุกคน</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem;" class="text-center font-semi">
                            {{ $b->recipients_count }} คน
                        </td>
                        <td style="padding:0.75rem;" class="text-center">
                            @if($b->line_sent_count > 0)
                                <span class="badge" style="background:#f0fdf4;color:#16a34a;font-size:0.75rem;">{{ $b->line_sent_count }} คน</span>
                            @else
                                <span class="text-xs text-muted">-</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem;" class="text-xs text-muted">
                            {{ $b->sender->full_name ?? $b->sender->name ?? 'แอดมิน' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<script>
function setPreset(type) {
    const titleInput = document.getElementById('broadcastTitle');
    const messageInput = document.getElementById('broadcastMessage');
    const typeSelect = document.getElementById('broadcastType');

    const activityLocation = "{{ addslashes($activity->location ?? 'สถานที่เดิม') }}";
    const activityDate = "{{ $activity->activity_date->format('d/m/Y') }}";
    const activityTime = "{{ \Carbon\Carbon::parse($activity->start_time)->format('H:i') }} น.";

    if (type === 'venue') {
        typeSelect.value = 'venue_change';
        titleInput.value = 'แจ้งเปลี่ยนแปลงสถานที่จัดกิจกรรม';
        messageInput.value = `เนื่องจากมีความจำเป็น ขอแจ้งย้ายสถานที่จัดกิจกรรมไปยัง [ระบุสถานที่ใหม่] ในวันและเวลาเดิม (${activityDate} เวลา ${activityTime}) กรุณาตรวจสอบและเดินทางมายังสถานที่ใหม่ ขอบคุณครับ/ค่ะ`;
    } else if (type === 'time') {
        typeSelect.value = 'time_change';
        titleInput.value = 'แจ้งเปลี่ยนแปลงเวลาจัดกิจกรรม';
        messageInput.value = `ขอแจ้งปรับเปลี่ยนเวลากิจกรรมในวันที่ ${activityDate} เป็นเวลา [ระบุเวลาใหม่] ณ ${activityLocation} ขออภัยในความไม่สะดวกที่เกิดขึ้นครับ/ค่ะ`;
    } else if (type === 'urgent') {
        typeSelect.value = 'urgent';
        titleInput.value = 'ประกาศด่วนสำคัญจากผู้จัดกิจกรรม';
        messageInput.value = `ขอความร่วมมือนักศึกษาทุกคนที่ลงทะเบียนในกิจกรรม กรุณา [ระบุสิ่งที่ต้องการให้นักศึกษาทำทันที] ณ ${activityLocation}`;
    } else if (type === 'prep') {
        typeSelect.value = 'reminder';
        titleInput.value = 'สิ่งที่ต้องเตรียมตัวก่อนเข้าร่วมกิจกรรม';
        messageInput.value = `เพื่อความพร้อมในการทำกิจกรรม ขอให้นักศึกษาเตรียม: 1. บัตรนักศึกษา 2. สมาร์ทโฟนสำหรับสแกนเข้างาน 3. แต่งกายชุดนักศึกษาถูกระเบียบ เจอกันวันที่ ${activityDate} เวลา ${activityTime}`;
    }

    updatePreview();
}

function updatePreview() {
    const titleVal = document.getElementById('broadcastTitle').value.trim();
    const msgVal = document.getElementById('broadcastMessage').value.trim();
    const typeVal = document.getElementById('broadcastType').value;

    const charCountEl = document.getElementById('charCount');
    charCountEl.innerText = `${msgVal.length}/1000`;

    const previewTitle = document.getElementById('previewTitle');
    const previewMessage = document.getElementById('previewMessage');
    const previewBadge = document.getElementById('previewTypeBadge');
    const previewBadgeIcon = document.getElementById('previewBadgeIcon');

    previewTitle.innerText = titleVal ? `[ประกาศ] ${titleVal}` : '[ประกาศด่วน] หัวข้อประกาศจะแสดงที่นี่';
    previewMessage.innerText = msgVal ? msgVal : 'เนื้อหาข้อความประกาศที่คุณพิมพ์จะแสดงตัวอย่างสดในกล่องนี้...';

    if (typeVal === 'urgent' || typeVal === 'venue_change') {
        previewBadge.innerText = typeVal === 'urgent' ? 'ประกาศด่วนสำคัญ' : 'ย้ายสถานที่';
        previewBadge.style.background = '#fee2e2';
        previewBadge.style.color = '#dc2626';
        previewBadgeIcon.style.background = '#fee2e2';
        previewBadgeIcon.style.color = '#dc2626';
    } else if (typeVal === 'time_change') {
        previewBadge.innerText = 'แจ้งเปลี่ยนเวลา';
        previewBadge.style.background = '#fef3c7';
        previewBadge.style.color = '#b45309';
        previewBadgeIcon.style.background = '#fef3c7';
        previewBadgeIcon.style.color = '#b45309';
    } else if (typeVal === 'reminder') {
        previewBadge.innerText = 'เตือนเตรียมตัว';
        previewBadge.style.background = '#e0f2fe';
        previewBadge.style.color = '#0284c7';
        previewBadgeIcon.style.background = '#e0f2fe';
        previewBadgeIcon.style.color = '#0284c7';
    } else {
        previewBadge.innerText = 'ประกาศทั่วไป';
        previewBadge.style.background = '#e0e7ff';
        previewBadge.style.color = '#4338ca';
        previewBadgeIcon.style.background = '#e0e7ff';
        previewBadgeIcon.style.color = '#4338ca';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    updatePreview();
});
</script>
@endsection
