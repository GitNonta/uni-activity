{{-- Admin Professional Command Center Dashboard --}}
@extends('layouts.admin')
@section('title', 'ภาพรวมระบบ')

@section('styles')
<style>
/* ══════════════════════════════════════════════════════════
   Command Center Dashboard — Modern Glassmorphism & SVG Design
   ══════════════════════════════════════════════════════════ */
:root {
    --dash-primary: #f97316;
    --dash-primary-dark: #ea580c;
    --dash-success: #10b981;
    --dash-info: #0ea5e9;
    --dash-warning: #f59e0b;
    --dash-danger: #ef4444;
}

/* Hero Command Bar */
.dashboard-hero {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.08) 0%, rgba(234, 88, 12, 0.03) 50%, rgba(255, 255, 255, 0) 100%);
    border: 1px solid rgba(249, 115, 22, 0.18);
    border-radius: 20px;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(8px);
}
html[data-theme="dark"] .dashboard-hero,
html.dark .dashboard-hero {
    background: linear-gradient(135deg, rgba(249, 115, 22, 0.12) 0%, rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(249, 115, 22, 0.25);
}

.hero-pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981;
    display: inline-block;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: pulse-green 2s infinite;
}
@keyframes pulse-green {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* System Health Strip */
.health-strip {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 14px;
    padding: 0.85rem 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.breakdown-bar {
    height: 8px;
    border-radius: 999px;
    display: flex;
    overflow: hidden;
    background: #e2e8f0;
}
html[data-theme="dark"] .breakdown-bar,
html.dark .breakdown-bar {
    background: #334155;
}

/* KPI Cards */
.kpi-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 16px;
    padding: 1.25rem;
    position: relative;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.08);
    border-color: rgba(249, 115, 22, 0.35);
}
html[data-theme="dark"] .kpi-card:hover,
html.dark .kpi-card:hover {
    box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.45);
    border-color: rgba(249, 115, 22, 0.45);
}

.kpi-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.trend-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 999px;
}
.trend-up {
    background: #dcfce7;
    color: #15803d;
}
.trend-down {
    background: #fee2e2;
    color: #b91c1c;
}
.trend-neutral {
    background: #f1f5f9;
    color: #64748b;
}
html[data-theme="dark"] .trend-up, html.dark .trend-up { background: rgba(22, 163, 74, 0.2); color: #4ade80; }
html[data-theme="dark"] .trend-down, html.dark .trend-down { background: rgba(220, 38, 38, 0.2); color: #f87171; }
html[data-theme="dark"] .trend-neutral, html.dark .trend-neutral { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }

/* Quick Action Shortcuts */
.quick-action-tile {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 1rem 0.75rem;
    border-radius: 12px;
    border: 1px solid var(--border, #e2e8f0);
    background: var(--surface, #ffffff);
    color: var(--text-main, #1e293b);
    text-decoration: none;
    transition: all 0.2s ease;
    gap: 0.5rem;
}
.quick-action-tile:hover {
    background: rgba(249, 115, 22, 0.05);
    border-color: rgba(249, 115, 22, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
}
.quick-action-tile span {
    font-size: 0.8rem;
    font-weight: 600;
}

/* Audit Log Rows */
.dashboard-audit-row {
    transition: background 0.15s;
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--border, #f1f5f9);
}
.dashboard-audit-row:hover {
    background: #fafbfe;
}
html[data-theme="dark"] .dashboard-audit-row:hover,
html.dark .dashboard-audit-row:hover {
    background: #27272a !important;
}

/* 2-Column Responsive Layout */
.command-center-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
@media (min-width: 1024px) {
    .command-center-grid {
        grid-template-columns: 1.7fr 1fr;
    }
}
</style>
@endsection

@section('content')
<div class="space-y-6">

    {{-- ═══ 1. Hero Command Bar ═══ --}}
    <div class="dashboard-hero mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="hero-pulse-dot"></span>
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color: #10b981;">System Operational &amp; Ready</span>
                    <span class="text-xs text-muted">·</span>
                    <span class="text-xs text-muted" id="live-time-display">{{ now()->locale('th')->isoFormat('D MMMM GGGG · HH:mm:ss') }}</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: var(--text-main, #0f172a);">
                    สวัสดี, {{ auth()->user()->full_name ?? auth()->user()->name }}
                </h1>
                <p class="text-sm text-muted mt-0.5">
                    ภาพรวมระบบและศูนย์ควบคุมกิจกรรมนักศึกษาแบบเรียลไทม์
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="document.getElementById('quickModal').classList.add('open')" class="btn btn-success btn-sm flex items-center gap-1.5 shadow-sm">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>บันทึกกิจกรรมด่วน</span>
                </button>
                <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm flex items-center gap-1.5 shadow-sm">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>สร้างกิจกรรมใหม่</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ═══ 2. System Health & Activity Overview Strip ═══ --}}
    <div class="health-strip mb-6">
        <div class="flex items-center gap-3" style="min-width: 220px;">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;color:#10b981;">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="text-xs text-muted">อัตราอนุมัติเดือนนี้</div>
                <div class="font-bold text-base" style="color: var(--text-main, #0f172a);">
                    {{ $approvalRate ?? 100 }}%
                    <span class="text-xs font-normal text-muted">ของการส่งคำขอ</span>
                </div>
            </div>
        </div>

        <div style="height: 32px; width: 1px; background: var(--border, #e2e8f0);" class="hidden sm:block"></div>

        <div class="flex-1" style="min-width: 280px;">
            @php
                $tot = max(1, $activityBreakdown['total'] ?? 1);
                $openPct = round((($activityBreakdown['open'] ?? 0) / $tot) * 100);
                $ongPct  = round((($activityBreakdown['ongoing'] ?? 0) / $tot) * 100);
                $clsPct  = max(0, 100 - $openPct - $ongPct);
            @endphp
            <div class="flex items-center justify-between text-xs mb-1.5">
                <span class="font-semibold" style="color: var(--text-main, #0f172a);">สัดส่วนสถานะกิจกรรม ({{ $activityBreakdown['total'] ?? 0 }} รายการ)</span>
                <span class="text-muted flex items-center gap-2">
                    <span style="color:#10b981;font-weight:600;">● เปิดรับ {{ $activityBreakdown['open'] ?? 0 }}</span>
                    <span style="color:#0ea5e9;font-weight:600;">● กำลังจัด {{ $activityBreakdown['ongoing'] ?? 0 }}</span>
                    <span style="color:#64748b;font-weight:600;">● เสร็จสิ้น/ปิด {{ $activityBreakdown['closed'] ?? 0 }}</span>
                </span>
            </div>
            <div class="breakdown-bar">
                <div style="width: {{ $openPct }}%; background: #10b981;" title="เปิดรับ {{ $openPct }}%"></div>
                <div style="width: {{ $ongPct }}%; background: #0ea5e9;" title="กำลังจัด {{ $ongPct }}%"></div>
                <div style="width: {{ $clsPct }}%; background: #94a3b8;" title="ปิด/เสร็จสิ้น {{ $clsPct }}%"></div>
            </div>
        </div>
    </div>

    {{-- ═══ 3. KPI Cards Grid (8 Cards) ═══ --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        {{-- Card 1: กิจกรรมทั้งหมด --}}
        <a href="{{ route('admin.activities.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">กิจกรรมทั้งหมด</span>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-main, #0f172a);">
                        {{ number_format($stats['totalActivities']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(249, 115, 22, 0.1); color: #ea580c;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                @php $actTrend = $trend['activitiesPct'] ?? 0; @endphp
                <span class="trend-badge {{ $actTrend > 0 ? 'trend-up' : ($actTrend < 0 ? 'trend-down' : 'trend-neutral') }}">
                    @if($actTrend > 0)
                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                        +{{ $actTrend }}%
                    @elseif($actTrend < 0)
                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                        {{ $actTrend }}%
                    @else
                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"/></svg>
                        0%
                    @endif
                </span>
                <span class="text-xs text-muted">เทียบเดือนก่อน</span>
            </div>
        </a>

        {{-- Card 2: เปิดรับสมัคร --}}
        <a href="{{ route('admin.activities.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">เปิดรับสมัคร</span>
                    <div class="text-2xl font-bold mt-1" style="color: #ea580c;">
                        {{ number_format($stats['upcomingActivities']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(234, 88, 12, 0.1); color: #c2410c;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-muted">พร้อมเข้าร่วม</span>
                <span class="text-xs font-semibold text-primary">ดูรายการ &rarr;</span>
            </div>
        </a>

        {{-- Card 3: นักศึกษาในระบบ --}}
        <a href="{{ route('admin.students.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">นักศึกษาในระบบ</span>
                    <div class="text-2xl font-bold mt-1" style="color: var(--text-main, #0f172a);">
                        {{ number_format($stats['totalStudents']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(14, 165, 233, 0.1); color: #0284c7;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-muted">ผู้ใช้งานทั้งหมด</span>
                <span class="text-xs font-semibold" style="color: #0284c7;">ทะเบียน &rarr;</span>
            </div>
        </a>

        {{-- Card 4: รออนุมัติ --}}
        <a href="#approval-queue-section" class="kpi-card" style="border-color: rgba(245, 158, 11, 0.4);">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">รออนุมัติทั้งหมด</span>
                    <div class="text-2xl font-bold mt-1" style="color: #d97706;" id="pending-badge-count">
                        {{ $stats['pendingRegistrations'] + $stats['pendingAttendances'] }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-muted">
                <span>สมัคร {{ $stats['pendingRegistrations'] }}</span>
                <span>·</span>
                <span>เช็คอิน {{ $stats['pendingAttendances'] }}</span>
            </div>
        </a>

        {{-- Card 5: ประกาศงาน --}}
        <a href="{{ route('admin.jobs.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">ประกาศงาน</span>
                    <div class="text-2xl font-bold mt-1" style="color: #059669;">
                        {{ number_format($stats['totalJobs']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(16, 185, 129, 0.1); color: #059669;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-muted">พาร์ทไทม์ &amp; ทั่วไป</span>
                <span class="text-xs font-semibold" style="color: #059669;">จัดการงาน &rarr;</span>
            </div>
        </a>

        {{-- Card 6: ข้อความใหม่ --}}
        <a href="{{ route('admin.inbox.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">ข้อความใหม่</span>
                    <div class="text-2xl font-bold mt-1" style="color: #e11d48;">
                        {{ number_format($stats['unreadMessages']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(244, 63, 94, 0.1); color: #e11d48;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-muted">กล่องข้อความ</span>
                <span class="text-xs font-semibold" style="color: #e11d48;">เปิดกล่องจดหมาย &rarr;</span>
            </div>
        </a>

        {{-- Card 7: ผลการประเมิน --}}
        <a href="{{ route('admin.feedbacks.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">แบบประเมิน</span>
                    <div class="text-2xl font-bold mt-1" style="color: #0284c7;">
                        {{ number_format($stats['totalFeedbacks']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(14, 165, 233, 0.1); color: #0284c7;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-muted">ความพึงพอใจ</span>
                <span class="text-xs font-semibold" style="color: #0284c7;">รายงานผล &rarr;</span>
            </div>
        </a>

        {{-- Card 8: กิจกรรมสัปดาห์นี้ --}}
        <a href="{{ route('admin.activities.index') }}" class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <span class="text-xs font-medium text-muted">สัปดาห์นี้</span>
                    <div class="text-2xl font-bold mt-1" style="color: #475569;">
                        {{ number_format($stats['upcomingThisWeek']) }}
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(100, 116, 139, 0.1); color: #475569;">
                    <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <span class="text-xs text-muted">กำหนดจัดสัปดาห์นี้</span>
                <span class="text-xs font-semibold" style="color: #475569;">ปฏิทิน &rarr;</span>
            </div>
        </a>
    </div>

    {{-- ═══ 4. Unified Approval Queue (Full Width Priority) ═══ --}}
    @php
        $allPending = collect();
        foreach($pendingRegistrations as $reg) {
            $allPending->push([
                'id'       => $reg->id,
                'type'     => 'registration',
                'name'     => $reg->user->full_name ?? '-',
                'sid'      => $reg->user->student_id ?? '',
                'faculty'  => $reg->user->faculty ?? '',
                'activity' => $reg->activity->title ?? '-',
                'time'     => $reg->created_at,
                'detail'   => 'ขอลงทะเบียนเข้าร่วมกิจกรรม',
            ]);
        }
        foreach($pendingAttendances as $att) {
            $allPending->push([
                'id'       => $att->id,
                'type'     => 'attendance',
                'name'     => $att->user->full_name ?? '-',
                'sid'      => $att->user->student_id ?? '',
                'faculty'  => $att->user->faculty ?? '',
                'activity' => $att->activity->title ?? '-',
                'time'     => $att->created_at,
                'detail'   => $att->distance_meters ? 'เช็คอิน GPS ห่าง '.number_format($att->distance_meters,0).' ม.' : 'บันทึกเช็คอิน',
            ]);
        }
        $allPending = $allPending->sortByDesc('time');
        $totalPending = $stats['pendingRegistrations'] + $stats['pendingAttendances'];
    @endphp

    <div id="approval-queue-section" class="mb-6">
        @if($totalPending > 0)
        <div class="card overflow-hidden" style="border: 1px solid rgba(245, 158, 11, 0.4); box-shadow: 0 4px 16px -4px rgba(245, 158, 11, 0.15);">
            <div class="card-header flex items-center justify-between" style="background: linear-gradient(135deg, rgba(254, 243, 199, 0.6) 0%, rgba(255, 251, 235, 0.3) 100%); border-bottom: 2px solid #fbbf24;">
                <div class="flex items-center gap-2.5">
                    <div style="width:32px;height:32px;border-radius:8px;background:#f59e0b;color:#fff;display:flex;align-items:center;justify-content:center;">
                        <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-base" style="color: #92400e; margin:0; line-height: 1.3;">
                            รายการรออนุมัติเร่งด่วน
                            <span id="queue-count" style="background:#b45309;color:#fff;border-radius:999px;padding:2px 10px;font-size:.78rem;margin-left:6px;font-weight:600;">{{ $totalPending }}</span>
                        </h3>
                        <p class="text-xs text-muted" style="margin:0;">อนุมัติการสมัครและเช็คอินเพื่อให้นักศึกษาได้รับชั่วโมงกิจกรรมทันที</p>
                    </div>
                </div>
                <a href="{{ route('admin.activities.index') }}" class="btn btn-outline btn-sm">ดูทั้งหมดในกิจกรรม</a>
            </div>

            <div id="approval-queue" style="max-height: 420px; overflow-y: auto;">
                @foreach($allPending as $item)
                <div class="approval-row" id="row-{{ $item['type'] }}-{{ $item['id'] }}"
                     style="display:flex;align-items:center;gap:.875rem;padding:.85rem 1.25rem;border-bottom:1px solid var(--border, #fef3c7);transition:all .25s ease;">
                    {{-- Type Badge --}}
                    <span style="flex-shrink:0;font-size:.72rem;font-weight:700;padding:4px 9px;border-radius:999px;
                        {{ $item['type'] === 'registration' ? 'background:#ffedd5;color:#c2410c;' : 'background:#dcfce7;color:#15803d;' }}">
                        {{ $item['type'] === 'registration' ? 'ลงทะเบียน' : 'เช็คอิน' }}
                    </span>

                    {{-- Student & Activity Info --}}
                    <div style="flex:1;min-width:0;">
                        <div class="font-semibold text-sm" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text-main, #0f172a);">
                            {{ $item['name'] }}
                            @if($item['sid']) <span class="text-xs text-muted font-normal"> · รหัส {{ $item['sid'] }}</span> @endif
                            @if($item['faculty']) <span class="text-xs text-muted font-normal"> · {{ $item['faculty'] }}</span> @endif
                        </div>
                        <div class="text-xs text-muted" style="margin-top:2px;">
                            <span class="font-medium" style="color:var(--dash-primary);font-weight:600;" title="{{ $item['activity'] }}">{{ Str::limit($item['activity'], 40, '...') }}</span>
                            &nbsp;·&nbsp; {{ $item['detail'] }} &nbsp;·&nbsp; {{ $item['time']->diffForHumans() }}
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1.5" style="flex-shrink:0;">
                        <button type="button" class="btn btn-success btn-sm flex items-center gap-1"
                            onclick="quickAction('approve','{{ $item['type'] }}',{{ $item['id'] }},this)"
                            title="อนุมัติ">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span>อนุมัติ</span>
                        </button>
                        <button type="button" class="btn btn-outline btn-sm flex items-center gap-1" style="color:#dc2626;border-color:rgba(239,68,68,0.35);"
                            onclick="quickAction('reject','{{ $item['type'] }}',{{ $item['id'] }},this)"
                            title="ปฏิเสธ">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>ปฏิเสธ</span>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @if($totalPending > 8)
            <div style="padding:.65rem 1.25rem;text-align:center;background:rgba(254, 243, 199, 0.4);border-top:1px solid rgba(245, 158, 11, 0.2);">
                <span class="text-xs text-muted">แสดง 8 รายการล่าสุด — <a href="{{ route('admin.activities.index') }}" style="color:#d97706;font-weight:600;">ดูทั้งหมด {{ $totalPending }} รายการในหน้ากิจกรรม</a></span>
            </div>
            @endif
        </div>
        @else
        <div class="card p-4 flex items-center justify-between" style="border-left: 4px solid #10b981; background: rgba(16, 185, 129, 0.05);">
            <div class="flex items-center gap-3">
                <div style="width:36px;height:36px;border-radius:10px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;">
                    <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm" style="color: #15803d; margin:0;">ไม่มีรายการรออนุมัติค้างอยู่</h4>
                    <p class="text-xs text-muted" style="margin:0;">คำขอลงทะเบียนและเช็คอินทั้งหมดได้รับการประมวลผลเรียบร้อยแล้ว</p>
                </div>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="btn btn-outline btn-sm">ดูกิจกรรมทั้งหมด</a>
        </div>
        @endif
    </div>

    {{-- ═══ 5. Two-Column Command Center Grid ═══ --}}
    <div class="command-center-grid">

        {{-- ── Left Column: กิจกรรมล่าสุด + Audit Log ── --}}
        <div class="space-y-6">

            {{-- กิจกรรมล่าสุด Card --}}
            <div class="card p-0 overflow-hidden">
                <div class="card-header flex items-center justify-between p-4 border-b">
                    <div class="flex items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(249,115,22,0.1);color:#ea580c;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-base" style="margin:0;">กิจกรรมล่าสุด</h3>
                            <p class="text-xs text-muted" style="margin:0;">รายการกิจกรรมที่สร้างและเปิดดำเนินการล่าสุด</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm flex items-center gap-1">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>สร้างใหม่</span>
                    </a>
                </div>

                <div class="table-wrap">
                    <table class="responsive-table">
                        <thead>
                            <tr>
                                <th>ชื่อกิจกรรม</th>
                                <th class="text-center">วันที่</th>
                                <th class="text-center">สถานะ</th>
                                <th class="text-center">ผู้เข้าร่วม</th>
                                <th class="text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivities as $act)
                            <tr>
                                <td data-label="ชื่อกิจกรรม" class="font-semi" title="{{ $act->title }}">
                                    <a href="{{ route('admin.activities.show', $act->id) }}" class="text-primary font-semi hover:underline">
                                        {{ Str::limit($act->title, 36, '...') }}
                                    </a>
                                    <div class="text-xs text-muted">{{ $act->category->name ?? 'ทั่วไป' }}</div>
                                </td>
                                <td data-label="วันที่" class="text-center text-xs text-muted">
                                    {{ $act->activity_date->format('d/m/Y') }}
                                </td>
                                <td data-label="สถานะ" class="text-center">
                                    @include('components.status-badge', ['status' => $act->computed_status])
                                </td>
                                <td data-label="ผู้เข้าร่วม" class="text-center">
                                    @php
                                        $regCount = $act->registrations()->where('status','approved')->count();
                                        $attCount = $act->attendances()->where('status','approved')->count();
                                    @endphp
                                    <span class="text-xs font-semibold">{{ $regCount }}/{{ $act->max_participants }}</span>
                                    @if($attCount > 0)
                                        <span class="text-xs" style="color:#16a34a;display:inline-flex;align-items:center;gap:1px;">
                                            (<svg style="width:10px;height:10px;display:inline;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>{{ $attCount }})
                                        </span>
                                    @endif
                                </td>
                                <td data-label="จัดการ" class="text-right">
                                    <div class="flex justify-end gap-1.5" style="justify-content:flex-end;">
                                        <a href="{{ route('admin.activities.show', $act->id) }}" class="btn btn-outline btn-sm">ดู</a>
                                        <a href="{{ route('admin.activities.edit', $act->id) }}" class="btn btn-outline btn-sm">แก้ไข</a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state-row py-8 text-center">
                                        <svg style="width:40px;height:40px;margin:0 auto;color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <div class="empty-state-row-title font-semibold mt-2">ยังไม่มีกิจกรรม</div>
                                        <div class="empty-state-row-desc text-xs text-muted">กดปุ่มสร้างใหม่เพื่อเพิ่มกิจกรรมแรก</div>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Audit Logs Card (Admin Only) --}}
            @if(auth()->user()->isAdmin())
            <div class="card p-0 overflow-hidden">
                <div class="card-header flex items-center justify-between p-4 border-b">
                    <div class="flex items-center gap-2">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(234,88,12,0.1);color:#ea580c;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-base" style="margin:0;">ประวัติการดำเนินงานล่าสุด</h3>
                            <p class="text-xs text-muted" style="margin:0;">Audit Logs ตรวจสอบกิจกรรมการแก้ไขระบบ</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.audit-logs.index') }}" class="dashboard-view-all-logs-btn">
                        <span>ดูประวัติทั้งหมด</span>
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </a>
                </div>

                <div class="divide-y" style="max-height: 380px; overflow-y: auto;">
                    @forelse($recentAuditLogs ?? [] as $log)
                    @php
                        $actionColor = match($log->action) {
                            'create', 'approve' => '#10b981',
                            'update', 'toggle'  => '#f59e0b',
                            'delete', 'reject'  => '#ef4444',
                            'login'             => '#0ea5e9',
                            default             => '#64748b',
                        };
                        $actionSvg = match($log->action) {
                            'create'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>',
                            'update'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
                            'delete'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>',
                            'approve' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
                            'reject'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
                            'login'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>',
                            default   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"/>',
                        };
                    @endphp
                    <a href="{{ route('admin.audit-logs.show', $log->id) }}" class="dashboard-audit-row">
                        <div style="width:34px;height:34px;border-radius:8px;background:{{ $actionColor }}15;color:{{ $actionColor }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $actionSvg !!}</svg>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold truncate" style="color:var(--text-main, #0f172a);">
                                    {{ $log->user->full_name ?? 'System' }}
                                </span>
                                <span class="text-xs text-muted" style="font-size:0.7rem;white-space:nowrap;">
                                    {{ $log->created_at->diffForHumans() }}
                                </span>
                            </div>
                            <p class="text-xs text-muted truncate mt-0.5" style="margin:0;">{{ $log->description }}</p>
                        </div>
                        <div style="flex-shrink:0;">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:{{ $actionColor }}20;color:{{ $actionColor }};font-size:0.7rem;">
                                {{ $log->action_label }}
                            </span>
                        </div>
                    </a>
                    @empty
                    <div class="p-6 text-center text-xs text-muted">
                        ไม่มีประวัติการดำเนินงานล่าสุด
                    </div>
                    @endforelse
                </div>
            </div>
            @endif

        </div>

        {{-- ── Right Column: Quick Action Panel + ประกาศงานล่าสุด + ประกาศล่าสุด ── --}}
        <div class="space-y-6">

            {{-- Quick Actions Shortcut Grid --}}
            <div class="card p-4">
                <div class="flex items-center gap-2 mb-3">
                    <svg style="width:18px;height:18px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <h3 class="font-bold text-sm" style="margin:0;">ทางลัดจัดการระบบ (Quick Actions)</h3>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <a href="{{ route('admin.activities.create') }}" class="quick-action-tile">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(249,115,22,0.1);color:#ea580c;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <span>สร้างกิจกรรม</span>
                    </a>
                    <a href="{{ route('admin.jobs.create') }}" class="quick-action-tile">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(16,185,129,0.1);color:#10b981;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
                        </div>
                        <span>ประกาศงาน</span>
                    </a>
                    <a href="{{ route('admin.announcements.create') }}" class="quick-action-tile">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(245,158,11,0.1);color:#f59e0b;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3-.204.904-.402 1.92-.402 3 0 1.08.198 2.096.402 3M2 9s1.5 2 2.5 2S7 9 7 9M2 9s1.5-2 2.5-2S7 9 7 9"/></svg>
                        </div>
                        <span>สร้างประกาศ</span>
                    </a>
                    <a href="{{ route('admin.students.index') }}" class="quick-action-tile">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(14,165,233,0.1);color:#0ea5e9;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                        <span>ค้นหานักศึกษา</span>
                    </a>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.audit-logs.index') }}" class="quick-action-tile">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(100,116,139,0.1);color:#64748b;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <span>Audit Logs</span>
                    </a>
                    <a href="{{ route('admin.settings.index') }}" class="quick-action-tile">
                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(100,116,139,0.1);color:#64748b;display:flex;align-items:center;justify-content:center;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <span>ตั้งค่าระบบ</span>
                    </a>
                    @endif
                </div>
            </div>

            {{-- ประกาศงานล่าสุด --}}
            <div class="card p-0 overflow-hidden">
                <div class="card-header flex items-center justify-between p-3.5 border-b">
                    <div class="flex items-center gap-2">
                        <svg style="width:18px;height:18px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
                        <h3 class="font-bold text-sm" style="margin:0;">ประกาศงานล่าสุด</h3>
                    </div>
                    <a href="{{ route('admin.jobs.index') }}" class="text-xs font-semibold text-primary hover:underline">ดูทั้งหมด</a>
                </div>
                <div class="divide-y">
                    @forelse($recentJobs as $job)
                    <div class="p-3 flex items-center justify-between gap-3 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        <div class="flex items-center gap-2.5 min-w-0">
                            @if($job->image_path)
                                <img src="{{ Storage::url($job->image_path) }}" alt="" style="width:34px;height:34px;border-radius:6px;object-fit:cover;flex-shrink:0;">
                            @else
                                <div style="width:34px;height:34px;border-radius:6px;background:rgba(16,185,129,0.1);color:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
                                </div>
                            @endif
                            <div class="min-w-0">
                                <a href="{{ route('admin.jobs.show', $job->id) }}" class="text-xs font-bold truncate block hover:underline" style="color:var(--text-main, #0f172a);">
                                    {{ Str::limit($job->title, 28) }}
                                </a>
                                <div class="text-xs text-muted flex items-center gap-1.5 mt-0.5">
                                    <span>{{ $job->position }}</span>
                                    <span>·</span>
                                    <span class="badge badge-orange" style="font-size:0.65rem;padding:1px 6px;">{{ $job->applications_count }} สมัคร</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @if($job->status === 'open')
                                <span class="badge badge-green" style="font-size:0.65rem;">เปิด</span>
                            @else
                                <span class="badge badge-gray" style="font-size:0.65rem;">ปิด</span>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="p-4 text-center text-xs text-muted">ยังไม่มีประกาศงาน</div>
                    @endforelse
                </div>
            </div>

            {{-- ประกาศข้อมูลล่าสุด --}}
            <div class="card p-0 overflow-hidden">
                <div class="card-header flex items-center justify-between p-3.5 border-b">
                    <div class="flex items-center gap-2">
                        <svg style="width:18px;height:18px;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3-.204.904-.402 1.92-.402 3 0 1.08.198 2.096.402 3M2 9s1.5 2 2.5 2S7 9 7 9M2 9s1.5-2 2.5-2S7 9 7 9"/></svg>
                        <h3 class="font-bold text-sm" style="margin:0;">ประกาศล่าสุด</h3>
                    </div>
                    <a href="{{ route('admin.announcements.index') }}" class="text-xs font-semibold text-primary hover:underline">ดูทั้งหมด</a>
                </div>
                <div class="divide-y">
                    @forelse($recentAnnouncements as $item)
                    <div class="p-3 flex items-center justify-between gap-3 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        <div class="min-w-0">
                            <div class="text-xs font-bold truncate" title="{{ $item->title }}" style="color:var(--text-main, #0f172a);">
                                {{ Str::limit($item->title, 30) }}
                            </div>
                            <div class="text-xs text-muted flex items-center gap-1.5 mt-0.5">
                                <span>{{ $item->target_faculty ?? 'ทุกคน' }}</span>
                                <span>·</span>
                                <span>{{ $item->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                        <div class="flex-shrink-0 flex items-center gap-1">
                            <span class="badge {{ $item->is_active ? 'badge-green' : 'badge-gray' }}" style="font-size:0.65rem;">
                                {{ $item->is_active ? 'เปิด' : 'ปิด' }}
                            </span>
                            <a href="{{ route('admin.announcements.edit', $item->id) }}" class="btn btn-outline btn-sm" style="padding:2px 6px;font-size:0.7rem;">แก้ไข</a>
                        </div>
                    </div>
                    @empty
                    <div class="p-4 text-center text-xs text-muted">ยังไม่มีประกาศ</div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

</div>

{{-- ═══ Modal สร้างกิจกรรมด่วน ═══ --}}
<div id="quickModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal" style="background: var(--surface, #ffffff); color: var(--text-main, #0f172a); border: 1px solid var(--border, #e2e8f0); max-width: 520px;">
        <div class="modal-header" style="background: var(--surface, #ffffff); border-bottom: 1px solid var(--border, #e2e8f0);">
            <h2 style="display:flex; align-items:center; gap:0.5rem; color: var(--text-main, #0f172a); font-size:1.1rem; font-weight:700;">
                <svg class="icon-sm" style="display:inline;color:#16a34a;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                บันทึกกิจกรรมด่วน
            </h2>
            <button class="modal-close" style="color: var(--text-muted, #64748b);" onclick="document.getElementById('quickModal').classList.remove('open')" aria-label="ปิด">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="modal-body" style="background: var(--surface, #ffffff); color: var(--text-main, #0f172a); padding: 1.25rem;">
            <form method="POST" action="{{ route('admin.activities.quick-store') }}">
                @csrf
                <div class="form-group mb-3">
                    <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="เช่น สัมมนา AI เบื้องต้น, อบรม Excel" required autofocus>
                </div>
                <div class="form-row mb-3" style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                    <div class="form-group">
                        <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">สถานที่ <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" placeholder="เช่น อาคาร 1 ห้อง 101" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">หมวดหมู่ <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-control" required>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row mb-3" style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                    <div class="form-group">
                        <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">วันที่จัดกิจกรรม <span class="text-danger">*</span></label>
                        <input type="date" name="activity_date" class="form-control" value="{{ now()->addDays(3)->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">ชั่วโมงกิจกรรม <span class="text-danger">*</span></label>
                        <input type="number" name="activity_hours" class="form-control" value="2" step="0.5" min="0.5" required>
                    </div>
                </div>
                <div class="form-row mb-3" style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                    <div class="form-group">
                        <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">เวลาเริ่ม <span class="text-danger">*</span></label>
                        <input type="time" name="start_time" class="form-control" value="09:00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color: var(--text-main, #0f172a); font-weight:600; font-size:0.85rem;">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                        <input type="time" name="end_time" class="form-control" value="12:00" required>
                    </div>
                </div>
                <p class="text-xs text-muted mb-4" style="color: var(--text-muted, #64748b);">* ค่าเริ่มต้นอัตโนมัติ: เปิดรับสมัครทันที, รับสมัครสูงสุด 50 คน</p>
                <div class="flex gap-2" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('quickModal').classList.remove('open')">ยกเลิก</button>
                    <button type="submit" class="btn btn-success flex items-center gap-1">
                        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>บันทึกกิจกรรม</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Toast Notification Box --}}
<div id="toast" style="display:none;position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
    background:#1e293b;color:#fff;padding:.75rem 1.25rem;border-radius:10px;
    font-size:.875rem;box-shadow:0 4px 24px rgba(0,0,0,.2);transition:opacity .3s;"></div>
@endsection

@section('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const APPROVE_URL = '{{ route("admin.quick.approve") }}';
const REJECT_URL  = '{{ route("admin.quick.reject") }}';

function showToast(msg, ok) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.background = ok ? '#15803d' : '#dc2626';
    t.style.display = 'block';
    t.style.opacity = '1';
    setTimeout(() => {
        t.style.opacity = '0';
        setTimeout(() => t.style.display = 'none', 300);
    }, 2800);
}

function updateBadges(count) {
    const el = document.getElementById('pending-badge-count');
    const qc = document.getElementById('queue-count');
    if (el) el.textContent = count;
    if (qc) qc.textContent = count;
}

async function quickAction(action, type, id, btn) {
    const row = document.getElementById(`row-${type}-${id}`);
    const url = action === 'approve' ? APPROVE_URL : REJECT_URL;
    btn.disabled = true;
    if (row) row.style.opacity = '0.5';

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ type, id })
        });
        const data = await res.json();
        if (data.ok) {
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.maxHeight = row.offsetHeight + 'px';
                row.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    row.style.maxHeight = '0';
                    row.style.padding = '0';
                    row.style.opacity = '0';
                });
                setTimeout(() => row.remove(), 320);
            }
            updateBadges(data.pending_count);
            showToast(data.message, true);
            if (data.pending_count === 0) {
                setTimeout(() => location.reload(), 600);
            }
        } else {
            showToast('เกิดข้อผิดพลาดในการประมวลผล', false);
            if (row) row.style.opacity = '1';
            btn.disabled = false;
        }
    } catch (e) {
        showToast('ไม่สามารถเชื่อมต่อเครือข่ายได้', false);
        if (row) row.style.opacity = '1';
        btn.disabled = false;
    }
}
</script>
@endsection
