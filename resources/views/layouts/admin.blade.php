<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - ระบบกิจกรรม</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    @vite(['resources/js/app.js'])
    <script>
        // ป้องกันปัญหา 419 Page Expired จาก BFCache (การกดปุ่ม Back)
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload();
            }
        });
    </script>
    @yield('styles')
<style>


*, *::before, *::after { box-sizing: border-box; }

/* ════════════════════════════
   SIDEBAR LAYOUT
   ════════════════════════════ */
.sb-shell { display: flex; min-height: 100vh; background: #f8fafc; }
.sb-sidebar { 
    width: 260px; 
    background: #111827; /* Darker, more modern navy */
    display: flex; 
    flex-direction: column; 
    position: fixed; 
    top: 0; left: 0; bottom: 0; 
    z-index: 300; 
    transition: all .3s cubic-bezier(.4,0,.2,1); 
    overflow: hidden;
    box-shadow: 4px 0 24px rgba(0,0,0,0.05);
}
.sb-sidebar.collapsed { width: 80px; }

.sidebar-brand { 
    height: 70px; 
    display: flex; 
    align-items: center; 
    padding: 0 24px; 
    color: #fff; 
    font-weight: 800; 
    font-size: 1.1rem;
    letter-spacing: -0.025em;
    white-space: nowrap; 
    overflow: hidden;
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

/* Custom Slim Scrollbar */
.sb-nav::-webkit-scrollbar { width: 5px; }
.sb-nav::-webkit-scrollbar-track { background: transparent; }
.sb-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
.sb-nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

.sb-nav { 
    flex: 1; 
    overflow-y: auto; 
    overflow-x: hidden; 
    padding: 20px 12px; 
    display: flex; 
    flex-direction: column; 
    gap: 4px; 
}

.sb-section-label { 
    font-size: 10px; 
    font-weight: 700; 
    color: #4b5563; 
    text-transform: uppercase; 
    letter-spacing: 0.1em;
    padding: 12px 12px 6px; 
}

.sb-link { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 12px 16px; 
    border-radius: 12px; 
    color: #9ca3af; 
    font-size: 14px; 
    font-weight: 500; 
    text-decoration: none; 
    transition: all .2s; 
    white-space: nowrap; 
}

.sb-link:hover { 
    background: rgba(234, 88, 12, 0.25); 
    color: #f97316; 
}

.sb-link.active, .sb-sidebar .sb-link.active, .sb-sidebar a.sb-link.active, .sb-sidebar a.active { 
    background: #ea580c !important;
    color: #ffffff !important; 
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.45) !important;
    border: none !important;
}

.sb-link.active svg, .sb-sidebar .sb-link.active svg, .sb-sidebar a.active svg {
    color: #ffffff !important;
    stroke: #ffffff !important;
}

.sb-link svg { 
    width: 20px; 
    height: 20px; 
    flex-shrink: 0; 
    transition: transform .2s;
}

.sb-link:hover svg { transform: translateX(2px); }

/* Fix Collapsed State UI */
.sb-sidebar.collapsed .sb-link {
    justify-content: center;
    padding: 12px 0;
}

.sb-sidebar.collapsed .sb-link svg {
    margin: 0;
}

.sb-sidebar.collapsed .sb-link-text, 
.sb-sidebar.collapsed .sb-section-label { 
    display: none !important; 
}

.sb-sidebar.collapsed .sidebar-brand {
    padding: 0;
    justify-content: center;
}

.sb-sidebar.collapsed .sidebar-brand {
    font-size: 0; 
}

/* ── Sidebar Footer ── */
.sb-footer { 
    padding: 16px 12px; 
    border-top: 1px solid rgba(255,255,255,0.05);
    background: rgba(0,0,0,0.1);
}
.sb-user { 
    display: flex; 
    align-items: center; 
    gap: 12px; 
    padding: 10px; 
    border-radius: 12px; 
    transition: all .2s;
    background: rgba(255,255,255,0.03);
    text-decoration: none;
}
.sb-user:hover { 
    background: rgba(255,255,255,0.06); 
}
.sb-avatar { 
    width: 36px; height: 36px; 
    background: linear-gradient(135deg, #f97316, #f87171); 
    border-radius: 10px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #fff; 
    font-size: 14px; 
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(249,115,22,0.3);
}
.sb-user-info { flex: 1; min-width: 0; }
.sb-user-name { 
    font-size: 13px; font-weight: 600; color: #f3f4f6; 
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; 
}
.sb-user-role { font-size: 11px; color: #6b7280; }

.sb-logout-btn { 
    background: none; border: none; cursor: pointer; color: #6b7280; 
    padding: 6px; display: flex; align-items: center; justify-content: center; 
    border-radius: 8px; transition: all .2s; 
}
.sb-logout-btn:hover { color: #f43f5e; background: rgba(244,63,94,0.1); }

.sb-sidebar.collapsed .sb-user-info, 
.sb-sidebar.collapsed .sb-logout-btn { 
    display: none; 
}
.sb-sidebar.collapsed .sb-user { justify-content: center; padding: 10px 0; }

/* Mobile Sidebar Overlay */
.sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 250; display: none; }
.sidebar-overlay.open { display: block; }

/* ── Content ── */
.sb-content { flex: 1; min-width: 0; margin-left: 260px; min-height: 100vh; transition: margin-left .3s ease; }
.sb-content.collapsed { margin-left: 80px; }
.sb-topbar { 
    height: 70px; 
    background: rgba(255,255,255,0.8); 
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #e5e7eb; 
    display: flex; 
    align-items: center; 
    padding: 0 24px; 
    gap: 16px; 
    position: sticky; top: 0; z-index: 100; 
}

.sb-toggle-btn {
    background: #f3f4f6;
    border: none;
    cursor: pointer;
    color: #4b5563;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    transition: all .2s;
}
.sb-toggle-btn:hover {
    background: #e5e7eb;
    color: #111827;
    transform: scale(1.05);
}

.sb-page-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #111827;
    letter-spacing: -0.025em;
}

.sb-main { padding: 32px; max-width: 1400px; margin: 0 auto; }

/* Tablet Auto-Collapse (769px - 1024px) */
@media (min-width: 769px) and (max-width: 1024px) {
    .sb-sidebar { width: 80px; }
    .sb-content { margin-left: 80px; }
    .sb-sidebar .sb-link { justify-content: center; padding: 12px 0; }
    .sb-sidebar .sb-link svg { margin: 0; }
    .sb-sidebar .sb-link-text, 
    .sb-sidebar .sb-section-label { display: none !important; }
    .sb-sidebar .sidebar-brand { padding: 0; justify-content: center; font-size: 0; gap: 0; }
    .sb-sidebar .sb-user-info, 
    .sb-sidebar .sb-logout-btn { display: none; }
    .sb-sidebar .sb-user { justify-content: center; padding: 10px 0; }
    .sb-main { padding: 24px; }
}

/* Mobile */
@media (max-width: 768px) {
    .sb-sidebar { left: -260px; transition: left 0.3s ease; }
    .sb-sidebar.mobile-open { left: 0; }
    .sb-content { margin-left: 0 !important; }
    .sb-main { padding: 20px; }
    .admin-mobile-header { display: flex; align-items: center; justify-content: space-between; background: #111827; color: #fff; padding: 0 1.25rem; height: 64px; }
    .sb-topbar { display: none; }
}
</style>

    @if(request('widget'))
    <style>
        html, body { background: #fff !important; overflow: hidden !important; height: 100vh !important; margin: 0 !important; padding: 0 !important; }
        .sb-sidebar, .sb-topbar, .admin-mobile-header, .admin-bottom-nav, .sb-footer, .chat-header-container { display: none !important; }
        .sb-content { margin-left: 0 !important; padding-top: 0 !important; height: 100vh !important; width: 100% !important; }
        .sb-main { padding: 0 !important; height: 100vh !important; max-width: 100% !important; display: flex !important; flex-direction: column !important; margin: 0 !important; }
        @media (prefers-color-scheme: dark) {
            html, body { background: #202124 !important; }
        }
    </style>
    @endif
</head>
<body>

{{-- 1. Mobile Header (Premium Style) --}}
<header class="admin-mobile-header">
    <div class="flex items-center gap-3">
        <button onclick="toggleMobileSidebar()" class="btn btn-outline" style="padding:.5rem;border-color:rgba(255,255,255,0.2);color:#fff;">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="{{ route('admin.dashboard') }}" class="admin-mobile-brand" style="display:flex; align-items:center; gap:8px;">
            <img src="{{ asset('logo.svg') }}" alt="Logo" style="height: 28px; width: 28px;">
            UniActivity
        </a>
    </div>
</header>

<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

<div class="sb-shell">
    <aside class="sb-sidebar" id="mainSidebar">
        <div class="sidebar-brand" style="display:flex; align-items:center; gap:10px;">
            <img src="{{ asset('logo.svg') }}" alt="Logo" style="height: 36px; width: 36px;">
            Uni-Activity
        </div>
        
        <nav class="sb-nav">
            <div class="sb-section-label">เมนูหลัก</div>
            <a href="{{ route('admin.dashboard') }}" class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="sb-link-text">Dashboard</span>
            </a>
            <a href="{{ route('admin.activities.index') }}" class="sb-link {{ request()->routeIs('admin.activities.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span class="sb-link-text">กิจกรรม</span>
            </a>
            <div class="sb-section-label">ประกาศ & ประชาสัมพันธ์</div>
            <a href="{{ route('admin.announcements.index') }}" class="sb-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                <span class="sb-link-text">ประกาศข่าวสาร</span>
            </a>
            <a href="{{ route('admin.jobs.index') }}" class="sb-link {{ request()->routeIs('admin.jobs.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span class="sb-link-text">งาน & พาร์ทไทม์</span>
            </a>
            @php
                $adminUnreadCount = 0;
                if (auth()->check() && auth()->user()->isStaffOrAdmin()) {
                    $adminId = auth()->id();
                    $isStaff = auth()->user()->isStaff();
                    $query = \Illuminate\Support\Facades\DB::table('messages')
                        ->join('rooms', 'messages.room_id', '=', 'rooms.id')
                        ->leftJoin('room_user', function($join) use ($adminId) {
                            $join->on('rooms.id', '=', 'room_user.room_id')
                                 ->where('room_user.user_id', '=', $adminId);
                        })
                        ->where('messages.user_id', '!=', $adminId)
                        ->where(function($q) {
                            $q->whereColumn('messages.created_at', '>', 'room_user.last_read_at')
                              ->orWhereNull('room_user.last_read_at');
                        });

                    if ($isStaff) {
                        $query->join('job_listings', 'rooms.job_id', '=', 'job_listings.id')
                              ->where('job_listings.created_by', '=', $adminId);
                    }

                    $adminUnreadCount = $query->count();
                }
            @endphp
            <a href="{{ route('admin.inbox.index') }}" class="sb-link {{ request()->routeIs('admin.inbox.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <span class="sb-link-text">
                    กล่องข้อความแชท
                    <span id="adminSidebarBadge" style="background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:999px;margin-left:4px;display:{{ $adminUnreadCount > 0 ? 'inline-block' : 'none' }};">{{ $adminUnreadCount }}</span>
                </span>
            </a>

            @if(auth()->check() && (auth()->user()->isAdmin() || auth()->user()->role === 'super-admin'))
            <div class="sb-section-label">จัดการระบบ (Admin)</div>
            <a href="{{ route('admin.audit-logs.index') }}" class="sb-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2-2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"/></svg>
                <span class="sb-link-text">Audit Logs (ประวัติ)</span>
            </a>
            @php $unreviewedSec = \App\Models\SecurityLog::where('is_reviewed', false)->count(); @endphp
            <a href="{{ route('admin.security-logs.index') }}" class="sb-link {{ request()->routeIs('admin.security-logs.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span class="sb-link-text">
                    Security Logs
                    @if($unreviewedSec > 0)
                        <span style="background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:999px;margin-left:4px;">{{ $unreviewedSec }}</span>
                    @endif
                </span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="sb-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span class="sb-link-text">จัดการผู้ใช้ระบบ</span>
            </a>
            <a href="{{ route('admin.categories.index') }}" class="sb-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span class="sb-link-text">จัดการหมวดหมู่</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="sb-link {{ request()->routeIs('admin.settings.*') && request()->get('tab') !== 'api-keys' ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="sb-link-text">ตั้งค่าระบบ</span>
            </a>
            <a href="{{ route('admin.settings.index', ['tab' => 'api-keys']) }}" class="sb-link {{ request()->routeIs('admin.settings.*') && request()->get('tab') === 'api-keys' ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span class="sb-link-text">API Keys & ความเป็นส่วนตัว</span>
            </a>
            <a href="{{ route('admin.system.cluster') }}" class="sb-link {{ request()->routeIs('admin.system.cluster*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span class="sb-link-text">Cluster Control Center</span>
            </a>
            @php $failedJobsCount = \Illuminate\Support\Facades\DB::table('failed_jobs')->count(); @endphp
            <a href="{{ route('admin.system.failed-jobs.index') }}" class="sb-link {{ request()->routeIs('admin.system.failed-jobs.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="sb-link-text">
                    Failed Queue Jobs
                    @if($failedJobsCount > 0)
                        <span style="background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;padding:1px 6px;border-radius:999px;margin-left:4px;">{{ $failedJobsCount }}</span>
                    @endif
                </span>
            </a>
            @endif

            <div class="sb-section-label">รายงาน & ผลการเรียน</div>
            <a href="{{ route('admin.feedbacks.index') }}" class="sb-link {{ request()->routeIs('admin.feedbacks.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                <span class="sb-link-text">ผลการประเมิน</span>
            </a>
            <a href="{{ route('admin.students.index') }}" class="sb-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span class="sb-link-text">ทะเบียนนักศึกษา</span>
            </a>
            <a href="{{ route('admin.exports.index') }}" class="sb-link {{ request()->routeIs('admin.exports.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="sb-link-text">ส่งออกข้อมูล</span>
            </a>
        </nav>

        {{-- Sidebar Footer --}}
        <div class="sb-footer">
            <div class="sb-user">
                <a href="{{ route('admin.profile.edit') }}" class="sb-user" style="padding:0; background:none; flex:1;">
                    @if(auth()->user()->profile_photo)
                        <img src="{{ asset('storage/' . auth()->user()->profile_photo) }}" alt="profile"
                            style="width: 36px; height: 36px; border-radius: 10px; object-fit: cover;">
                    @else
                        <div class="sb-avatar">
                            {{ strtoupper(substr(auth()->user()->full_name ?? 'A', 0, 1)) }}
                        </div>
                    @endif
                    <div class="sb-user-info sb-link-text">
                        <div class="sb-user-name">{{ auth()->user()->full_name ?? 'User' }}</div>
                        <div class="sb-user-role">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Staff' }}</div>
                    </div>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}" class="sb-logout-btn">
                    @csrf
                    <button type="submit" class="sb-logout-btn" style="padding:0;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="sb-content" id="sbContent">
        <div class="sb-topbar" style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:16px;">
                <button onclick="toggleSidebar()" class="sb-toggle-btn">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="sb-page-title">@yield('title', 'Dashboard')</span>
            </div>

            <!-- Global Search Trigger (Ctrl+K) -->
            <button onclick="openGlobalSearch()" style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:6px 14px; font-size:0.85rem; color:#64748b; cursor:pointer; transition:all .2s; min-width:240px; justify-content:space-between;" onmouseover="this.style.borderColor='#94a3b8'; this.style.background='#f1f5f9';" onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';">
                <span style="display:flex; align-items:center; gap:6px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span>ค้นหาข้อมูลระบบ...</span>
                </span>
                <kbd style="background:#e2e8f0; border:1px solid #cbd5e1; border-radius:4px; padding:1px 5px; font-size:0.75rem; font-family:monospace; color:#475569;">Ctrl K</kbd>
            </button>
        </div>
        <div class="sb-main">
            @if(session('success'))
                <div style="background-color: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #bbf7d0;">
                    {{ session('success') }}
                </div>
            @endif
            
            @if(session('error'))
                <div style="background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca;">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div style="background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid #fecaca;">
                    <ul style="margin: 0; padding-left: 1.5rem;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        sidebar.classList.toggle('collapsed');
        document.getElementById('sbContent').classList.toggle('collapsed');
    }

    function toggleSubmenu(el) {
        const submenu = el.nextElementSibling;
        el.classList.toggle('open');
        submenu.classList.toggle('open');
        submenu.style.maxHeight = submenu.classList.contains('open') ? submenu.scrollHeight + "px" : "0px";
    }

    function toggleMobileSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('open');
        document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
    }
</script>
@yield('scripts')
@stack('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ADMIN_UNREAD_URL = '{{ route("admin.inbox.unread-count") }}';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function refreshAdminBadge() {
        var url = ADMIN_UNREAD_URL + '?_t=' + new Date().getTime();
        fetch(url, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }, cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('adminSidebarBadge');
                if (!badge) return;
                if (data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }

    (function initAdminGlobalEcho() {
        if (!window.Echo) {
            setTimeout(initAdminGlobalEcho, 200);
            return;
        }

        window.Echo.private('admin.inbox')
            .listen('.MessageSent', function(e) {
                // Fetch real count from server (accurate, handles mark-read state)
                refreshAdminBadge();

                // If currently on inbox index page, refresh the thread list
                if (window.refreshInboxList) {
                    window.refreshInboxList();
                }
            });
    })();

    // Periodic backup poll (every 5s) to keep admin badge & inbox list 100% updated in real-time
    setInterval(function() {
        refreshAdminBadge();
        if (window.refreshInboxList) {
            window.refreshInboxList();
        }
    }, 5000);
});
</script>
<style>
/* Admin Chat Widget Styles */
#adminChatWidgetContainer {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9000;
    display: flex;
    align-items: flex-end;
    gap: 15px;
    pointer-events: none;
}

.admin-chat-widget {
    width: 340px;
    height: 480px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.18);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    pointer-events: auto;
    transition: transform 0.2s, height 0.2s;
    transform-origin: bottom center;
}

.admin-chat-widget.minimized {
    height: 50px;
}

.acw-header {
    background: #ea580c;
    color: #fff;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}
.acw-title {
    font-size: 0.9rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 5px;
}
.acw-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.acw-actions button {
    background: none;
    border: none;
    color: #fff;
    cursor: pointer;
    opacity: 0.8;
    margin: 0;
    padding: 0;
    line-height: 1;
}
.acw-actions button:hover { opacity: 1; }
.acw-iframe {
    flex: 1;
    border: none;
    width: 100%;
    background: #fff;
}
</style>

<div id="adminChatWidgetContainer"></div>

<script>
window.AdminChatManager = (function() {
    let openChats = {};

    function openChat(url, title, chatId) {
        if (openChats[chatId]) {
            openChats[chatId].classList.remove('minimized');
            return;
        }

        const container = document.getElementById('adminChatWidgetContainer');
        
        const widget = document.createElement('div');
        widget.className = 'admin-chat-widget';
        widget.id = 'acw-' + chatId;

        const header = document.createElement('div');
        header.className = 'acw-header';
        header.onclick = function(e) {
            if (e.target.tagName !== 'BUTTON') {
                widget.classList.toggle('minimized');
            }
        };

        const titleSpan = document.createElement('span');
        titleSpan.className = 'acw-title';
        titleSpan.innerHTML = '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> ' + title;

        const actions = document.createElement('div');
        actions.className = 'acw-actions';
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '✕';
        closeBtn.style.fontSize = '1.1rem';
        closeBtn.onclick = function() {
            widget.remove();
            delete openChats[chatId];
        };

        actions.appendChild(closeBtn);
        header.appendChild(titleSpan);
        header.appendChild(actions);

        // Convert url to relative to avoid cross-origin issues with proxies like Cloudflare Tunnels
        let relativeUrl = url;
        try {
            const urlObj = new URL(url, window.location.origin);
            relativeUrl = urlObj.pathname + urlObj.search;
        } catch(e) {}

        const iframe = document.createElement('iframe');
        iframe.className = 'acw-iframe';
        iframe.src = relativeUrl + (relativeUrl.includes('?') ? '&' : '?') + 'widget=1';

        widget.appendChild(header);
        widget.appendChild(iframe);

        container.appendChild(widget);
        openChats[chatId] = widget;
    }

    return { openChat: openChat };
})();
</script>

<!-- Auto-Linker & Hashtag Script for Admin Descriptions and Comments -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tokenPattern = /(?:(https?:\/\/[^\s<]+|(?<![\/\w])www\.[^\s<]+)|(?<![\w#&;:=])#(?!(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\b)([a-zA-Z0-9_\u0E00-\u0E7F]+))/gi;
    
    function linkifyTextNodes(element) {
        if (!element || element.classList.contains('no-linkify') || element.closest('a') || element.closest('.no-linkify')) return;
        
        var walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null, false);
        var textNodes = [];
        var node;
        while (node = walker.nextNode()) {
            if (node.nodeValue && tokenPattern.test(node.nodeValue)) {
                var parent = node.parentElement;
                if (parent && !parent.closest('a') && parent.tagName !== 'SCRIPT' && parent.tagName !== 'STYLE' && parent.tagName !== 'BUTTON' && parent.tagName !== 'TEXTAREA') {
                    textNodes.push(node);
                }
            }
            tokenPattern.lastIndex = 0;
        }
        
        textNodes.forEach(function(textNode) {
            if (!textNode.parentNode || textNode.parentNode.closest('a')) return;
            var val = textNode.nodeValue;
            var frag = document.createDocumentFragment();
            var lastIdx = 0;
            var match;
            tokenPattern.lastIndex = 0;
            
            while ((match = tokenPattern.exec(val)) !== null) {
                if (match.index > lastIdx) {
                    frag.appendChild(document.createTextNode(val.substring(lastIdx, match.index)));
                }
                
                var raw = match[0];
                if (match[2]) {
                    // Hashtag match
                    var tag = match[2];
                    var basePath = '/admin/activities';
                    if (window.location.pathname.startsWith('/admin/jobs')) {
                        basePath = '/admin/jobs';
                    } else if (window.location.pathname.startsWith('/admin/announcements')) {
                        basePath = '/admin/announcements';
                    }
                    var a = document.createElement('a');
                    a.href = basePath + '?search=' + encodeURIComponent('#' + tag);
                    a.className = 'hashtag-badge';
                    a.textContent = '#' + tag;
                    a.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                    frag.appendChild(a);
                } else if (match[1]) {
                    // URL match
                    var rawUrl = match[1];
                    var trailing = '';
                    while (rawUrl.length > 0 && /[.,;:!?)}\]"']/.test(rawUrl[rawUrl.length - 1])) {
                        trailing = rawUrl[rawUrl.length - 1] + trailing;
                        rawUrl = rawUrl.substring(0, rawUrl.length - 1);
                    }
                    
                    var a = document.createElement('a');
                    a.href = /^https?:\/\//i.test(rawUrl) ? rawUrl : 'https://' + rawUrl;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.className = 'linkified-url';
                    a.textContent = rawUrl;
                    a.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                    
                    frag.appendChild(a);
                    if (trailing) {
                        frag.appendChild(document.createTextNode(trailing));
                    }
                }
                lastIdx = match.index + match[0].length;
            }
            
            if (lastIdx < val.length) {
                frag.appendChild(document.createTextNode(val.substring(lastIdx)));
            }
            
            if (textNode.parentNode && !textNode.parentNode.closest('a')) {
                textNode.parentNode.replaceChild(frag, textNode);
            }
        });
    }
    
    document.querySelectorAll('.desc-content, .prose, .comment-body, .user-content').forEach(linkifyTextNodes);
});
</script>

<!-- Global Omnisearch Modal (Ctrl+K) -->
<div id="globalSearchModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(4px); z-index:10000; align-items:flex-start; justify-content:center; padding:10vh 1rem 2rem 1rem;">
    <div style="background:#fff; border-radius:16px; max-width:640px; width:100%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #cbd5e1; animation:fadeInDown .2s ease;">
        <div style="padding:1rem 1.25rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:12px;">
            <svg width="20" height="20" fill="none" stroke="#64748b" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" id="globalSearchInput" placeholder="พิมพ์ค้นหานักศึกษา, กิจกรรม, ตำแหน่งงาน, หรือประกาศ..." style="flex:1; border:none; outline:none; font-size:1rem; color:#0f172a; background:transparent;" autocomplete="off">
            <kbd onclick="closeGlobalSearch()" style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; padding:2px 8px; font-size:0.75rem; color:#64748b; cursor:pointer;">ESC</kbd>
        </div>
        <div id="globalSearchResults" style="max-height:420px; overflow-y:auto; padding:0.5rem;">
            <div style="padding:2rem 1rem; text-align:center; color:#94a3b8; font-size:0.875rem;">
                พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหาข้อมูลข้ามระบบ
            </div>
        </div>
        <div style="padding:0.75rem 1.25rem; background:#f8fafc; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; font-size:0.75rem; color:#64748b;">
            <span>กด <strong>&uarr;</strong> <strong>&darr;</strong> เพื่อเลื่อน, <strong>Enter</strong> เพื่อเปิด</span>
            <span>Uni-Activity Omnisearch</span>
        </div>
    </div>
</div>

<script>
    let searchDebounceTimer = null;

    function escapeSearchHtml(value) {
        const element = document.createElement('span');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function openGlobalSearch() {
        const modal = document.getElementById('globalSearchModal');
        modal.style.display = 'flex';
        const input = document.getElementById('globalSearchInput');
        input.value = '';
        input.focus();
        document.getElementById('globalSearchResults').innerHTML = '<div style="padding:2rem 1rem; text-align:center; color:#94a3b8; font-size:0.875rem;">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหาข้อมูลข้ามระบบ</div>';
    }

    function closeGlobalSearch() {
        const modal = document.getElementById('globalSearchModal');
        modal.style.display = 'none';
    }

    // Keyboard shortcut (Ctrl+K or Cmd+K) and Escape
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            const modal = document.getElementById('globalSearchModal');
            if (modal.style.display === 'flex') {
                closeGlobalSearch();
            } else {
                openGlobalSearch();
            }
        } else if (e.key === 'Escape') {
            closeGlobalSearch();
        }
    });

    document.getElementById('globalSearchModal').addEventListener('click', function(e) {
        if (e.target === this) closeGlobalSearch();
    });

    document.getElementById('globalSearchInput').addEventListener('input', function(e) {
        clearTimeout(searchDebounceTimer);
        const query = e.target.value.trim();
        const resultsContainer = document.getElementById('globalSearchResults');

        if (query.length < 2) {
            resultsContainer.innerHTML = '<div style="padding:2rem 1rem; text-align:center; color:#94a3b8; font-size:0.875rem;">พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อค้นหาข้อมูลข้ามระบบ</div>';
            return;
        }

        resultsContainer.innerHTML = '<div style="padding:2rem 1rem; text-align:center; color:#64748b; font-size:0.875rem;">กำลังค้นหา...</div>';

        searchDebounceTimer = setTimeout(async () => {
            try {
                const res = await fetch(`{{ route('admin.global.search') }}?q=${encodeURIComponent(query)}`);
                const data = await res.json();

                if (!data.results || data.results.length === 0) {
                    resultsContainer.innerHTML = `<div style="padding:2rem 1rem; text-align:center; color:#64748b; font-size:0.875rem;">ไม่พบข้อมูลที่ตรงกับ "${query}"</div>`;
                    return;
                }

                let html = '';
                data.results.forEach((item, index) => {
                    html += `
                        <a href="${item.url}" style="display:flex; align-items:center; justify-content:space-between; padding:0.75rem 1rem; border-radius:10px; text-decoration:none; color:inherit; margin-bottom:4px; transition:background .15s;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                            <div>
                                <div style="font-weight:600; font-size:0.9rem; color:#0f172a; display:flex; align-items:center; gap:8px;">
                                    <span>${escapeSearchHtml(item.title)}</span>
                                    <span style="font-size:0.7rem; font-weight:700; background:${item.badge_color}15; color:${item.badge_color}; padding:2px 8px; border-radius:6px;">${escapeSearchHtml(item.type_label)}</span>
                                </div>
                                <div style="font-size:0.8rem; color:#64748b; margin-top:2px;">${escapeSearchHtml(item.subtitle)}</div>
                            </div>
                            <svg width="16" height="16" fill="none" stroke="#94a3b8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    `;
                });
                resultsContainer.innerHTML = html;
            } catch (err) {
                resultsContainer.innerHTML = `<div style="padding:2rem 1rem; text-align:center; color:#ef4444; font-size:0.875rem;">เกิดข้อผิดพลาดในการค้นหา</div>`;
            }
        }, 200);
    });
</script>
</body>
</html>
