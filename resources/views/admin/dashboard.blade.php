{{-- Admin Dashboard: Clean, Orderly, and Professional --}}
@extends('layouts.admin')
@section('title', 'ภาพรวมระบบ')

@section('styles')
<style>
/* ══════════════════════════════════════════════════════════
   Clean, Orderly Dashboard Styles (Minimalist & Professional)
   ══════════════════════════════════════════════════════════ */

/* Stat Cards */
.dash-stat-grid-main {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.dash-stat-grid-sub {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.dash-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--border, #e2e8f0);
    border-radius: 14px;
    padding: 1.15rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    text-decoration: none;
    color: inherit;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.dash-card:hover {
    transform: translateY(-2px);
    border-color: rgba(234, 88, 12, 0.4);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}
html[data-theme="dark"] .dash-card:hover,
html.dark .dash-card:hover {
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
}

.dash-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Approval Queue */
.approval-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1.25rem;
    border-bottom: 1px solid var(--border, #f1f5f9);
    transition: background 0.15s;
}
.approval-item:last-child {
    border-bottom: none;
}
.approval-item:hover {
    background: rgba(249, 115, 22, 0.03);
}

/* Balanced 2-Column Grid */
.dash-split-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media (min-width: 1024px) {
    .dash-split-grid {
        grid-template-columns: 1fr 1fr;
    }
}

/* Audit Log Rows */
.audit-list-item {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border, #f1f5f9);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
}
.audit-list-item:last-child {
    border-bottom: none;
}
.audit-list-item:hover {
    background: rgba(0, 0, 0, 0.02);
}
html[data-theme="dark"] .audit-list-item:hover,
html.dark .audit-list-item:hover {
    background: rgba(255, 255, 255, 0.03);
}

/* Action button in tables */
.btn-action-view {
    padding: 4px 10px;
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid var(--border, #cbd5e1);
    background: transparent;
    color: var(--text-main, #334155);
    text-decoration: none;
    transition: all 0.15s;
}
.btn-action-view:hover {
    background: var(--dash-primary, #ea580c);
    border-color: var(--dash-primary, #ea580c);
    color: #ffffff;
}
</style>
@endsection

@section('content')
<div class="space-y-6">

    {{-- ═══ 1. Clean Header ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b" style="border-color: var(--border, #e2e8f0);">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight" style="color: var(--text-main, #0f172a); margin: 0;">
                ภาพรวมระบบ
            </h1>
            <p class="text-xs sm:text-sm text-muted mt-0.5">
                ยินดีต้อนรับ, {{ auth()->user()->full_name ?? auth()->user()->name }} &nbsp;·&nbsp; {{ now()->locale('th')->isoFormat('D MMMM GGGG') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="document.getElementById('quickModal').classList.add('open')" class="btn btn-outline btn-sm flex items-center gap-1.5">
                <svg style="width:15px;height:15px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>บันทึกกิจกรรมด่วน</span>
            </button>
            <a href="{{ route('admin.activities.create') }}" class="btn btn-primary btn-sm flex items-center gap-1.5">
                <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>สร้างกิจกรรม</span>
            </a>
        </div>
    </div>

    {{-- ═══ 2. Primary Key Metrics (4 Cards — Clean & Clear) ═══ --}}
    @php
        $totalPending = $stats['pendingRegistrations'] + $stats['pendingAttendances'];
    @endphp
    <div class="dash-stat-grid-main">
        {{-- Card 1: กิจกรรมทั้งหมด --}}
        <a href="{{ route('admin.activities.index') }}" class="dash-card">
            <div>
                <div class="text-xs font-medium text-muted">กิจกรรมทั้งหมด</div>
                <div class="text-2xl font-bold mt-1" style="color: var(--text-main, #0f172a);">
                    {{ number_format($stats['totalActivities']) }}
                </div>
                <div class="text-xs text-muted mt-1">
                    สัปดาห์นี้ <span class="font-semibold" style="color: var(--text-main, #0f172a);">{{ $stats['upcomingThisWeek'] }}</span> รายการ
                </div>
            </div>
            <div class="dash-icon-box" style="background: rgba(249, 115, 22, 0.1); color: #ea580c;">
                <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
        </a>

        {{-- Card 2: เปิดรับสมัคร --}}
        <a href="{{ route('admin.activities.index') }}" class="dash-card">
            <div>
                <div class="text-xs font-medium text-muted">เปิดรับสมัคร</div>
                <div class="text-2xl font-bold mt-1" style="color: #ea580c;">
                    {{ number_format($stats['upcomingActivities']) }}
                </div>
                <div class="text-xs text-muted mt-1">
                    พร้อมให้นักศึกษาเข้าร่วม
                </div>
            </div>
            <div class="dash-icon-box" style="background: rgba(234, 88, 12, 0.1); color: #c2410c;">
                <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        </a>

        {{-- Card 3: นักศึกษาในระบบ --}}
        <a href="{{ route('admin.students.index') }}" class="dash-card">
            <div>
                <div class="text-xs font-medium text-muted">นักศึกษาในระบบ</div>
                <div class="text-2xl font-bold mt-1" style="color: var(--text-main, #0f172a);">
                    {{ number_format($stats['totalStudents']) }}
                </div>
                <div class="text-xs text-muted mt-1">
                    ผู้ใช้งานนักศึกษาทั้งหมด
                </div>
            </div>
            <div class="dash-icon-box" style="background: rgba(14, 165, 233, 0.1); color: #0284c7;">
                <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </a>

        {{-- Card 4: รออนุมัติ --}}
        <a href="{{ $totalPending > 0 ? '#approval-queue-section' : route('admin.activities.index') }}" class="dash-card" style="{{ $totalPending > 0 ? 'border-color: rgba(245, 158, 11, 0.5);' : '' }}">
            <div>
                <div class="text-xs font-medium text-muted">รออนุมัติทั้งหมด</div>
                <div class="text-2xl font-bold mt-1" style="color: {{ $totalPending > 0 ? '#d97706' : 'var(--text-main, #0f172a)' }};" id="pending-badge-count">
                    {{ $totalPending }}
                </div>
                <div class="text-xs text-muted mt-1">
                    สมัคร {{ $stats['pendingRegistrations'] }} · เช็คอิน {{ $stats['pendingAttendances'] }}
                </div>
            </div>
            <div class="dash-icon-box" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">
                <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </a>
    </div>

    {{-- ═══ 3. Secondary Stats Strip (3 Compact Items) ═══ --}}
    <div class="dash-stat-grid-sub">
        <a href="{{ route('admin.jobs.index') }}" class="dash-card py-2.5">
            <div>
                <span class="text-xs text-muted">งานทั้งหมด:</span>
                <span class="font-bold text-sm ml-1" style="color: #059669;">{{ number_format($stats['totalJobs']) }} งาน</span>
            </div>
            <svg style="width:16px;height:16px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
        </a>

        <a href="{{ route('admin.inbox.index') }}" class="dash-card py-2.5">
            <div>
                <span class="text-xs text-muted">ข้อความใหม่:</span>
                <span class="font-bold text-sm ml-1" style="color: #e11d48;">{{ number_format($stats['unreadMessages']) }} ฉบับ</span>
            </div>
            <svg style="width:16px;height:16px;color:#f43f5e;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
        </a>

        <a href="{{ route('admin.feedbacks.index') }}" class="dash-card py-2.5">
            <div>
                <span class="text-xs text-muted">ผลการประเมิน:</span>
                <span class="font-bold text-sm ml-1" style="color: #0284c7;">{{ number_format($stats['totalFeedbacks']) }} รายการ</span>
            </div>
            <svg style="width:16px;height:16px;color:#0ea5e9;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        </a>
    </div>

    {{-- ═══ 4. Unified Approval Queue (แสดงเฉพาะเมื่อมีรายการค้าง) ═══ --}}
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
    <div id="approval-queue-section" class="card p-0 overflow-hidden mb-6" style="border-color: rgba(245, 158, 11, 0.4);">
        <div class="card-header flex items-center justify-between p-3.5 border-b" style="background: rgba(254, 243, 199, 0.35);">
            <div class="flex items-center gap-2">
                <svg style="width:18px;height:18px;color:#d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="font-bold text-sm" style="color: #92400e; margin: 0;">
                    รายการรออนุมัติ
                    <span id="queue-count" style="background:#b45309;color:#fff;border-radius:999px;padding:1px 8px;font-size:0.75rem;margin-left:4px;">{{ $totalPending }}</span>
                </h3>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="text-xs font-semibold hover:underline" style="color:#b45309;">ดูทั้งหมดในกิจกรรม</a>
        </div>
        <div id="approval-queue" style="max-height: 360px; overflow-y: auto;">
            @foreach($allPending as $item)
            <div class="approval-item" id="row-{{ $item['type'] }}-{{ $item['id'] }}">
                <span style="flex-shrink:0;font-size:0.7rem;font-weight:600;padding:2px 8px;border-radius:999px;
                    {{ $item['type'] === 'registration' ? 'background:#ffedd5;color:#c2410c;' : 'background:#dcfce7;color:#15803d;' }}">
                    {{ $item['type'] === 'registration' ? 'ลงทะเบียน' : 'เช็คอิน' }}
                </span>
                <div style="flex:1;min-width:0;">
                    <div class="font-semibold text-xs sm:text-sm truncate" style="color:var(--text-main, #0f172a);">
                        {{ $item['name'] }}
                        @if($item['sid']) <span class="text-muted font-normal"> · {{ $item['sid'] }}</span> @endif
                        @if($item['faculty']) <span class="text-muted font-normal"> · {{ $item['faculty'] }}</span> @endif
                    </div>
                    <div class="text-xs text-muted truncate mt-0.5">
                        <span class="font-medium" style="color:var(--dash-primary); font-weight:600;">{{ Str::limit($item['activity'], 36, '...') }}</span>
                        &nbsp;·&nbsp; {{ $item['detail'] }} &nbsp;·&nbsp; {{ $item['time']->diffForHumans() }}
                    </div>
                </div>
                <div class="flex items-center gap-1.5" style="flex-shrink:0;">
                    <button type="button" class="btn btn-success btn-sm py-1 px-2.5 text-xs flex items-center gap-1"
                        onclick="quickAction('approve','{{ $item['type'] }}',{{ $item['id'] }},this)">
                        <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>อนุมัติ</span>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm py-1 px-2.5 text-xs flex items-center gap-1 text-danger"
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

    {{-- ═══ 5. กิจกรรมล่าสุด (Full-width clean table) ═══ --}}
    <div class="card p-0 overflow-hidden mb-6">
        <div class="card-header flex items-center justify-between p-4 border-b">
            <div>
                <h3 class="font-bold text-base" style="margin:0;">กิจกรรมล่าสุด</h3>
                <p class="text-xs text-muted" style="margin:0;">รายการกิจกรรมที่สร้างและเปิดรับสมัครล่าสุดในระบบ</p>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="text-xs font-semibold text-primary hover:underline">
                ดูกิจกรรมทั้งหมด &rarr;
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
                                {{ Str::limit($act->title, 45, '...') }}
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
                                <span class="text-xs text-success ml-1">({{ $attCount }} เช็คอิน)</span>
                            @endif
                        </td>
                        <td data-label="จัดการ" class="text-right">
                            <div class="flex justify-end gap-1.5" style="justify-content:flex-end;">
                                <a href="{{ route('admin.activities.show', $act->id) }}" class="btn-action-view">ดู</a>
                                <a href="{{ route('admin.activities.edit', $act->id) }}" class="btn-action-view">แก้ไข</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-xs text-muted">ยังไม่มีกิจกรรมในระบบ</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ 6. Balanced 2-Column: ประกาศงาน + ประกาศล่าสุด ═══ --}}
    <div class="dash-split-grid">

        {{-- ฝั่งซ้าย: ประกาศงานล่าสุด --}}
        <div class="card p-0 overflow-hidden">
            <div class="card-header flex items-center justify-between p-3.5 border-b">
                <div class="flex items-center gap-2">
                    <svg style="width:16px;height:16px;color:#10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-8.995-1.745M16 6l4-4m0 0l-4-4m4 4H9a2 2 0 00-2 2v12a2 2 0 002 2h9a2 2 0 002-2V8a2 2 0 00-2-2z"/></svg>
                    <h3 class="font-bold text-sm" style="margin:0;">ประกาศงานล่าสุด</h3>
                </div>
                <a href="{{ route('admin.jobs.index') }}" class="text-xs font-semibold text-primary hover:underline">ดูทั้งหมด</a>
            </div>
            <div class="divide-y">
                @forelse($recentJobs as $job)
                <div class="p-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('admin.jobs.show', $job->id) }}" class="text-xs font-bold truncate block hover:underline" style="color:var(--text-main, #0f172a);">
                            {{ Str::limit($job->title, 34) }}
                        </a>
                        <div class="text-xs text-muted flex items-center gap-2 mt-0.5">
                            <span>{{ $job->position }}</span>
                            <span>·</span>
                            <span class="badge badge-orange" style="font-size:0.65rem;padding:1px 6px;">{{ $job->applications_count }} สมัคร</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="badge {{ $job->status === 'open' ? 'badge-green' : 'badge-gray' }}" style="font-size:0.65rem;">
                            {{ $job->status === 'open' ? 'เปิด' : 'ปิด' }}
                        </span>
                        <a href="{{ route('admin.jobs.show', $job->id) }}" class="btn-action-view">ดู</a>
                    </div>
                </div>
                @empty
                <div class="p-4 text-center text-xs text-muted">ยังไม่มีประกาศงาน</div>
                @endforelse
            </div>
        </div>

        {{-- ฝั่งขวา: ประกาศล่าสุด --}}
        <div class="card p-0 overflow-hidden">
            <div class="card-header flex items-center justify-between p-3.5 border-b">
                <div class="flex items-center gap-2">
                    <svg style="width:16px;height:16px;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3-.204.904-.402 1.92-.402 3 0 1.08.198 2.096.402 3M2 9s1.5 2 2.5 2S7 9 7 9M2 9s1.5-2 2.5-2S7 9 7 9"/></svg>
                    <h3 class="font-bold text-sm" style="margin:0;">ประกาศล่าสุด</h3>
                </div>
                <a href="{{ route('admin.announcements.index') }}" class="text-xs font-semibold text-primary hover:underline">ดูทั้งหมด</a>
            </div>
            <div class="divide-y">
                @forelse($recentAnnouncements as $item)
                <div class="p-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-xs font-bold truncate" title="{{ $item->title }}" style="color:var(--text-main, #0f172a);">
                            {{ Str::limit($item->title, 34) }}
                        </div>
                        <div class="text-xs text-muted flex items-center gap-2 mt-0.5">
                            <span>{{ $item->target_faculty ?? 'ทุกคน' }}</span>
                            <span>·</span>
                            <span>{{ $item->created_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="badge {{ $item->is_active ? 'badge-green' : 'badge-gray' }}" style="font-size:0.65rem;">
                            {{ $item->is_active ? 'เปิด' : 'ปิด' }}
                        </span>
                        <a href="{{ route('admin.announcements.edit', $item->id) }}" class="btn-action-view">แก้ไข</a>
                    </div>
                </div>
                @empty
                <div class="p-4 text-center text-xs text-muted">ยังไม่มีประกาศ</div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ═══ 7. ประวัติการดำเนินงานล่าสุด (Audit Logs — Admin Only) ═══ --}}
    @if(auth()->user()->isAdmin())
    <div class="card p-0 overflow-hidden mb-6">
        <div class="card-header flex items-center justify-between p-3.5 border-b">
            <div class="flex items-center gap-2">
                <svg style="width:16px;height:16px;color:#ea580c;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <h3 class="font-bold text-sm" style="margin:0;">ประวัติการดำเนินงานล่าสุด (Audit Logs)</h3>
            </div>
            <a href="{{ route('admin.audit-logs.index') }}" class="text-xs font-semibold text-primary hover:underline">
                ดูประวัติทั้งหมด &rarr;
            </a>
        </div>
        <div class="divide-y">
            @forelse($recentAuditLogs ?? [] as $log)
            <a href="{{ route('admin.audit-logs.show', $log->id) }}" class="audit-list-item">
                <div style="flex:1;min-width:0;">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold truncate" style="color:var(--text-main, #0f172a);">
                            {{ $log->user->full_name ?? 'System' }}
                        </span>
                        <span class="text-xs text-muted" style="font-size:0.7rem;white-space:nowrap;">
                            {{ $log->created_at->diffForHumans() }}
                        </span>
                    </div>
                    <p class="text-xs text-muted truncate mt-0.5" style="margin:0;">{{ $log->description }}</p>
                </div>
                <div style="flex-shrink:0;">
                    <span class="badge badge-gray" style="font-size:0.7rem;">
                        {{ $log->action_label }}
                    </span>
                </div>
            </a>
            @empty
            <div class="p-4 text-center text-xs text-muted">ไม่มีประวัติการดำเนินงานล่าสุด</div>
            @endforelse
        </div>
    </div>
    @endif

</div>

{{-- ═══ Modal สร้างกิจกรรมด่วน ═══ --}}
<div id="quickModal" class="modal-overlay" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal" style="background: var(--surface, #ffffff); color: var(--text-main, #0f172a); border: 1px solid var(--border, #e2e8f0); max-width: 520px;">
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
