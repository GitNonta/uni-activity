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
    background: rgba(255,255,255,0.05); 
    color: #f3f4f6; 
}

.sb-link.active { 
    background: linear-gradient(135deg, rgba(99,102,241,0.15) 0%, rgba(139,92,246,0.15) 100%);
    color: #a5b4fc; 
    box-shadow: inset 0 0 0 1px rgba(99,102,241,0.2);
}

.sb-link svg { 
    width: 20px; 
    height: 20px; 
    flex-shrink: 0; 
    transition: transform .2s;
}

.sb-link:hover svg { transform: translateX(2px); }

.sb-sidebar.collapsed .sb-link-text, 
.sb-sidebar.collapsed .sb-section-label { 
    opacity: 0; visibility: hidden; 
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
    background: linear-gradient(135deg, #6366f1, #8b5cf6); 
    border-radius: 10px; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    color: #fff; 
    font-size: 14px; 
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
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
.sb-content { flex: 1; margin-left: 260px; min-height: 100vh; transition: margin-left .3s ease; }
.sb-content.collapsed { margin-left: 80px; }
.sb-topbar { 
    height: 70px; 
    background: rgba(255,255,255,0.8); 
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #e5e7eb; 
    display: flex; 
    align-items: center; 
    padding: 0 32px; 
    gap: 16px; 
    position: sticky; top: 0; z-index: 100; 
}
.sb-main { padding: 32px; max-width: 1400px; margin: 0 auto; }

/* Mobile */
@media (max-width: 768px) {
    .sb-sidebar { left: -260px; transition: left 0.3s ease; }
    .sb-sidebar.mobile-open { left: 0; }
    .sb-content { margin-left: 0 !important; }
    .admin-mobile-header { display: flex; align-items: center; justify-content: space-between; background: #111827; color: #fff; padding: 0 1.25rem; height: 64px; }
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
            <div class="sb-section-label">ประกาศ & ประชาสัมพันธ์</div>
            <a href="{{ route('admin.announcements.index') }}" class="sb-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                <span class="sb-link-text">ประกาศข่าวสาร</span>
            </a>
            <a href="{{ route('admin.jobs.index') }}" class="sb-link {{ request()->routeIs('admin.jobs.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span class="sb-link-text">งาน & พาร์ทไทม์</span>
            </a>
            <a href="{{ route('admin.inbox.index') }}" class="sb-link {{ request()->routeIs('admin.inbox.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <span class="sb-link-text">กล่องข้อความแชท</span>
            </a>

            @if(auth()->user()->isAdmin())
            <div class="sb-section-label">จัดการระบบ (Admin)</div>
            <a href="{{ route('admin.audit-logs.index') }}" class="sb-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2-2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01m-.01 4h.01"/></svg>
                <span class="sb-link-text">Audit Logs (ประวัติ)</span>
            </a>
            <a href="{{ route('admin.users.index') }}" class="sb-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <span class="sb-link-text">จัดการผู้ใช้ระบบ</span>
            </a>
            <a href="{{ route('admin.categories.index') }}" class="sb-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                <span class="sb-link-text">จัดการหมวดหมู่</span>
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
                    <div class="sb-avatar">
                        {{ strtoupper(substr(auth()->user()->full_name ?? 'A', 0, 1)) }}
                    </div>
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
