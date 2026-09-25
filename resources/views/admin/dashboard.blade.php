{{-- Admin Dashboard: Cardless, Boxless, Ultra-Minimalist UX/UI --}}
@extends('layouts.admin')
@section('title', 'ภาพรวมระบบ')

@section('styles')
<style>
/* ══════════════════════════════════════════════════════════
   Cardless, Boxless, Ultra-Minimalist Design System
   ══════════════════════════════════════════════════════════ */
.minimal-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Hairline border rule using theme border variable */
.hairline-b {
    border-bottom: 1px solid var(--border, rgba(226, 232, 240, 0.8));
}
html[data-theme="dark"] .hairline-b,
html.dark .hairline-b {
    border-bottom-color: rgba(39, 39, 42, 0.9);
}

.hairline-t {
    border-top: 1px solid var(--border, rgba(226, 232, 240, 0.8));
}
html[data-theme="dark"] .hairline-t,
html.dark .hairline-t {
    border-top-color: rgba(39, 39, 42, 0.9);
}

/* Metric Display (No Box, No Card, Pure Typography) */
.metric-item {
    display: flex;
    flex-direction: column;
    text-decoration: none;
    color: inherit;
    padding: 0.5rem 0;
    transition: opacity 0.15s ease;
}
.metric-item:hover {
    opacity: 0.8;
}

.metric-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-muted, #64748b);
    letter-spacing: 0.025em;
    margin-bottom: 0.35rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.metric-value {
    font-size: 2.25rem;
    font-weight: 700;
    line-height: 1.1;
    color: var(--text-main, #0f172a);
    letter-spacing: -0.03em;
}

.metric-sub {
    font-size: 0.75rem;
    color: var(--text-muted, #64748b);
    margin-top: 0.35rem;
}

/* Minimalist Table */
.minimal-table {
    width: 100%;
    border-collapse: collapse;
}
.minimal-table th {
    text-align: left;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.75rem 0.5rem;
    border-bottom: 1px solid var(--border, #e2e8f0);
}
.minimal-table td {
    padding: 0.95rem 0.5rem;
    border-bottom: 1px solid var(--border, rgba(226, 232, 240, 0.6));
    font-size: 0.85rem;
    vertical-align: middle;
}
html[data-theme="dark"] .minimal-table th,
html.dark .minimal-table th {
    border-bottom-color: #27272a;
}
html[data-theme="dark"] .minimal-table td,
html.dark .minimal-table td {
    border-bottom-color: rgba(39, 39, 42, 0.6);
}
.minimal-table tr:hover td {
    background: rgba(0, 0, 0, 0.015);
}
html[data-theme="dark"] .minimal-table tr:hover td,
html.dark .minimal-table tr:hover td {
    background: rgba(255, 255, 255, 0.02);
}

/* Minimalist List Rows */
.minimal-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.85rem 0.25rem;
    border-bottom: 1px solid var(--border, rgba(226, 232, 240, 0.6));
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
}
html[data-theme="dark"] .minimal-row,
html.dark .minimal-row {
    border-bottom-color: rgba(39, 39, 42, 0.6);
}
.minimal-row:last-child {
    border-bottom: none;
}
.minimal-row:hover {
    background: rgba(0, 0, 0, 0.015);
}
html[data-theme="dark"] .minimal-row:hover,
html.dark .minimal-row:hover {
    background: rgba(255, 255, 255, 0.02);
}

/* Subtle Action Links */
.action-link {
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--text-muted, #64748b);
    text-decoration: none;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all 0.15s ease;
}
.action-link:hover {
    color: var(--dash-primary, #ea580c);
    background: rgba(234, 88, 12, 0.08);
}
</style>
@endsection

@section('content')
<div class="minimal-container space-y-10 py-1">

    {{-- ═══ 1. Minimal Header (Clean, Floating, Content-first) ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 hairline-b">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight" style="color: var(--text-main, #0f172a); margin: 0;">
                ภาพรวมระบบ
            </h1>
            <p class="text-xs sm:text-sm text-muted mt-1">
                สวัสดี, {{ auth()->user()->full_name ?? auth()->user()->name }} &nbsp;·&nbsp; {{ now()->locale('th')->isoFormat('D MMMM GGGG') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="document.getElementById('quickModal').classList.add('open')" class="btn btn-outline btn-sm flex items-center gap-1.5" style="border-radius: 8px;">
                <svg style="width:14px;height:14px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>บันทึกด่วน</span>
            </button>
            <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm flex items-center gap-1.5" style="border-radius: 8px;">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>สร้างกิจกรรม</span>
            </a>
        </div>
    </div>

    {{-- ═══ 2. Cardless & Boxless Key Metrics (Typography-First) ═══ --}}
    @php
        $totalPending = $stats['pendingRegistrations'] + $stats['pendingAttendances'];
    @endphp
    <div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8 pb-6 hairline-b">
            {{-- Metric 1: กิจกรรมทั้งหมด --}}
            <a href="{{ route('admin.activities.index') }}" class="metric-item">
                <div class="metric-label">
                    <svg style="width:14px;height:14px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    กิจกรรมทั้งหมด
                </div>
                <div class="metric-value">
                    {{ number_format($stats['totalActivities']) }}
                </div>
                <div class="metric-sub">
                    สัปดาห์นี้ <span class="font-semibold text-main">{{ $stats['upcomingThisWeek'] }}</span> กิจกรรม
                </div>
            </a>

            {{-- Metric 2: เปิดรับสมัคร --}}
            <a href="{{ route('admin.activities.index') }}" class="metric-item">
                <div class="metric-label">
                    <svg style="width:14px;height:14px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    เปิดรับสมัคร
                </div>
                <div class="metric-value" style="color: #ea580c;">
                    {{ number_format($stats['upcomingActivities']) }}
                </div>
                <div class="metric-sub">
                    พร้อมให้นักศึกษาเข้าร่วม
                </div>
            </a>

            {{-- Metric 3: นักศึกษา --}}
            <a href="{{ route('admin.students.index') }}" class="metric-item">
                <div class="metric-label">
                    <svg style="width:14px;height:14px;color:#0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    นักศึกษาในระบบ
                </div>
                <div class="metric-value">
                    {{ number_format($stats['totalStudents']) }}
                </div>
                <div class="metric-sub">
                    สมาชิกผู้ใช้งานทั้งหมด
                </div>
            </a>

            {{-- Metric 4: รออนุมัติ --}}
            <a href="{{ $totalPending > 0 ? '#approval-queue-section' : route('admin.activities.index') }}" class="metric-item">
                <div class="metric-label">
                    <svg style="width:14px;height:14px;color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    รออนุมัติทั้งหมด
                </div>
                <div class="metric-value" style="{{ $totalPending > 0 ? 'color: #d97706;' : '' }}" id="pending-badge-count">
                    {{ $totalPending }}
                </div>
                <div class="metric-sub">
                    สมัคร {{ $stats['pendingRegistrations'] }} · เช็คอิน {{ $stats['pendingAttendances'] }}
                </div>
            </a>
        </div>

        {{-- Secondary Metrics Line (Quiet, Non-intrusive) --}}
        <div class="flex flex-wrap items-center gap-y-2 gap-x-6 pt-3 text-xs text-muted">
            <a href="{{ route('admin.jobs.index') }}" class="hover:text-primary transition flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full" style="background:#10b981;"></span>
                <span>ประกาศงาน <strong>{{ number_format($stats['totalJobs']) }}</strong> งาน</span>
            </a>
            <span class="text-muted opacity-40">/</span>
            <a href="{{ route('admin.inbox.index') }}" class="hover:text-primary transition flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full" style="background:#f43f5e;"></span>
                <span>ข้อความใหม่ <strong>{{ number_format($stats['unreadMessages']) }}</strong> ฉบับ</span>
            </a>
            <span class="text-muted opacity-40">/</span>
            <a href="{{ route('admin.feedbacks.index') }}" class="hover:text-primary transition flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full" style="background:#0ea5e9;"></span>
                <span>ผลการประเมิน <strong>{{ number_format($stats['totalFeedbacks']) }}</strong> รายการ</span>
            </a>
        </div>
    </div>

    {{-- ═══ 3. Unified Approval Queue (Cardless — แสดงเฉพาะเมื่อมีรายการค้าง) ═══ --}}
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
                'detail'   => 'ขอลงทะเบียนเข้าร่วม',
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
    @endphp

    @if($totalPending > 0)
    <div id="approval-queue-section" class="space-y-3">
        <div class="flex items-center justify-between pb-2 hairline-b">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full" style="background: #f59e0b;"></span>
                <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-main, #0f172a); margin: 0;">
                    รายการรออนุมัติ
                </h2>
                <span id="queue-count" class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background: rgba(245, 158, 11, 0.15); color: #d97706;">{{ $totalPending }}</span>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="text-xs font-medium text-muted hover:text-primary transition">ดูทั้งหมดในกิจกรรม &rarr;</a>
        </div>

        <div id="approval-queue" class="divide-y" style="border-color: var(--border, #e2e8f0);">
            @foreach($allPending as $item)
            <div class="minimal-row" id="row-{{ $item['type'] }}-{{ $item['id'] }}">
                <div class="flex items-center gap-3 min-w-0">
                    <span style="font-size:0.7rem;font-weight:600;padding:2px 7px;border-radius:4px;
                        {{ $item['type'] === 'registration' ? 'background:rgba(234,88,12,0.1);color:#ea580c;' : 'background:rgba(16,185,129,0.1);color:#10b981;' }}">
                        {{ $item['type'] === 'registration' ? 'สมัคร' : 'เช็คอิน' }}
                    </span>
                    <div class="min-w-0">
                        <div class="font-semibold text-xs sm:text-sm truncate" style="color:var(--text-main, #0f172a);">
                            {{ $item['name'] }}
                            @if($item['sid']) <span class="text-muted font-normal text-xs">· {{ $item['sid'] }}</span> @endif
                            @if($item['faculty']) <span class="text-muted font-normal text-xs">· {{ $item['faculty'] }}</span> @endif
                        </div>
                        <div class="text-xs text-muted truncate mt-0.5">
                            <span class="font-medium" style="color:var(--text-main, #0f172a);">{{ Str::limit($item['activity'], 36, '...') }}</span>
                            &nbsp;·&nbsp; {{ $item['detail'] }} &nbsp;·&nbsp; {{ $item['time']->diffForHumans() }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button type="button" class="btn btn-success btn-sm py-1 px-2.5 text-xs flex items-center gap-1" style="border-radius:6px;"
                        onclick="quickAction('approve','{{ $item['type'] }}',{{ $item['id'] }},this)">
                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>อนุมัติ</span>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm py-1 px-2 text-xs flex items-center gap-1 text-danger" style="border-radius:6px;"
                        onclick="quickAction('reject','{{ $item['type'] }}',{{ $item['id'] }},this)">
                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>ปฏิเสธ</span>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ═══ 4. กิจกรรมล่าสุด (Cardless Minimal Table) ═══ --}}
    <div class="space-y-3">
        <div class="flex items-center justify-between pb-2 hairline-b">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-main, #0f172a); margin: 0;">
                    กิจกรรมล่าสุด
                </h2>
                <p class="text-xs text-muted mt-0.5">รายการกิจกรรมที่สร้างและเปิดรับสมัครล่าสุดในระบบ</p>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="text-xs font-medium text-muted hover:text-primary transition">
                ดูกิจกรรมทั้งหมด &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="minimal-table">
                <thead>
                    <tr>
                        <th>ชื่อกิจกรรม</th>
                        <th class="text-center" style="width: 110px;">วันที่</th>
                        <th class="text-center" style="width: 120px;">สถานะ</th>
                        <th class="text-center" style="width: 130px;">ผู้เข้าร่วม</th>
                        <th class="text-right" style="width: 100px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentActivities as $act)
                    <tr>
                        <td title="{{ $act->title }}">
                            <a href="{{ route('admin.activities.show', $act->id) }}" class="font-semibold text-main hover:text-primary transition">
                                {{ Str::limit($act->title, 42, '...') }}
                            </a>
                            <div class="text-xs text-muted mt-0.5">{{ $act->category->name ?? 'ทั่วไป' }}</div>
                        </td>
                        <td class="text-center text-xs text-muted">
                            {{ $act->activity_date->format('d/m/Y') }}
                        </td>
                        <td class="text-center">
                            @include('components.status-badge', ['status' => $act->computed_status])
                        </td>
                        <td class="text-center text-xs">
                            @php
                                $regCount = $act->registrations()->where('status','approved')->count();
                                $attCount = $act->attendances()->where('status','approved')->count();
                            @endphp
                            <span class="font-medium text-main">{{ $regCount }}/{{ $act->max_participants }}</span>
                            @if($attCount > 0)
                                <span class="text-success ml-1">({{ $attCount }})</span>
                            @endif
                        </td>
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.activities.show', $act->id) }}" class="action-link">ดู</a>
                                <a href="{{ route('admin.activities.edit', $act->id) }}" class="action-link">แก้ไข</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-xs text-muted">ยังไม่มีกิจกรรมในระบบ</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ 5. Split Minimal Feed: ประกาศงาน & ประกาศทั่วไป (ไร้ Card ไร้ Box) ═══ --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">

        {{-- ฝั่งซ้าย: ประกาศงานล่าสุด --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between pb-2 hairline-b">
                <div class="flex items-center gap-2">
                    <svg style="width:15px;height:15px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
                    <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-main, #0f172a); margin: 0;">
                        ประกาศงานล่าสุด
                    </h2>
                </div>
                <a href="{{ route('admin.jobs.index') }}" class="text-xs font-medium text-muted hover:text-primary transition">ดูทั้งหมด &rarr;</a>
            </div>

            <div>
                @forelse($recentJobs as $job)
                <div class="minimal-row">
                    <div class="min-w-0">
                        <a href="{{ route('admin.jobs.show', $job->id) }}" class="font-semibold text-xs sm:text-sm truncate block text-main hover:text-primary transition">
                            {{ Str::limit($job->title, 34) }}
                        </a>
                        <div class="text-xs text-muted flex items-center gap-2 mt-0.5">
                            <span>{{ $job->position }}</span>
                            <span>·</span>
                            <span>{{ $job->applications_count }} สมัคร</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-xs font-medium px-2 py-0.5 rounded" style="font-size:0.68rem;
                            {{ $job->status === 'open' ? 'background:rgba(16,185,129,0.12);color:#10b981;' : 'background:rgba(100,116,139,0.12);color:#64748b;' }}">
                            {{ $job->status === 'open' ? 'เปิด' : 'ปิด' }}
                        </span>
                        <a href="{{ route('admin.jobs.show', $job->id) }}" class="action-link">ดู</a>
                    </div>
                </div>
                @empty
                <div class="py-6 text-center text-xs text-muted">ยังไม่มีประกาศงาน</div>
                @endforelse
            </div>
        </div>

        {{-- ฝั่งขวา: ประกาศทั่วไปล่าสุด --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between pb-2 hairline-b">
                <div class="flex items-center gap-2">
                    <svg style="width:15px;height:15px;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3-.204.904-.402 1.92-.402 3 0 1.08.198 2.096.402 3M2 9s1.5 2 2.5 2S7 9 7 9M2 9s1.5-2 2.5-2S7 9 7 9"/></svg>
                    <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-main, #0f172a); margin: 0;">
                        ประกาศล่าสุด
                    </h2>
                </div>
                <a href="{{ route('admin.announcements.index') }}" class="text-xs font-medium text-muted hover:text-primary transition">ดูทั้งหมด &rarr;</a>
            </div>

            <div>
                @forelse($recentAnnouncements as $item)
                <div class="minimal-row">
                    <div class="min-w-0">
                        <div class="font-semibold text-xs sm:text-sm truncate text-main" title="{{ $item->title }}">
                            {{ Str::limit($item->title, 34) }}
                        </div>
                        <div class="text-xs text-muted flex items-center gap-2 mt-0.5">
                            <span>{{ $item->target_faculty ?? 'ทุกคน' }}</span>
                            <span>·</span>
                            <span>{{ $item->created_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="text-xs font-medium px-2 py-0.5 rounded" style="font-size:0.68rem;
                            {{ $item->is_active ? 'background:rgba(16,185,129,0.12);color:#10b981;' : 'background:rgba(100,116,139,0.12);color:#64748b;' }}">
                            {{ $item->is_active ? 'เปิด' : 'ปิด' }}
                        </span>
                        <a href="{{ route('admin.announcements.edit', $item->id) }}" class="action-link">แก้ไข</a>
                    </div>
                </div>
                @empty
                <div class="py-6 text-center text-xs text-muted">ยังไม่มีประกาศ</div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ═══ 6. ประวัติการดำเนินงานล่าสุด (Audit Logs — Cardless & Borderless) ═══ --}}
    @if(auth()->user()->isAdmin())
    <div class="space-y-3 pt-2">
        <div class="flex items-center justify-between pb-2 hairline-b">
            <div class="flex items-center gap-2">
                <svg style="width:15px;height:15px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--text-main, #0f172a); margin: 0;">
                    ประวัติการดำเนินงานล่าสุด (Audit Logs)
                </h2>
            </div>
            <a href="{{ route('admin.audit-logs.index') }}" class="text-xs font-medium text-muted hover:text-primary transition">
                ดูประวัติทั้งหมด &rarr;
            </a>
        </div>

        <div>
            @forelse($recentAuditLogs ?? [] as $log)
            <a href="{{ route('admin.audit-logs.show', $log->id) }}" class="minimal-row">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-main truncate">
                            {{ $log->user->full_name ?? 'System' }}
                        </span>
                        <span class="text-xs text-muted" style="font-size:0.7rem; white-space:nowrap;">
                            {{ $log->created_at->diffForHumans() }}
                        </span>
                    </div>
                    <p class="text-xs text-muted truncate mt-0.5" style="margin:0;">{{ $log->description }}</p>
                </div>
                <div class="flex-shrink-0">
                    <span class="text-xs font-medium px-2 py-0.5 rounded" style="background:rgba(100,116,139,0.1);color:#64748b;font-size:0.68rem;">
                        {{ $log->action_label }}
                    </span>
                </div>
            </a>
            @empty
            <div class="py-6 text-center text-xs text-muted">ไม่มีประวัติการดำเนินงานล่าสุด</div>
            @endforelse
        </div>
    </div>
    @endif

</div>

{{-- ═══ Modal สร้างกิจกรรมด่วน ═══ --}}
<div id="quickModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal" style="background: var(--surface, #ffffff); color: var(--text-main, #0f172a); border: 1px solid var(--border, #e2e8f0); max-width: 520px; border-radius: 14px;">
        <div class="modal-header" style="background: var(--surface, #ffffff); border-bottom: 1px solid var(--border, #e2e8f0);">
            <h2 style="display:flex; align-items:center; gap:0.5rem; color: var(--text-main, #0f172a); font-size:1.05rem; font-weight:700; margin:0;">
                <svg class="icon-sm" style="display:inline;color:#10b981;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
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
                row.style.transition = 'all 0.25s ease';
                row.style.maxHeight = row.offsetHeight + 'px';
                row.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    row.style.maxHeight = '0';
                    row.style.padding = '0';
                    row.style.opacity = '0';
                });
                setTimeout(() => row.remove(), 260);
            }
            updateBadges(data.pending_count);
            showToast(data.message, true);
            if (data.pending_count === 0) {
                setTimeout(() => location.reload(), 500);
            }
        } else {
            showToast('เกิดข้อผิดพลาดในการประมวลผล', false);
            if (row) row.style.opacity = '1';
            btn.disabled = false;
        }
    } catch (e) {
        showToast('ไม่สามารถเชื่อมต่อได้', false);
        if (row) row.style.opacity = '1';
        btn.disabled = false;
    }
}
</script>
@endsection
