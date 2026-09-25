{{-- ปฏิทินกิจกรรมอัจฉริยะ (Interactive Master Calendar) — Admin & Staff --}}
@extends('layouts.admin')
@section('title', 'ปฏิทินกิจกรรมอัจฉริยะ')

@section('styles')
{{-- FullCalendar v6 CDN --}}
<link href="https://unpkg.com/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════════════
   Master Calendar & Conflict Warning Styles
   ══════════════════════════════════════════════════════════ */
.cal-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 1024px) {
    .cal-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .cal-stats-grid { grid-template-columns: 1fr; }
}

.cal-stat-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.cal-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.cal-stat-card.alert-conflict {
    border-color: #fca5a5;
    background: linear-gradient(135deg, rgba(254,242,242,0.85) 0%, rgba(255,255,255,0.95) 100%);
    cursor: pointer;
}
html[data-theme="dark"] .cal-stat-card.alert-conflict {
    background: linear-gradient(135deg, rgba(127,29,29,0.2) 0%, rgba(24,24,27,0.95) 100%);
    border-color: rgba(239,68,68,0.35);
}
.cal-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Calendar Filter Toolbar */
.cal-filter-toolbar {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 14px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
}
.cal-filter-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .6rem;
}
.cal-select {
    padding: .45rem .85rem;
    border-radius: 8px;
    border: 1px solid var(--border-color, #cbd5e1);
    background: var(--bg-main, #ffffff);
    color: var(--text-main, #1e293b);
    font-size: .85rem;
    outline: none;
    transition: border-color .15s;
}
.cal-select:focus {
    border-color: #f97316;
}
.btn-conflict-toggle {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .9rem;
    border-radius: 8px;
    font-size: .82rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #b91c1c;
    transition: all .15s ease;
}
.btn-conflict-toggle:hover {
    background: #fee2e2;
    border-color: #f87171;
}
.btn-conflict-toggle.active {
    background: #dc2626;
    color: #ffffff;
    border-color: #b91c1c;
    box-shadow: 0 2px 6px rgba(220,38,38,0.3);
}

/* FullCalendar Styling Overrides */
.cal-container {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    min-height: 650px;
}
.fc .fc-toolbar {
    flex-wrap: wrap;
    gap: .75rem;
    margin-bottom: 1.25rem !important;
}
.fc .fc-toolbar-title {
    font-size: 1.2rem !important;
    font-weight: 700 !important;
    color: var(--text-main, #0f172a);
}
.fc .fc-button {
    font-family: inherit !important;
    font-size: .82rem !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    padding: .4rem .85rem !important;
    text-transform: none !important;
    box-shadow: none !important;
}
.fc .fc-button-primary {
    background: var(--bg-main, #f8fafc) !important;
    border: 1px solid var(--border-color, #cbd5e1) !important;
    color: var(--text-main, #334155) !important;
}
.fc .fc-button-primary:hover {
    background: #f1f5f9 !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
}
.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active {
    background: #ea580c !important;
    border-color: #ea580c !important;
    color: #ffffff !important;
}
.fc-theme-standard td, .fc-theme-standard th {
    border-color: var(--border-color, #e2e8f0) !important;
}
.fc .fc-daygrid-day-number {
    font-size: .82rem !important;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    padding: 6px 8px !important;
}
.fc .fc-day-today {
    background: rgba(249, 115, 22, 0.04) !important;
}
.fc .fc-day-today .fc-daygrid-day-number {
    color: #ea580c !important;
    font-weight: 700 !important;
}

/* Event cards inside FullCalendar */
.fc .fc-event {
    border-radius: 6px !important;
    padding: 2px 4px !important;
    font-size: .75rem !important;
    line-height: 1.35 !important;
    cursor: pointer;
    transition: transform .12s ease, filter .12s ease;
    border-width: 1px !important;
}
.fc .fc-event:hover {
    filter: brightness(1.08);
    transform: scale(1.01);
}
.fc-event-conflict-badge {
    display: inline-flex;
    align-items: center;
    background: #991b1b;
    color: #fff;
    font-size: .62rem;
    font-weight: 700;
    padding: 0 4px;
    border-radius: 4px;
    margin-right: 3px;
    vertical-align: middle;
}

/* Slide-over Event Detail Drawer / Modal */
.cal-drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: opacity .25s ease, visibility .25s ease;
    display: flex;
    justify-content: flex-end;
}
.cal-drawer-overlay.active {
    opacity: 1;
    visibility: visible;
}
.cal-drawer-panel {
    background: var(--card-bg, #ffffff);
    width: 100%;
    max-width: 520px;
    height: 100%;
    box-shadow: -4px 0 24px rgba(0,0,0,0.15);
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transform: translateX(100%);
    transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1);
}
.cal-drawer-overlay.active .cal-drawer-panel {
    transform: translateX(0);
}
.drawer-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    position: sticky;
    top: 0;
    background: var(--card-bg, #ffffff);
    z-index: 10;
}
.drawer-body {
    padding: 1.5rem;
    flex: 1;
}
.drawer-footer {
    padding: 1.25rem 1.5rem;
    border-top: 1px solid var(--border-color, #e2e8f0);
    background: var(--bg-main, #f8fafc);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: .75rem;
}

/* Conflict Alert in Modal */
.conflict-alert-box {
    background: #fef2f2;
    border: 1px solid #f87171;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    animation: pulseBorder 2s infinite;
}
@keyframes pulseBorder {
    0%, 100% { border-color: #f87171; }
    50% { border-color: #ef4444; box-shadow: 0 0 10px rgba(239,68,68,0.25); }
}
html[data-theme="dark"] .conflict-alert-box {
    background: rgba(127, 29, 29, 0.25);
    border-color: #ef4444;
}

/* Dark mode tweaks */
html[data-theme="dark"] .cal-select {
    background: #27272a;
    border-color: #3f3f46;
    color: #f4f4f5;
}
html[data-theme="dark"] .fc .fc-toolbar-title {
    color: #f4f4f5;
}
html[data-theme="dark"] .fc .fc-button-primary {
    background: #27272a !important;
    border-color: #3f3f46 !important;
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .fc .fc-button-primary:hover {
    background: #3f3f46 !important;
}
html[data-theme="dark"] .fc .fc-list-day-cushion {
    background-color: #27272a !important;
}
html[data-theme="dark"] .fc .fc-list-event:hover td {
    background-color: #27272a !important;
    color: #ffffff !important;
}
html[data-theme="dark"] .fc-theme-standard td,
html[data-theme="dark"] .fc-theme-standard th,
html[data-theme="dark"] .fc-theme-standard .fc-scrollgrid {
    border-color: #27272a !important;
}
html[data-theme="dark"] .fc-col-header-cell-cushion,
html[data-theme="dark"] .fc-daygrid-day-number,
html[data-theme="dark"] .fc-list-day-text,
html[data-theme="dark"] .fc-list-day-side-text,
html[data-theme="dark"] .fc-list-event-title,
html[data-theme="dark"] .fc-list-event-time {
    color: #f4f4f5 !important;
}
</style>
@endsection

@section('content')
<div class="calendar-page-container">
    {{-- Top Header Section --}}
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                <span style="font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#ea580c; background:rgba(234,88,12,0.1); padding:2px 8px; border-radius:6px;">
                    Smart Conflict Detection
                </span>
                <span style="color:var(--text-muted, #94a3b8); font-size:0.8rem;">• ป้องกันสถานที่ชนกัน</span>
            </div>
            <h1 class="font-bold" style="font-size:1.65rem; color:var(--text-main, #0f172a); margin:0; line-height:1.3;">
                ปฏิทินกิจกรรมอัจฉริยะ (Master Calendar)
            </h1>
            <p style="font-size:0.875rem; color:var(--text-muted, #64748b); margin-top:0.25rem;">
                ตรวจสอบภาพรวมตารางกิจกรรม ป้องกันการแย่งห้องประชุม/หอประชุม และแจ้งเตือนกิจกรรมที่ทับซ้อนกันแบบอัตโนมัติ
            </p>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <a href="{{ route('admin.activities.create') }}" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 1.1rem; border-radius:10px; font-weight:600;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                สร้างกิจกรรมใหม่
            </a>
        </div>
    </div>

    {{-- Top 4 Summary Stat Cards --}}
    <div class="cal-stats-grid">
        {{-- Card 1: Total Month --}}
        <div class="cal-stat-card">
            <div class="cal-stat-icon" style="background:rgba(59,130,246,0.12); color:#2563eb;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div style="font-size:0.78rem; font-weight:600; color:var(--text-muted, #64748b);">กิจกรรมเดือนนี้</div>
                <div style="font-size:1.45rem; font-weight:700; color:var(--text-main, #0f172a); line-height:1.2;">{{ number_format($stats['total_month']) }}</div>
                <div style="font-size:0.72rem; color:#2563eb; margin-top:2px;">รอบเดือนปัจจุบัน</div>
            </div>
        </div>

        {{-- Card 2: Upcoming / Open --}}
        <div class="cal-stat-card">
            <div class="cal-stat-icon" style="background:rgba(217,119,6,0.12); color:#d97706;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div style="font-size:0.78rem; font-weight:600; color:var(--text-muted, #64748b);">รอจัด / เปิดรับสมัคร</div>
                <div style="font-size:1.45rem; font-weight:700; color:var(--text-main, #0f172a); line-height:1.2;">{{ number_format($stats['upcoming']) }}</div>
                <div style="font-size:0.72rem; color:#d97706; margin-top:2px;">กิจกรรมที่กำลังจะมาถึง</div>
            </div>
        </div>

        {{-- Card 3: Ongoing --}}
        <div class="cal-stat-card">
            <div class="cal-stat-icon" style="background:rgba(5,150,105,0.12); color:#059669;">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div style="font-size:0.78rem; font-weight:600; color:var(--text-muted, #64748b);">กำลังจัดกิจกรรม</div>
                <div style="font-size:1.45rem; font-weight:700; color:var(--text-main, #0f172a); line-height:1.2;">{{ number_format($stats['ongoing']) }}</div>
                <div style="font-size:0.72rem; color:#059669; margin-top:2px;">Active ตอนนี้</div>
            </div>
        </div>

        {{-- Card 4: Conflicts Alert --}}
        <div class="cal-stat-card {{ $stats['conflicts_count'] > 0 ? 'alert-conflict' : '' }}" onclick="toggleConflictFilter(true)" title="คลิกเพื่อกรองเฉพาะกิจกรรมที่ชนกัน">
            <div class="cal-stat-icon" style="background:{{ $stats['conflicts_count'] > 0 ? '#ef4444' : 'rgba(100,116,139,0.12)' }}; color:{{ $stats['conflicts_count'] > 0 ? '#ffffff' : '#64748b' }};">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div style="flex:1;">
                <div style="font-size:0.78rem; font-weight:600; color:{{ $stats['conflicts_count'] > 0 ? '#b91c1c' : 'var(--text-muted, #64748b)' }};">
                    ตรวจพบสถานที่ชนกัน
                </div>
                <div style="font-size:1.45rem; font-weight:700; color:{{ $stats['conflicts_count'] > 0 ? '#dc2626' : 'var(--text-main, #0f172a)' }}; line-height:1.2;">
                    {{ number_format($stats['conflicts_count']) }}
                </div>
                <div style="font-size:0.72rem; color:{{ $stats['conflicts_count'] > 0 ? '#b91c1c' : '#16a34a' }}; margin-top:2px;">
                    {{ $stats['conflicts_count'] > 0 ? 'คลิกดูรายการที่ทับซ้อน' : 'ไม่มีกิจกรรมชนกัน ปกติดี' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Toolbar --}}
    <div class="cal-filter-toolbar">
        <div class="cal-filter-group">
            {{-- Category Filter --}}
            <select id="filterCategory" class="cal-select" onchange="refetchCalendarEvents()">
                <option value="">ทุกหมวดหมู่กิจกรรม</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>

            {{-- Venue / Location Filter --}}
            <select id="filterLocation" class="cal-select" onchange="refetchCalendarEvents()">
                <option value="">ทุกสถานที่จัดงาน</option>
                @foreach($uniqueLocations as $loc)
                    <option value="{{ $loc }}">{{ $loc }}</option>
                @endforeach
            </select>

            {{-- Status Filter --}}
            <select id="filterStatus" class="cal-select" onchange="refetchCalendarEvents()">
                <option value="all">ทุกสถานะกิจกรรม</option>
                <option value="upcoming">กำลังจะเปิด</option>
                <option value="open">เปิดรับสมัคร</option>
                <option value="ongoing">กำลังจัดกิจกรรม</option>
                <option value="done">เสร็จสิ้น</option>
                <option value="cancelled">ยกเลิก</option>
            </select>

            {{-- Only Conflicts Toggle Button --}}
            <button type="button" id="btnOnlyConflicts" class="btn-conflict-toggle" onclick="toggleConflictFilter()">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span id="conflictToggleText">แสดงเฉพาะรายการที่ชนกัน ({{ $stats['conflicts_count'] }})</span>
            </button>
        </div>

        <div class="cal-filter-group">
            {{-- View Legend Button / Indicators --}}
            <div style="display:flex; align-items:center; gap:0.6rem; font-size:0.75rem; color:var(--text-muted, #64748b);">
                <span style="display:inline-flex; align-items:center; gap:3px;">
                    <span style="width:10px; height:10px; border-radius:50%; background:#dc2626; display:inline-block;"></span> ชนกัน/ซ้ำซ้อน
                </span>
                <span style="display:inline-flex; align-items:center; gap:3px;">
                    <span style="width:10px; height:10px; border-radius:50%; background:#059669; display:inline-block;"></span> กำลังจัด
                </span>
                <span style="display:inline-flex; align-items:center; gap:3px;">
                    <span style="width:10px; height:10px; border-radius:50%; background:#2563eb; display:inline-block;"></span> เปิดรับ
                </span>
                <span style="display:inline-flex; align-items:center; gap:3px;">
                    <span style="width:10px; height:10px; border-radius:50%; background:#d97706; display:inline-block;"></span> กำลังจะเปิด
                </span>
            </div>

            {{-- Refresh Button --}}
            <button type="button" class="btn btn-outline btn-sm" onclick="refetchCalendarEvents()" title="รีเฟรชข้อมูล" style="padding:0.4rem 0.65rem; border-radius:8px;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>
    </div>

    {{-- FullCalendar Container --}}
    <div class="cal-container">
        <div id="master-calendar"></div>
    </div>
</div>

{{-- Slide-over Event Detail Drawer --}}
<div id="calendarDrawerOverlay" class="cal-drawer-overlay" onclick="if(event.target===this)closeCalendarDrawer()">
    <div class="cal-drawer-panel">
        {{-- Header --}}
        <div class="drawer-header">
            <div>
                <div id="drawerCategoryPill" style="display:inline-block; font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#ea580c; background:rgba(234,88,12,0.1); padding:2px 8px; border-radius:6px; margin-bottom:0.4rem;">
                    หมวดหมู่
                </div>
                <h3 id="drawerTitle" style="font-size:1.2rem; font-weight:700; color:var(--text-main, #0f172a); margin:0; line-height:1.4;">
                    ชื่อกิจกรรม
                </h3>
            </div>
            <button type="button" onclick="closeCalendarDrawer()" style="background:none; border:none; cursor:pointer; color:var(--text-muted, #64748b); width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; transition:background .15s;" title="ปิด">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="drawer-body">
            {{-- Conflict Alert Container (Dynamic) --}}
            <div id="drawerConflictBox" class="conflict-alert-box" style="display:none;">
                <div style="display:flex; align-items:flex-start; gap:0.6rem;">
                    <div style="color:#dc2626; flex-shrink:0; margin-top:1px;">
                        <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:0.95rem; font-weight:700; color:#991b1b; margin-bottom:0.2rem;">
                            ตรวจพบกิจกรรมที่ชนกันในสถานที่นี้!
                        </div>
                        <p id="drawerConflictDesc" style="font-size:0.8rem; color:#7f1d1d; margin:0 0 0.6rem 0;">
                            มีกิจกรรมอื่นที่ใช้สถานที่เดียวกันและช่วงเวลาทับซ้อนกัน โปรดตรวจสอบหรือจัดตารางเวลาใหม่
                        </p>
                        <div id="drawerConflictList" style="display:grid; gap:0.5rem;">
                            {{-- Conflicting activity items rendered via JS --}}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Activity Info Grid --}}
            <div style="display:grid; gap:0.85rem; margin-bottom:1.5rem;">
                {{-- Date --}}
                <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.875rem; color:var(--text-main, #334155);">
                    <div style="width:34px; height:34px; border-radius:8px; background:rgba(234,88,12,0.1); color:#ea580c; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; color:var(--text-muted, #64748b);">วันที่จัดกิจกรรม</div>
                        <div id="drawerDate" style="font-weight:600;">-</div>
                    </div>
                </div>

                {{-- Time --}}
                <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.875rem; color:var(--text-main, #334155);">
                    <div style="width:34px; height:34px; border-radius:8px; background:rgba(14,165,233,0.1); color:#0284c7; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; color:var(--text-muted, #64748b);">ช่วงเวลา</div>
                        <div id="drawerTime" style="font-weight:600;">-</div>
                    </div>
                </div>

                {{-- Location --}}
                <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.875rem; color:var(--text-main, #334155);">
                    <div style="width:34px; height:34px; border-radius:8px; background:rgba(220,38,38,0.1); color:#dc2626; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; color:var(--text-muted, #64748b);">สถานที่จัดกิจกรรม</div>
                        <div id="drawerLocation" style="font-weight:600;">-</div>
                    </div>
                </div>

                {{-- Quota / Participants --}}
                <div style="display:flex; align-items:center; gap:0.75rem; font-size:0.875rem; color:var(--text-main, #334155);">
                    <div style="width:34px; height:34px; border-radius:8px; background:rgba(16,185,129,0.1); color:#059669; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:0.72rem; color:var(--text-muted, #64748b);">จำนวนผู้ลงทะเบียน</div>
                        <div id="drawerParticipants" style="font-weight:600;">0 / 0 คน</div>
                    </div>
                </div>

                {{-- Hours & Creator --}}
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; padding-top:0.5rem; border-top:1px solid var(--border-color, #e2e8f0);">
                    <div>
                        <div style="font-size:0.72rem; color:var(--text-muted, #64748b);">ชั่วโมงกิจกรรม</div>
                        <div id="drawerHours" style="font-weight:700; color:#ea580c; font-size:1.05rem;">0.0 ชม.</div>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; color:var(--text-muted, #64748b);">ผู้สร้างกิจกรรม</div>
                        <div id="drawerCreator" style="font-weight:600; font-size:0.875rem;">-</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer Action Buttons --}}
        <div class="drawer-footer">
            <a id="drawerBtnEdit" href="#" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                แก้ไขกิจกรรม
            </a>
            <a id="drawerBtnParticipants" href="#" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                รายชื่อผู้เข้าร่วม
            </a>
            <a id="drawerBtnView" href="#" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                ดูรายละเอียด
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- FullCalendar v6 CDN --}}
<script src="https://unpkg.com/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://unpkg.com/@fullcalendar/core@6.1.11/locales/th.global.min.js"></script>

<script>
var CAL_EVENTS_URL = '{{ route("admin.calendar.events") }}';
var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';
var calendarInstance = null;
var isOnlyConflictsActive = false;

document.addEventListener('DOMContentLoaded', function() {
    var calEl = document.getElementById('master-calendar');

    calendarInstance = new FullCalendar.Calendar(calEl, {
        locale: 'th',
        initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        buttonText: {
            today: 'วันนี้',
            month: 'เดือน',
            week:  'สัปดาห์',
            day:   'วัน',
            list:  'รายการ'
        },
        height: 'auto',
        dayMaxEvents: 3,
        nowIndicator: true,
        events: function(fetchInfo, successCallback, failureCallback) {
            var params = new URLSearchParams({
                start: fetchInfo.startStr,
                end: fetchInfo.endStr,
                category_id: document.getElementById('filterCategory').value || '',
                location: document.getElementById('filterLocation').value || '',
                status: document.getElementById('filterStatus').value || 'all',
                only_conflicts: isOnlyConflictsActive ? '1' : '0'
            });

            fetch(CAL_EVENTS_URL + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                successCallback(data);
            })
            .catch(function(err) {
                console.error('Error fetching calendar events:', err);
                failureCallback(err);
            });
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            openCalendarDrawer(info.event);
        },
        eventContent: function(arg) {
            var ep = arg.event.extendedProps;
            var isConflict = ep.is_conflict;

            var container = document.createElement('div');
            container.style.overflow = 'hidden';
            container.style.textOverflow = 'ellipsis';
            container.style.whiteSpace = 'nowrap';
            container.style.display = 'flex';
            container.style.alignItems = 'center';
            container.style.gap = '3px';

            if (isConflict) {
                var badge = document.createElement('span');
                badge.className = 'fc-event-conflict-badge';
                badge.innerHTML = '<svg width="9" height="9" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:2px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> ชน!';
                container.appendChild(badge);
            }

            var titleSpan = document.createElement('span');
            titleSpan.style.fontWeight = isConflict ? '700' : '500';
            titleSpan.textContent = arg.event.title;
            container.appendChild(titleSpan);

            if (ep.location && ep.location !== '-') {
                var locSpan = document.createElement('span');
                locSpan.style.opacity = '0.85';
                locSpan.style.fontSize = '0.68rem';
                locSpan.textContent = ' (' + ep.location + ')';
                container.appendChild(locSpan);
            }

            return { domNodes: [container] };
        },
        noEventsContent: 'ไม่มีกิจกรรมในช่วงเวลาหรือเงื่อนไขที่เลือก'
    });

    calendarInstance.render();
});

function refetchCalendarEvents() {
    if (calendarInstance) {
        calendarInstance.refetchEvents();
    }
}

function toggleConflictFilter(forceState) {
    if (typeof forceState === 'boolean') {
        isOnlyConflictsActive = forceState;
    } else {
        isOnlyConflictsActive = !isOnlyConflictsActive;
    }

    var btn = document.getElementById('btnOnlyConflicts');
    if (isOnlyConflictsActive) {
        btn.classList.add('active');
    } else {
        btn.classList.remove('active');
    }

    refetchCalendarEvents();
}

function openCalendarDrawer(event) {
    var ep = event.extendedProps;

    document.getElementById('drawerTitle').textContent = event.title;
    document.getElementById('drawerCategoryPill').textContent = ep.category || 'ทั่วไป';
    document.getElementById('drawerLocation').textContent = ep.location || '-';
    document.getElementById('drawerHours').textContent = (ep.hours || 0).toFixed(1) + ' ชม.';
    document.getElementById('drawerCreator').textContent = ep.creator_name || '-';
    document.getElementById('drawerParticipants').textContent = (ep.registered_count || 0) + ' / ' + (ep.max_participants || 0) + ' คน';

    // Dates & Times formatting
    var start = event.start;
    var dateDisplay = start ? start.toLocaleDateString('th-TH', {
        year: 'numeric', month: 'long', day: 'numeric', weekday: 'short'
    }) : '-';
    document.getElementById('drawerDate').textContent = dateDisplay;

    var timeDisplay = (ep.start_time || '00:00') + ' - ' + (ep.end_time || '23:59') + ' น.';
    document.getElementById('drawerTime').textContent = timeDisplay;

    // Action button links
    document.getElementById('drawerBtnEdit').href = ep.edit_url || '#';
    document.getElementById('drawerBtnParticipants').href = ep.participants_url || '#';
    document.getElementById('drawerBtnView').href = ep.view_url || '#';

    // Conflict Alert Rendering
    var conflictBox = document.getElementById('drawerConflictBox');
    var conflictList = document.getElementById('drawerConflictList');
    conflictList.innerHTML = '';

    if (ep.is_conflict && ep.conflicts && ep.conflicts.length > 0) {
        conflictBox.style.display = 'block';
        document.getElementById('drawerConflictDesc').textContent =
            'ตรวจพบการจัดกิจกรรมชนกัน ' + ep.conflicts.length + ' รายการในสถานที่ "' + (ep.location || '') + '" ในช่วงเวลาเดียวกัน:';

        ep.conflicts.forEach(function(c) {
            var item = document.createElement('div');
            item.style.padding = '8px 10px';
            item.style.borderRadius = '8px';
            item.style.background = '#ffffff';
            item.style.border = '1px solid #fecaca';
            item.style.display = 'flex';
            item.style.alignItems = 'center';
            item.style.justifyContent = 'space-between';
            item.style.gap = '8px';

            item.innerHTML = `
                <div>
                    <div style="font-weight:600; font-size:0.82rem; color:#991b1b;">${c.title}</div>
                    <div style="font-size:0.72rem; color:#64748b;">เวลา: ${c.time_display}</div>
                </div>
                <div style="display:flex; gap:4px;">
                    <a href="${c.edit_url}" target="_blank" class="btn btn-xs btn-outline" style="padding:2px 8px; font-size:0.72rem; border-color:#f87171; color:#991b1b;" title="แก้ไขกิจกรรมที่ชน">
                        เปลี่ยนเวลา/ห้อง
                    </a>
                </div>
            `;
            conflictList.appendChild(item);
        });
    } else {
        conflictBox.style.display = 'none';
    }

    // Open drawer
    var overlay = document.getElementById('calendarDrawerOverlay');
    overlay.classList.add('active');
}

function closeCalendarDrawer() {
    var overlay = document.getElementById('calendarDrawerOverlay');
    overlay.classList.remove('active');
}
</script>
@endsection
