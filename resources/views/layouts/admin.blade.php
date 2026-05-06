{{-- เลย์เอาต์หลักฝั่งผู้ดูแล (Admin): Collapsible Sidebar (Desktop) + Bottom Nav (Mobile) --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - ระบบกิจกรรม</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @vite(['resources/js/app.js'])
    @yield('styles')
<style>
*, *::before, *::after { box-sizing: border-box; }

/* ════════════════════════════
   SIDEBAR LAYOUT
════════════════════════════ */
.sb-shell { display: flex; min-height: 100vh; }
.sb-sidebar { width: 240px; background: #1e293b; display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 300; transition: width .2s cubic-bezier(.4,0,.2,1); overflow: hidden; }
.sb-sidebar.collapsed { width: 64px; }
.sidebar-brand { height: 60px; display: flex; align-items: center; padding: 0 16px; color: #fff; font-weight: 700; white-space: nowrap; overflow: hidden; }

/* Submenu Styles */
.sb-has-submenu { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; cursor: pointer; color: #94a3b8; font-size: 13.5px; transition: all .2s; }
.sb-has-submenu:hover { color: #e2e8f0; }
.sb-chevron { width: 14px; height: 14px; transition: transform .3s; }
.sb-has-submenu.open .sb-chevron { transform: rotate(90deg); }
.sb-submenu { overflow: hidden; max-height: 0; transition: max-height .3s ease-out; background: rgba(0,0,0,0.1); }
.sb-submenu-item { display: block; padding: 8px 14px 8px 40px; color: #94a3b8; font-size: 13px; text-decoration: none; }
.sb-submenu-item:hover, .sb-submenu-item.active { color: #a5b4fc; }

/* Nav */
.sb-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 8px; display: flex; flex-direction: column; gap: 2px; }
.sb-section-label { font-size: 10px; font-weight: 600; color: #475569; text-transform: uppercase; padding: 8px; margin-top: 10px; }
.sb-link { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 999px; color: #94a3b8; font-size: 13.5px; font-weight: 500; text-decoration: none; transition: all .2s; white-space: nowrap; }
.sb-link:hover { background: rgba(255,255,255,.07); color: #e2e8f0; }
.sb-link.active { background: rgba(99,102,241,.18); color: #a5b4fc; }
.sb-link svg { width: 18px; height: 18px; flex-shrink: 0; }
.sb-sidebar.collapsed .sb-link-text, .sb-sidebar.collapsed .sb-section-label, .sb-sidebar.collapsed .sb-has-submenu, .sb-sidebar.collapsed .sb-submenu { display: none; }

/* Mobile Sidebar Overlay */
.sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 250; display: none; }
.sidebar-overlay.open { display: block; }

/* ── Content ── */
.sb-content { flex: 1; margin-left: 240px; min-height: 100vh; transition: margin-left .2s cubic-bezier(.4,0,.2,1); background: #f8fafc; }
.sb-content.collapsed { margin-left: 64px; }
.sb-topbar { height: 60px; background: #fff; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; padding: 0 24px; gap: 12px; position: sticky; top: 0; z-index: 100; }
.sb-main { padding: 24px 28px; }

/* Mobile */
@media (max-width: 768px) {
    .sb-sidebar { left: -240px; transition: left 0.3s ease; }
    .sb-sidebar.mobile-open { left: 0; }
    .sb-content { margin-left: 0 !important; }
    .admin-mobile-header { display: flex; align-items: center; justify-content: space-between; background: #1e293b; color: #fff; padding: 0 1.25rem; height: 64px; }
    .sb-topbar { display: none; }
}
@media (min-width: 769px) { .admin-mobile-header { display: none !important; } }
</style>
</head>
<body>

{{-- 1. Mobile Header (Premium Style) --}}
<header class="admin-mobile-header">
    <div class="flex items-center gap-3">
        <button onclick="toggleMobileSidebar()" class="btn btn-outline" style="padding:.5rem;border-color:rgba(255,255,255,0.2);color:#fff;">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="{{ route('admin.dashboard') }}" class="admin-mobile-brand">UniActivity</a>
    </div>
</header>

<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileSidebar()"></div>

<div class="sb-shell">
    <aside class="sb-sidebar" id="mainSidebar">
        <div class="sidebar-brand">Uni-Activity Admin</div>
        
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
            <a href="{{ route('admin.announcements.index') }}" class="sb-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                <span class="sb-link-text">ประกาศ</span>
            </a>

            @if(auth()->user()->isAdmin())
            <div class="sb-section-label">จัดการระบบ</div>
            <div class="sb-has-submenu {{ request()->routeIs('admin.users.*', 'admin.categories.*', 'admin.audit-logs.*') ? 'active open' : '' }}" onclick="toggleSubmenu(this)">
                <div class="flex items-center gap-2">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="sb-link-text">การตั้งค่าระบบ</span>
                </div>
                <svg class="sb-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div class="sb-submenu {{ request()->routeIs('admin.users.*', 'admin.categories.*', 'admin.audit-logs.*') ? 'open' : '' }}" style="{{ request()->routeIs('admin.users.*', 'admin.categories.*', 'admin.audit-logs.*') ? 'max-height:500px;' : '' }}">
                <a href="{{ route('admin.users.index') }}" class="sb-submenu-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">ผู้ใช้ระบบ</a>
                <a href="{{ route('admin.categories.index') }}" class="sb-submenu-item {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">หมวดหมู่</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="sb-submenu-item {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">Audit Log</a>
            </div>
            @endif

            <div class="sb-section-label">รายงาน & ข้อมูล</div>
            <a href="{{ route('admin.students.index') }}" class="sb-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span class="sb-link-text">นักศึกษา</span>
            </a>
            <a href="{{ route('admin.exports.index') }}" class="sb-link {{ request()->routeIs('admin.exports.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="sb-link-text">ส่งออกรายงาน</span>
            </a>
        </nav>
        </nav>

        {{-- Sidebar Footer --}}
        <div class="sb-footer" style="padding: 10px 8px; border-top: 1px solid rgba(255,255,255,.07);">
            <div class="sb-user" style="display:flex; align-items:center; gap:8px; padding:8px; border-radius:8px; transition: background .15s;">
                <a href="{{ route('admin.profile.edit') }}" style="display:flex; align-items:center; gap:10px; flex:1; text-decoration:none;">
                    <div style="width:32px; height:32px; background:linear-gradient(135deg, #6366f1, #8b5cf6); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; font-weight:700;">
                        {{ strtoupper(substr(auth()->user()->full_name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="sb-link-text" style="flex:1; min-width:0;">
                        <div style="font-size:12px; font-weight:600; color:#e2e8f0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ auth()->user()->full_name ?? 'User' }}</div>
                        <div style="font-size:10px; color:#64748b;">{{ auth()->user()->isAdmin() ? 'Administrator' : 'Staff' }}</div>
                    </div>
                </a>
                <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" style="background:none; border:none; cursor:pointer; color:#64748b; padding:4px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:color .15s;">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="sb-content" id="sbContent">
        <div class="sb-topbar">
            <button onclick="toggleSidebar()">☰</button>
            <span>@yield('title', 'Dashboard')</span>
        </div>
        <div class="sb-main">
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
</body>
</html>
