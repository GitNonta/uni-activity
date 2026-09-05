{{-- เลย์เอาต์หลักฝั่งนักศึกษา: navbar + bottom nav (mobile) + เนื้อหา --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2563eb">
    {{-- SEO: Title --}}
    <title>@yield('title', 'UNI Activity') | UNI Activity</title>
    {{-- SEO: Description --}}
    <meta name="description" content="@yield('description', 'ระบบศูนย์รวมกิจกรรมมหาวิทยาลัย - ค้นหา ลงทะเบียน และติดตามกิจกรรมของคุณ')">
    <meta name="author" content="@yield('author', 'UNI Activity')">
    @yield('head')
    {{-- Canonical URL --}}
    @if(request()->isMethod('GET'))
    <link rel="canonical" href="{{ request()->url() }}">
    @endif
    {{-- Open Graph / Facebook --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:title" content="@yield('title', 'UNI Activity') | UNI Activity">
    <meta property="og:description" content="@yield('description', 'ระบบศูนย์รวมกิจกรรมมหาวิทยาลัย - ค้นหา ลงทะเบียน และติดตามกิจกรรมของคุณ')">
    <meta property="og:image" content="{{ asset('logo.svg') }}">
    <meta property="og:site_name" content="UNI Activity">
    <meta property="og:locale" content="th_TH">
    {{-- Twitter --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', 'UNI Activity') | UNI Activity">
    <meta name="twitter:description" content="@yield('description', 'ระบบศูนย์รวมกิจกรรมมหาวิทยาลัย - ค้นหา ลงทะเบียน และติดตามกิจกรรมของคุณ')">
    <meta name="twitter:image" content="{{ asset('logo.svg') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    {{-- Preconnect to Google Fonts (resolve DNS early) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Font loaded async with media=print trick --}}
    <link href="https://fonts.googleapis.com/css2?display=swap&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?display=swap&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
    {{-- Critical CSS: preload for fast paint --}}
    <link rel="preload" href="{{ asset('css/app.css') }}?v={{ md5_file(public_path('css/app.css')) }}" as="style">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ md5_file(public_path('css/app.css')) }}">
    @vite(['resources/js/app.js'])
</head>
<body>
    {{-- Top Navbar --}}
    <header class="navbar">
        <div class="navbar-inner">
            <div class="navbar-left">
                <a href="{{ route('activities.index') }}" class="navbar-brand" style="display:flex; align-items:center; gap:8px;">
                    <img src="{{ asset('logo.svg') }}" alt="Logo" style="height: 32px; width: 32px;">
                    UNI Activity
                </a>
            </div>
            @auth
            {{-- Desktop nav links (ซ่อนบนมือถือ) --}}
            <nav class="navbar-center navbar-desktop">
                <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    กิจกรรม
                </a>
                <a href="{{ route('map.index') }}" class="{{ request()->routeIs('map.*') ? 'active' : '' }}" title="แผนที่กิจกรรมและงาน">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    แผนที่
                </a>
                <a href="{{ route('student.calendar') }}" class="{{ request()->routeIs('student.calendar') ? 'active' : '' }}" title="ปฏิทิน">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/><rect x="3" y="9" width="18" height="12" rx="2" stroke-width="0" fill="currentColor" opacity=".12"/></svg>
                    ปฏิทิน
                </a>
                <a href="{{ route('announcements.index') }}" class="{{ request()->routeIs('announcements.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    ประกาศ
                </a>
                <a href="{{ route('student.my') }}" class="{{ request()->routeIs('student.my') ? 'active' : '' }}" style="position:relative;">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    ของฉัน
                    <span id="nav-todo-badge" style="display:none;position:absolute;top:-4px;right:-6px;min-width:16px;height:16px;border-radius:8px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:16px;text-align:center;padding:0 3px;"></span>
                </a>
                <a href="{{ route('student.history') }}" class="{{ request()->routeIs('student.history') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    ประวัติ
                </a>
                <a href="{{ route('student.summary') }}" class="{{ request()->routeIs('student.summary') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    สรุป
                </a>
                <a href="{{ route('student.profile') }}" class="{{ request()->routeIs('student.profile') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    โปรไฟล์
                </a>
            </nav>
            <div class="navbar-right navbar-desktop">
                <span class="navbar-user">
                    <svg class="icon-sm" style="display:inline;margin-right:.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ auth()->user()->full_name }}
                </span>

                <!-- SVG Theme Toggle Button (Desktop Auth) -->
                <button type="button" data-theme-toggle class="navbar-icon-btn navbar-theme-toggle-btn" title="สลับโหมดมืด / สว่าง (Dark / Light Mode)">
                    <svg class="theme-icon-sun" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                    <svg class="theme-icon-moon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>
                    </svg>
                </button>

                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="navbar-logout-btn" title="ออกจากระบบ">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>ออก</span>
                    </button>
                </form>
            </div>
            {{-- Mobile: แสดงชื่อ + ปุ่มไอคอน + ปุ่มออก --}}
            <div class="navbar-mobile-right">
                <!-- SVG Theme Toggle Button (Mobile Auth) -->
                <button type="button" data-theme-toggle class="navbar-icon-btn navbar-theme-toggle-btn" title="สลับโหมดมืด / สว่าง">
                    <svg class="theme-icon-sun" width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                    <svg class="theme-icon-moon" width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>
                    </svg>
                </button>
                <a href="{{ route('map.index') }}" class="navbar-icon-btn {{ request()->routeIs('map.*') ? 'active' : '' }}" title="แผนที่">
                    <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5.764v15M9 3.236v15"/>
                    </svg>
                </a>
                <a href="{{ route('jobs.index') }}" class="navbar-icon-btn {{ request()->routeIs('jobs.*') ? 'active' : '' }}" title="หางาน / ฝึกงาน">
                    <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect width="20" height="14" x="2" y="7" rx="2" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><circle cx="12" cy="13" r="1" fill="currentColor"/>
                    </svg>
                </a>
                <a href="{{ route('announcements.index') }}" class="navbar-icon-btn {{ request()->routeIs('announcements.*') ? 'active' : '' }}" title="ประกาศข่าวสาร">
                    <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m3 11 18-5v12L3 14v-3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>
                    </svg>
                </a>
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="navbar-logout-btn" title="ออกจากระบบ">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>ออก</span>
                    </button>
                </form>
            </div>
            @elseif(!request()->routeIs('login*', 'admin.login*', 'register*', 'password.*', 'admin.password.*'))
            {{-- Guest Desktop Menu (แสดงเฉพาะหน้าข้อมูลสาธารณะ ไม่แสดงในหน้า login/register) --}}
            <nav class="navbar-center navbar-desktop">
                <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    กิจกรรม
                </a>
                <a href="{{ route('jobs.index') }}" class="{{ request()->routeIs('jobs.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    หางาน / ฝึกงาน
                </a>
                <a href="{{ route('map.index') }}" class="{{ request()->routeIs('map.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    แผนที่
                </a>
                <a href="{{ route('announcements.index') }}" class="{{ request()->routeIs('announcements.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                    ข่าวประกาศ
                </a>
            </nav>
            <div class="navbar-right navbar-desktop">
                <!-- SVG Theme Toggle Button (Desktop Guest) -->
                <button type="button" data-theme-toggle class="navbar-theme-toggle-btn" title="สลับโหมดมืด / สว่าง (Dark / Light Mode)">
                    <svg class="theme-icon-sun" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <svg class="theme-icon-moon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                <a href="{{ route('login') }}" class="btn btn-primary btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    เข้าสู่ระบบ
                </a>
                <a href="{{ route('register') }}" class="btn btn-outline btn-sm" style="display:inline-flex; align-items:center; gap:4px;">
                    สมัครสมาชิก
                </a>
            </div>
            {{-- Guest Mobile Header Right --}}
            <div class="navbar-mobile-right">
                <!-- SVG Theme Toggle Button (Mobile Guest) -->
                <button type="button" data-theme-toggle class="navbar-icon-btn navbar-theme-toggle-btn" title="สลับโหมดมืด / สว่าง">
                    <svg class="theme-icon-sun" width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32l1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                    </svg>
                    <svg class="theme-icon-moon" width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>
                    </svg>
                </button>
                <a href="{{ route('map.index') }}" class="navbar-icon-btn {{ request()->routeIs('map.*') ? 'active' : '' }}" title="แผนที่">
                    <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5.764v15M9 3.236v15"/>
                    </svg>
                </a>
                <a href="{{ route('login') }}" class="btn btn-sm btn-primary" style="padding:.35rem .75rem; font-size:.8rem; display:inline-flex; align-items:center; gap:5px; border-radius:9px;">
                    <svg class="icon-sm" style="width:14px; height:14px; margin:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    เข้าสู่ระบบ
                </a>
            </div>
            @endauth
        </div>
    </header>

    {{-- Notification Banner (กิจกรรมเร่งด่วน) --}}
    @auth
    @if(!in_array(auth()->user()->role ?? 'student', ['admin','staff']))
    <div id="notif-banner" class="notif-banner-wrapper" style="display:none;">
        <div class="container notif-banner-container">
            <div class="notif-banner-card">
                <div class="notif-banner-main">
                    <div class="notif-banner-icon-box">
                        <span class="notif-banner-pulse"></span>
                        <svg class="notif-banner-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <div class="notif-banner-content">
                        <div class="notif-banner-title-row">
                            <span class="notif-banner-badge" id="notif-banner-badge">กิจกรรมด่วน</span>
                            <span id="notif-banner-title" class="notif-banner-title"></span>
                        </div>
                        <span id="notif-banner-text" class="notif-banner-text"></span>
                    </div>
                </div>
                <div class="notif-banner-actions">
                    <a id="notif-banner-link" href="#" class="notif-banner-btn">
                        <span id="notif-banner-btn-text">ไปที่กิจกรรม</span>
                        <svg class="notif-banner-btn-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    <button type="button" onclick="document.getElementById('notif-banner').style.display='none'" class="notif-banner-close" aria-label="ปิดการแจ้งเตือน" title="ปิด">
                        <svg class="notif-banner-close-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endauth
    {{-- เนื้อหาหลัก --}}
    <div class="container" style="padding-top:1rem; padding-bottom:6rem;">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
        @yield('content')
    </div>

    {{-- Bottom Navigation Bar (Mobile App Style) --}}
    @auth
    <nav class="bottom-nav">
        <a href="{{ route('activities.index') }}" class="bottom-nav-item {{ request()->routeIs('activities.*') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span>หน้าหลัก</span>
        </a>
        <a href="{{ route('student.my') }}" class="bottom-nav-item {{ request()->routeIs('student.my') ? 'active' : '' }}" style="position:relative;">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <span>กิจกรรม</span>
            <span id="bottom-todo-badge" style="display:none;position:absolute;top:4px;right:calc(50% - 20px);min-width:16px;height:16px;border-radius:8px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:16px;text-align:center;padding:0 3px;"></span>
        </a>
        <a href="{{ route('student.scanner') }}" class="bottom-nav-item scanner-nav-item {{ request()->routeIs('student.scanner') ? 'active' : '' }}">
            <div class="scanner-icon-wrap">
                <svg class="bottom-nav-icon scanner-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <span>สแกน</span>
        </a>
        <a href="{{ route('student.summary') }}" class="bottom-nav-item {{ request()->routeIs('student.summary') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span>สรุป</span>
        </a>
        <a href="{{ route('student.profile') }}" class="bottom-nav-item {{ request()->routeIs('student.profile') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>โปรไฟล์</span>
        </a>
    </nav>
    @elseif(!request()->routeIs('login*', 'admin.login*', 'register*', 'password.*', 'admin.password.*'))
    <nav class="bottom-nav">
        <a href="{{ route('activities.index') }}" class="bottom-nav-item {{ request()->routeIs('activities.*') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span>กิจกรรม</span>
        </a>
        <a href="{{ route('jobs.index') }}" class="bottom-nav-item {{ request()->routeIs('jobs.*') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>หางาน</span>
        </a>
        <a href="{{ route('map.index') }}" class="bottom-nav-item {{ request()->routeIs('map.*') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            <span>แผนที่</span>
        </a>
        <a href="{{ route('announcements.index') }}" class="bottom-nav-item {{ request()->routeIs('announcements.*') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
            <span>ข่าวประกาศ</span>
        </a>
        <a href="{{ route('login') }}" class="bottom-nav-item {{ request()->routeIs('login') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
            <span>เข้าสู่ระบบ</span>
        </a>
    </nav>
    @endauth

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.recommend-dropdown.open').forEach(function(d) {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
    </script>

    @auth
    @if(!in_array(auth()->user()->role ?? 'student', ['admin','staff']))
    {{-- ── Notification Polling Script ── --}}
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (function() {
        var NOTIF_URL = '{{ route("student.notifications") }}';
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;

        function fetchNotifications() {
            fetch(NOTIF_URL, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r) {
                    if (!r.ok) return { alerts: [] };
                    return r.json();
                })
                .then(function(data) {
                    var alerts = data.alerts || [];
                    var banner = document.getElementById('notif-banner');
                    var navBadge = document.getElementById('nav-todo-badge');
                    var botBadge = document.getElementById('bottom-todo-badge');

                    if (!alerts.length) {
                        if (banner) banner.style.display = 'none';
                        if (navBadge) navBadge.style.display = 'none';
                        if (botBadge) botBadge.style.display = 'none';
                        return;
                    }

                    var count = alerts.length;
                    if (navBadge) { navBadge.textContent = count; navBadge.style.display = 'inline-block'; }
                    if (botBadge) { botBadge.textContent = count; botBadge.style.display = 'inline-block'; }

                    var urgent = alerts.filter(function(a) { return a.type === 'checkin_open' || a.type === 'checkin_soon'; });
                    if (urgent.length && banner) {
                        var first = urgent[0];
                        var badgeEl = document.getElementById('notif-banner-badge');
                        var titleEl = document.getElementById('notif-banner-title');
                        var textEl = document.getElementById('notif-banner-text');
                        var linkEl = document.getElementById('notif-banner-link');
                        var btnTextEl = document.getElementById('notif-banner-btn-text');

                        if (badgeEl) {
                            badgeEl.textContent = first.type === 'checkin_open' ? 'เช็คอินได้แล้ว' : 'ใกล้เปิดเช็คอิน';
                        }
                        if (titleEl) {
                            titleEl.textContent = first.title;
                        }
                        if (textEl) {
                            textEl.textContent = first.body;
                        }
                        if (linkEl) {
                            linkEl.href = first.url;
                        }
                        if (btnTextEl) {
                            btnTextEl.textContent = first.type === 'checkin_open' ? 'เช็คอินทันที' : 'ไปที่กิจกรรม';
                        }
                        banner.style.display = 'block';
                    }
                })
                .catch(function() {});
        }
        setTimeout(fetchNotifications, 2000);

        if (window.Echo) {
            window.Echo.private('App.Models.User.{{ auth()->id() }}')
                .listen('StudentAlertsUpdated', function(e) {
                    fetchNotifications();
                });
        }
    })();
    </script>
    @endif
    @endauth

    @auth
    @if(!in_array(auth()->user()->role ?? 'student', ['admin','staff']))
    {{-- ── Floating Chat Widget ── --}}
    {{-- ── Floating Chat Widget Styles ── --}}
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .chat-list-item { background: transparent; transition: all .15s ease; margin: 6px 8px; border-radius: 14px; border: 1px solid #f1f5f9; }
    .chat-list-item:hover { background: #f8fafc; border-color: #e2e8f0; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }

    /* Desktop widget entrance + header button feedback */
    #chatFloatPanel.cf-anim {
        animation: cf-desktop-in 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes cf-desktop-in {
        from { opacity: 0; transform: translateY(16px) scale(0.96); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    #cfHeader button:hover {
        background: rgba(255, 255, 255, 0.32) !important;
    }
    #cfHeader button:active {
        transform: scale(0.94);
    }
    #cfChatWindow {
        background:
            radial-gradient(circle at 15% 8%, rgba(249, 115, 22, 0.04), transparent 42%),
            radial-gradient(circle at 88% 92%, rgba(249, 115, 22, 0.03), transparent 42%),
            #f8fafc;
    }
    html[data-theme="dark"] #cfChatWindow {
        background:
            radial-gradient(circle at 15% 8%, rgba(249, 115, 22, 0.05), transparent 42%),
            radial-gradient(circle at 88% 92%, rgba(249, 115, 22, 0.04), transparent 42%),
            #121214;
    }
    .chat-list-item.unread { background: #FF9933; border-color: #FF9933; } /* Requested #FF9933 orange background */
    .chat-list-item.unread:hover { background: #e68a2e; border-color: #e68a2e; }
    .chat-list-item.unread .chat-title, .chat-list-item.unread .chat-preview { color: #000 !important; }

    /* Custom scrollbars for chat */
    #cfChatWindow::-webkit-scrollbar,
    #cfViewList::-webkit-scrollbar,
    #cfMsgInput::-webkit-scrollbar {
        width: 5px;
    }
    #cfChatWindow::-webkit-scrollbar-track,
    #cfViewList::-webkit-scrollbar-track,
    #cfMsgInput::-webkit-scrollbar-track {
        background: transparent;
    }
    #cfChatWindow::-webkit-scrollbar-thumb,
    #cfViewList::-webkit-scrollbar-thumb,
    #cfMsgInput::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.4);
        border-radius: 4px;
    }
    #cfChatWindow::-webkit-scrollbar-thumb:hover,
    #cfViewList::-webkit-scrollbar-thumb:hover {
        background: rgba(148, 163, 184, 0.7);
    }

    /* Input & Bottom Bar Elements */
    .cf-chat-input-area {
        border-top: 1px solid #e2e8f0;
        padding: .5rem .6rem;
        padding-bottom: calc(.5rem + env(safe-area-inset-bottom, 0px));
        background: #fff;
        flex-shrink: 0;
        width: 100%;
        box-sizing: border-box;
    }
    .cf-input-field {
        flex: 1;
        min-width: 0;
        width: 100%;
        resize: none;
        border: 1.5px solid transparent;
        border-radius: 20px;
        padding: .5rem .85rem;
        font-size: .85rem;
        line-height: 1.4;
        outline: none;
        font-family: inherit;
        max-height: 80px;
        overflow-y: auto;
        background: #f8fafc;
        color: #1e293b;
        transition: border-color .15s, background .15s, box-shadow .15s;
    }
    .cf-input-field:focus {
        background: #fff;
        border-color: #ea580c;
        box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.14);
    }
    .cf-attach-label {
        cursor: pointer;
        width: 36px;
        height: 36px;
        min-width: 36px;
        border-radius: 50%;
        background: #f1f5f9;
        border: none;
        font-size: .9rem;
        line-height: 1;
        flex-shrink: 0;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: border-color .15s, color .15s, background .15s;
    }
    .cf-attach-label:hover {
        background: #fff7ed;
        color: #ea580c;
    }

    .chat-link {
        color: #dc2626 !important;
        text-decoration: underline !important;
        text-underline-offset: 3px;
        word-break: break-all;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .chat-link-mine {
        color: #ef4444 !important;
        background: #ffffff;
        padding: 1px 6px;
        border-radius: 6px;
        display: inline-block;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        text-decoration: underline !important;
    }
    .chat-link:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }

    /* Light Theme (Default & data-theme="light") */
    html[data-theme="light"] #chatFloatPanel { 
        background: #ffffff !important; 
        box-shadow: 0 8px 40px rgba(0,0,0,0.15) !important;
        border: 1px solid #e2e8f0;
    }
    html[data-theme="light"] .chat-list-item { 
        background: transparent !important;
        border-color: #f1f5f9 !important; 
    }
    html[data-theme="light"] .chat-list-item:hover { 
        background: #f8fafc !important; 
        border-color: #e2e8f0 !important; 
    }
    html[data-theme="light"] .chat-list-item .chat-title { 
        color: #0f172a !important; 
    }
    html[data-theme="light"] .chat-list-item .chat-preview { 
        color: #64748b !important; 
    }
    html[data-theme="light"] .cf-chat-input-area {
        background: #ffffff !important;
        border-top-color: #e2e8f0 !important;
    }
    html[data-theme="light"] .cf-input-field {
        background: #ffffff !important;
        border-color: #cbd5e1 !important;
        color: #1e293b !important;
    }
    html[data-theme="light"] .cf-attach-label {
        background: #f1f5f9 !important;
        border-color: #cbd5e1 !important;
        color: #475569 !important;
    }
    html[data-theme="light"] #cfChatWindow, 
    html[data-theme="light"] #cfTypingBar {
        background: #f8fafc !important;
    }

    /* Dark Theme (data-theme="dark") */
    html[data-theme="dark"] #chatFloatPanel { 
        background: #18181b !important; 
        box-shadow: 0 8px 40px rgba(0,0,0,0.45) !important;
        border: 1px solid #27272a;
    }
    html[data-theme="dark"] .chat-list-item { 
        background: transparent !important;
        border-color: #27272a !important; 
    }
    html[data-theme="dark"] .chat-list-item:hover { 
        background: #27272a !important; 
        border-color: #3f3f46 !important; 
    }
    html[data-theme="dark"] .chat-list-item .chat-title { 
        color: #f4f4f5 !important; 
    }
    html[data-theme="dark"] .chat-list-item .chat-preview { 
        color: #a1a1aa !important; 
    }
    html[data-theme="dark"] .chat-list-item.unread { 
        background: #ea580c !important; 
        color: #fff !important; 
        border-color: #ea580c !important; 
    }
    html[data-theme="dark"] .chat-list-item.unread .chat-title, 
    html[data-theme="dark"] .chat-list-item.unread .chat-preview { 
        color: #fff !important; 
    }
    html[data-theme="dark"] .cf-chat-input-area {
        background: #18181b !important;
        border-top-color: #27272a !important;
    }
    html[data-theme="dark"] .cf-input-field {
        background: #27272a !important;
        border-color: #3f3f46 !important;
        color: #f4f4f5 !important;
    }
    html[data-theme="dark"] .cf-attach-label {
        background: #27272a !important;
        border-color: #3f3f46 !important;
        color: #d4d4d8 !important;
    }
    html[data-theme="dark"] #cfChatWindow, 
    html[data-theme="dark"] #cfTypingBar {
        background: #121214 !important;
    }

    /* ซ่อน Scrollbar ของ Floating Chat Widget */
    #cfChatWindow, #cfViewList, #cfListContent, #cfMsgInput {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    #cfChatWindow::-webkit-scrollbar, 
    #cfViewList::-webkit-scrollbar, 
    #cfListContent::-webkit-scrollbar, 
    #cfMsgInput::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
    #cfMsgInput {
        resize: none !important;
    }

    /* ══════════════════════════════════════════════
       NATIVE CHAT UI — floating widget on phones/tablets
       ══════════════════════════════════════════════ */
    @media (max-width: 1024px) {
        /* Panel overlays the whole screen; launcher stays in place behind it */
        #chatFloatPanel {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100dvh !important;
            max-height: 100dvh !important;
            z-index: 8501 !important;
            border-radius: 0 !important;
            border: none !important;
            box-shadow: none !important;
            animation: cf-fullscreen-in 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes cf-fullscreen-in {
            from { opacity: 0; transform: translateY(14px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── Native gradient header ── */
        #cfHeader {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 55%, #c2410c 100%) !important;
            padding: 0.65rem 0.75rem !important;
            padding-top: calc(0.65rem + env(safe-area-inset-top));
            box-shadow: 0 2px 8px rgba(194, 65, 12, 0.35);
        }

        #cfBackBtn,
        #cfHeader + div ~ div button[onclick="closeChatWidget()"],
        #cfHeader button[onclick="closeChatWidget()"] {
            width: 34px !important;
            height: 34px !important;
            min-width: 34px !important;
            border-radius: 50% !important;
            background: rgba(255, 255, 255, 0.18) !important;
            border: 1px solid rgba(255, 255, 255, 0.28) !important;
            opacity: 1 !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            font-size: 1rem !important;
        }

        #cfHeaderTitle {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
        }

        /* ── Thread list: full-bleed rows ── */
        .chat-list-item {
            margin: 4px 8px !important;
            border-radius: 14px !important;
            padding: 0.7rem 0.75rem !important;
        }

        /* ── Chat canvas ── */
        #cfChatWindow {
            background:
                radial-gradient(circle at 15% 8%, rgba(249, 115, 22, 0.05), transparent 42%),
                radial-gradient(circle at 88% 92%, rgba(249, 115, 22, 0.04), transparent 42%),
                #f8fafc !important;
            padding: 0.7rem 0.6rem 0.2rem !important;
            gap: 0.35rem !important;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        /* ── Composer: floating pill ── */
        .cf-chat-input-area {
            padding: 0.45rem 0.55rem !important;
            padding-bottom: calc(0.45rem + env(safe-area-inset-bottom)) !important;
            border-top: 1px solid #e2e8f0 !important;
            box-shadow: 0 -2px 10px rgba(15, 23, 42, 0.06);
        }

        #cfChatForm {
            display: flex !important;
            gap: 0.4rem !important;
            flex-wrap: nowrap !important;
            align-items: flex-end !important;
            min-width: 0 !important;
        }

        .cf-attach-label {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            flex-shrink: 0 !important;
            border-radius: 50% !important;
            border: none !important;
            background: #f1f5f9 !important;
            color: #64748b !important;
        }

        .cf-input-field {
            min-height: 40px !important;
            max-height: 96px !important;
            border-radius: 20px !important;
            padding: 0.55rem 0.9rem !important;
            /* 16px prevents iOS Safari auto-zoom on focus, which distorts
               the fixed fullscreen panel layout */
            font-size: 16px !important;
            line-height: 1.4 !important;
            min-width: 0 !important;
            flex: 1 1 auto !important;
            background: #f8fafc !important;
            border: 1.5px solid transparent !important;
            box-sizing: border-box !important;
            transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
        }

        .cf-input-field:focus {
            background: #ffffff !important;
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12) !important;
        }

        #cfSendBtn {
            width: 40px !important;
            height: 40px !important;
            min-width: 40px !important;
            flex-shrink: 0 !important;
            padding: 0 !important;
            border-radius: 50% !important;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%) !important;
            box-shadow: 0 3px 8px rgba(234, 88, 12, 0.35);
        }

        #cfSendBtn:active {
            transform: scale(0.92);
        }

        /* ── Dark theme ── */
        html[data-theme="dark"] #cfHeader {
            background: linear-gradient(135deg, #c2410c 0%, #9a3412 100%) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
        }

        html[data-theme="dark"] #cfChatWindow {
            background:
                radial-gradient(circle at 15% 8%, rgba(249, 115, 22, 0.06), transparent 42%),
                radial-gradient(circle at 88% 92%, rgba(249, 115, 22, 0.05), transparent 42%),
                #121214 !important;
        }

        html[data-theme="dark"] .cf-chat-input-area {
            border-top-color: #27272a !important;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.35);
        }

        html[data-theme="dark"] .cf-input-field {
            background: #27272a !important;
            color: #f4f4f5 !important;
        }

        html[data-theme="dark"] .cf-input-field:focus {
            background: #18181b !important;
            border-color: #f97316 !important;
        }

        html[data-theme="dark"] .cf-attach-label {
            background: #27272a !important;
            color: #a1a1aa !important;
        }
    }
    </style>
    
    <div id="chatFloatWidget" style="position:fixed;bottom:5.5rem;right:1.1rem;z-index:8500;display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;">
        <div id="chatFloatPanel" style="display:none;width:360px;height:520px;background:#fff;border-radius:20px;box-shadow:0 12px 40px rgba(0,0,0,.18),0 4px 12px rgba(0,0,0,.08);overflow:hidden;flex-direction:column;">
            <div id="cfHeader" style="background:linear-gradient(135deg,#f97316 0%,#ea580c 55%,#c2410c 100%);padding:.6rem .85rem;display:flex;align-items:center;gap:.5rem;flex-shrink:0;box-shadow:0 2px 8px rgba(194,65,12,.35);">
                <button id="cfBackBtn" onclick="cfBackToList()" style="display:none;width:30px;height:30px;min-width:30px;align-items:center;justify-content:center;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);color:#fff;cursor:pointer;padding:0;border-radius:50%;" aria-label="ย้อนกลับ"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></button>
                <span id="cfHeaderTitle" style="color:#fff;font-weight:700;font-size:.88rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:.5rem;"><span style="width:30px;height:30px;min-width:30px;border-radius:50%;background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.35);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"><svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg></span> <span style="overflow:hidden;text-overflow:ellipsis;">ข้อความของฉัน</span></span>
                <button onclick="closeChatWidget()" style="width:30px;height:30px;min-width:30px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.28);color:#fff;cursor:pointer;padding:0;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;transition:background .15s;" aria-label="ปิด"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div id="cfViewList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;">
                <div id="cfListContent" style="flex:1;">
                    <div style="padding:1.5rem;text-align:center;font-size:.85rem;color:#94a3b8;">กำลังโหลด...</div>
                </div>
            </div>
            <div id="cfViewChat" style="display:none;flex-direction:column;flex:1;min-height:0;">
                <div id="cfChatWindow" style="flex:1;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:.45rem;background:#f8fafc;"></div>
                <div id="cfTypingBar" style="display:none;align-items:center;padding:.4rem .75rem;background:#f8fafc;font-size:.72rem;color:#f97316;">
                    <svg style="width:12px;height:12px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    ผู้ดูแลกำลังพิมพ์...
                </div>
                <div class="cf-chat-input-area">
                    <div id="cfAttachPreview" style="display:none;gap:.3rem;flex-wrap:wrap;margin-bottom:.3rem;"></div>
                    <form id="cfChatForm" enctype="multipart/form-data" style="display:flex;gap:.35rem;align-items:flex-end;">
                        @csrf
                        <label class="cf-attach-label" title="แนบไฟล์">
                            <svg style="width:16px;height:16px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg><input type="file" id="cfFileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
                        </label>
                        <textarea id="cfMsgInput" class="cf-input-field" name="message" rows="1" placeholder="พิมพ์ข้อความ..."></textarea>
                        <button type="submit" id="cfSendBtn" style="width:38px;height:38px;min-width:38px;padding:0;background:linear-gradient(135deg,#f97316 0%,#ea580c 100%);color:#fff;border:none;border-radius:50%;font-size:.82rem;cursor:pointer;font-weight:500;flex-shrink:0;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(234,88,12,.35);transition:transform .15s ease,box-shadow .15s ease;">
                            <svg style="width:16px;height:16px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <button id="chatFloatBtn" onclick="toggleChatWidget()" style="width:52px;height:52px;border-radius:50%;background:#ea580c;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 18px rgba(234,88,12,.45);display:flex;align-items:center;justify-content:center;position:relative;transition:transform .15s;">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span id="chatFloatBadge" style="display:none;position:absolute;top:-3px;right:-3px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;border:2px solid #fff;"></span>
        </button>
    </div>

    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (function () {
        var THREADS_URL = '{{ route("chat.threads") }}';
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;
        var USER_ID = '{{ auth()->id() }}';
        var MY_PHOTO = '{{ auth()->user()->profile_photo ? asset("storage/".auth()->user()->profile_photo) : "" }}';
        var MY_NAME = '{{ auth()->user()->full_name ?? auth()->user()->name ?? "คุณ" }}';

        var panelOpen = false;
        var threads = [];
        var currentJobId = null;
        var currentRoomId = null;
        var currentRoomEcho = null; // Echo subscription for current open room

        window.toggleChatWidget = function () { panelOpen ? closeChatWidget() : openChatWidget(); };
        window.closeChatWidget = function () {
            panelOpen = false;
            document.getElementById('chatFloatPanel').style.display = 'none';
            document.getElementById('chatFloatBtn').style.transform = '';
        };
        window.openChatWidget = function() {
            panelOpen = true;
            var panel = document.getElementById('chatFloatPanel');
            panel.style.display = 'flex';
            panel.classList.add('cf-anim');
            document.getElementById('chatFloatBtn').style.transform = 'scale(1.1)';
            showListView();
            loadThreads();
        };

        function showListView() {
            // Unsubscribe from the room channel when leaving chat view
            if (currentRoomEcho && currentRoomId) {
                try { window.Echo.leave('chat.room.' + currentRoomId); } catch(e) {}
                currentRoomEcho = null;
            }
            currentJobId = null;
            currentRoomId = null;
            document.getElementById('cfViewList').style.display = 'flex';
            document.getElementById('cfViewChat').style.display = 'none';
            document.getElementById('cfBackBtn').style.display = 'none';
            document.getElementById('cfHeaderTitle').innerHTML = '<svg style="width:16px;height:16px;display:inline;vertical-align:-3px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> ข้อความของฉัน';
        }
        window.cfBackToList = function () { showListView(); loadThreads(); };

        window.showChatView = function(jobId, jobTitle) {
            currentJobId = jobId;
            currentRoomId = null;
            document.getElementById('cfViewList').style.display = 'none';
            document.getElementById('cfViewChat').style.display = 'flex';
            document.getElementById('cfBackBtn').style.display = 'inline-block';
            document.getElementById('cfHeaderTitle').textContent = jobTitle;
            loadMessages(jobId);
            fetch('/jobs/' + jobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            var idx = threads.findIndex(function(t){ return t.job_id == jobId; });
            if (idx >= 0) { threads[idx].unread = 0; recalcBadge(); }
        };

        function loadThreads() {
            var url = THREADS_URL + '?_t=' + new Date().getTime();
            fetch(url, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    threads = data.threads || [];
                    renderThreads();
                    updateBadge(data.total_unread || 0);
                });
        }

        function formatLastSeen(lastSeenAt) {
            if (!lastSeenAt) return 'ออฟไลน์';
            var date = new Date(lastSeenAt);
            if (isNaN(date.getTime())) return 'ออฟไลน์';
            var now = new Date();
            var diffSec = Math.max(0, Math.floor((now - date) / 1000));
            var diffMin = Math.floor(diffSec / 60);
            var diffHours = Math.floor(diffMin / 60);
            if (diffSec < 60) {
                return 'ออนไลน์เมื่อสักครู่';
            } else if (diffMin < 60) {
                return 'ออนไลน์เมื่อ ' + diffMin + ' นาทีที่แล้ว';
            } else if (diffHours < 24) {
                return 'ออนไลน์เมื่อ ' + diffHours + ' ชม. ที่แล้ว';
            } else {
                return 'ออนไลน์เมื่อ ' + date.toLocaleDateString('th-TH', { day: '2-digit', month: '2-digit' }) + ' ' + date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            }
        }

        window.onlineUsersList = [];
        function isStaffMemberOnline(staffId) {
            if (!staffId || staffId === 0 || staffId === '0') {
                return window.onlineUsersList.some(function(u) {
                    return (u.role === 'admin' || u.role === 'staff' || u.is_staff) && String(u.id) !== String(USER_ID);
                });
            }
            return window.onlineUsersList.some(function(u) {
                return String(u.id) === String(staffId);
            });
        }

        function renderThreads() {
            var el = document.getElementById('cfListContent');
            
            // Filter out job_id=0 if it came from the server to avoid duplicates
            var activeThreads = threads.filter(function(t) { return t.job_id != 0; });
            var supportThread = threads.find(function(t) { return t.job_id == 0; });
            
            var isSupportUnread = supportThread && (supportThread.unread || 0) > 0;
            var supportPreview = supportThread && supportThread.last_message ? (supportThread.last_message.length > 32 ? supportThread.last_message.slice(0,32)+'…' : supportThread.last_message) : 'สอบถามปัญหาการใช้งาน';
            var supportLastSeen = (supportThread && supportThread.staff_last_seen) ? supportThread.staff_last_seen : (threads.length > 0 && threads[0].staff_last_seen ? threads[0].staff_last_seen : null);
            var isSupOnline = isStaffMemberOnline(0);
            var supportStatusHtml = isSupOnline 
                ? '<span style="color:#10b981;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span> กำลังใช้งาน</span>' 
                : '<span style="color:#94a3b8;">' + formatLastSeen(supportLastSeen) + '</span>';
            
            var supportChatHtml = '<div onclick="showChatView(0, \'ติดต่อสอบถามเจ้าหน้าที่\')" style="display:flex;align-items:center;gap:.65rem;padding:.65rem .9rem;cursor:pointer;" class="chat-list-item ' + (isSupportUnread ? 'unread' : '') + '">'
                + '<div style="position:relative;flex-shrink:0;">'
                + '<div style="width:34px;height:34px;border-radius:50%;background:#ffedd5;color:#ea580c;display:flex;align-items:center;justify-content:center;">'
                + '<svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.05 2a9 9 0 0 1 8 7.94"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.05 6A5 5 0 0 1 18 10"/></svg>'
                + '</div>'
                + '<span class="cf-staff-online-dot cf-staff-online-dot-0" data-job-id="0" data-staff-id="0" style="display:' + (isSupOnline ? 'block' : 'none') + ';position:absolute;bottom:-1px;right:-1px;width:9px;height:9px;background:#10b981;border:2px solid #fff;border-radius:50%;box-shadow:0 0 4px #10b981;" title="กำลังใช้งาน"></span>'
                + '</div>'
                + '<div style="flex:1;min-width:0;">'
                + '<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:2px;">'
                + '<div class="chat-title" style="font-size:.82rem;font-weight:' + (isSupportUnread?'700':'500') + ';color:#1e293b;">ติดต่อสอบถามเจ้าหน้าที่</div>'
                + '<div class="cf-thread-status cf-thread-status-0" data-job-id="0" data-staff-id="0" data-last-seen="' + (supportLastSeen || '') + '" style="font-size:.65rem;flex-shrink:0;">' + supportStatusHtml + '</div>'
                + '</div>'
                + '<div class="chat-preview" style="font-size:.7rem;color:' + (isSupportUnread?'#1e293b':'#64748b') + ';font-weight:' + (isSupportUnread?'700':'400') + ';">' + supportPreview + '</div>'
                + '</div>'
                + (isSupportUnread ? '<div style="min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;">' + supportThread.unread + '</div>' : '')
                + '</div>';

            if (!activeThreads.length) {
                el.innerHTML = supportChatHtml + '<div style="padding:2rem 1rem;text-align:center;font-size:.83rem;color:#94a3b8;">ยังไม่มีข้อความเกี่ยวกับงาน</div>';
                return;
            }
            el.innerHTML = supportChatHtml + activeThreads.map(function(t) {
                var isUnread = (t.unread || 0) > 0;
                var isArchived = !!t.job_deleted;
                var titlePrefix = isArchived ? '<span style="font-size:.6rem;color:#b45309;background:#fef3c7;border:1px solid #fcd34d;border-radius:999px;padding:1px 6px;margin-right:4px;vertical-align:1px;">ลบแล้ว</span>' : '';
                var preview = t.last_message ? (t.last_message.length > 32 ? t.last_message.slice(0,32)+'…' : t.last_message) : '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ไฟล์แนบ';
                var safeTitle = (t.job_title || 'งานกิจกรรม').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                var threadLastSeen = t.staff_last_seen || null;
                var isJobOnline = isStaffMemberOnline(t.staff_id);
                var threadStatusHtml = isJobOnline 
                    ? '<span style="color:#10b981;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span> กำลังใช้งาน</span>' 
                    : '<span style="color:#94a3b8;">' + formatLastSeen(threadLastSeen) + '</span>';
                
                var avatarHtml = '';
                if (t.avatar) {
                    avatarHtml = '<img src="' + t.avatar + '" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;">';
                } else {
                    avatarHtml = '<div style="width:34px;height:34px;border-radius:50%;background:#ea580c;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;">' + safeTitle.charAt(0).toUpperCase() + '</div>';
                }

                return '<div onclick="showChatView(' + t.job_id + ',\'' + safeTitle + '\')" '
                    + 'style="display:flex;align-items:center;gap:.65rem;padding:.65rem .9rem;cursor:pointer;" class="chat-list-item ' + (isUnread ? 'unread' : '') + '">'
                    + '<div style="position:relative;flex-shrink:0;">'
                    + avatarHtml
                    + '<span class="cf-staff-online-dot cf-staff-online-dot-' + t.job_id + '" data-job-id="' + t.job_id + '" data-staff-id="' + (t.staff_id || '') + '" style="display:' + (isJobOnline ? 'block' : 'none') + ';position:absolute;bottom:-1px;right:-1px;width:9px;height:9px;background:#10b981;border:2px solid #fff;border-radius:50%;box-shadow:0 0 4px #10b981;" title="กำลังใช้งาน"></span>'
                    + '</div>'
                    + '<div style="flex:1;min-width:0;">'
                    + '<div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:2px;">'
                    + '<div class="chat-title" style="font-size:.82rem;font-weight:' + (isUnread?'700':'500') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:' + (isArchived ? '#94a3b8' : '#1e293b') + ';max-width:150px;">' + titlePrefix + safeTitle + '</div>'
                    + '<div class="cf-thread-status cf-thread-status-' + t.job_id + '" data-job-id="' + t.job_id + '" data-staff-id="' + (t.staff_id || '') + '" data-last-seen="' + (threadLastSeen || '') + '" style="font-size:.65rem;flex-shrink:0;">' + threadStatusHtml + '</div>'
                    + '</div>'
                    + '<div class="chat-preview" style="font-size:.7rem;color:' + (isUnread?'#1e293b':'#64748b') + ';font-weight:' + (isUnread?'700':'400') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + preview + '</div>'
                    + '</div>'
                    + (isUnread ? '<div style="min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;">' + t.unread + '</div>' : '')
                    + '</div>';
            }).join('');
        }
        window.showChatView = showChatView;

        function formatReadStatus(readAt, isRead) {
            if (!readAt && !isRead) return 'ส่งแล้ว';
            if (!readAt) return 'ส่งแล้ว';

            var readTime = new Date(readAt);
            if (isNaN(readTime.getTime())) return 'ส่งแล้ว';

            var now = new Date();
            var diffSec = Math.max(0, Math.floor((now.getTime() - readTime.getTime()) / 1000));
            var diffMin = Math.floor(diffSec / 60);
            var diffHours = Math.floor(diffMin / 60);

            if (diffSec < 60) {
                return 'เพิ่งอ่าน';
            } else if (diffMin < 60) {
                return 'อ่านเมื่อ ' + diffMin + ' นาทีที่แล้ว';
            } else if (diffHours < 24) {
                return 'อ่านเมื่อ ' + diffHours + ' ชม. ที่แล้ว';
            } else {
                return 'อ่านเมื่อ ' + readTime.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            }
        }

        // Live Dynamic Ticker - automatically updates minutes elapsed
        function updateAllFloatingReadStatuses() {
            document.querySelectorAll('.cf-read-status[data-read-at]').forEach(function(el) {
                var readAt = el.getAttribute('data-read-at');
                if (readAt) {
                    el.textContent = formatReadStatus(readAt, true);
                }
            });
        }
        updateAllFloatingReadStatuses();
        setInterval(updateAllFloatingReadStatuses, 10000);

        // Newest read timestamp seen (guards against out-of-order events)
        var newestFloatingReadAtMs = 0;
        function resetFloatingReadGuard() {
            newestFloatingReadAtMs = 0;
            document.querySelectorAll('.cf-read-status[data-read-at]').forEach(function(el) {
                var t = new Date(el.getAttribute('data-read-at')).getTime();
                if (!isNaN(t) && t > newestFloatingReadAtMs) newestFloatingReadAtMs = t;
            });
        }
        resetFloatingReadGuard();

        function applyFloatingReadUpdate(readAt) {
            var t = new Date(readAt).getTime();
            if (isNaN(t) || t <= newestFloatingReadAtMs) return; // ignore stale/duplicate events
            newestFloatingReadAtMs = t;
            document.querySelectorAll('.cf-read-status').forEach(function(el) {
                el.setAttribute('data-read-at', readAt);
                el.textContent = formatReadStatus(readAt, true);
                el.style.color = '#10b981';
                setTimeout(function(){ el.style.color = '#f97316'; }, 2000);
            });
        }

        // Polling fallback: if a WebSocket read event was missed, the status
        // self-heals within 5 seconds from the server's persisted value.
        function pollFloatingReadStatus() {
            if (currentJobId === null || currentJobId === undefined) return;
            fetch('/jobs/' + currentJobId + '/chat/read-status', { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    if (res && res.success && res.other_read_at) applyFloatingReadUpdate(res.other_read_at);
                })
                .catch(function() {});
        }
        setInterval(pollFloatingReadStatus, 5000);

        function playChatChime() {
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.12); // A5
                gain.gain.setValueAtTime(0.08, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.28);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.28);
            } catch(e) {}
        }

        function appendMessageToChat(e) {
            // Prevent duplicate if already rendered
            if (document.getElementById('cf-msg-' + e.id)) return;
            if (e.user && String(e.user.id) === String(USER_ID)) return;
            var win = document.getElementById('cfChatWindow');
            if (!win) return;
            // Build a msg-compatible object from broadcast payload
            var msg = {
                id: e.id,
                user_id: e.user ? e.user.id : null,
                user: e.user || null,
                message: e.message,
                is_edited: false,
                attachments: e.attachments || [],
                created_at: e.created_at
            };
            win.appendChild(buildBubble(msg));
            win.scrollTop = win.scrollHeight;
            if (panelOpen && currentJobId !== null) {
                fetch('/jobs/' + currentJobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            }
            if (e.user && String(e.user.id) !== String(USER_ID)) {
                playChatChime();
            }
        }

        function subscribeRoomChannel(roomId) {
            // Unsubscribe previous room if any
            if (currentRoomEcho && currentRoomId && currentRoomId !== roomId) {
                try { window.Echo.leave('chat.room.' + currentRoomId); } catch(e) {}
            }
            if (!window.Echo) return;
            currentRoomEcho = window.Echo.private('chat.room.' + roomId)
                .listen('.MessageSent', function(e) {
                    if (e.user && String(e.user.id) === String(USER_ID)) return; // Skip own (optimistic)
                    appendMessageToChat(e);
                })
                .listen('.MessageDeleted', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) el.remove();
                })
                .listen('.MessagesRead', function(e) {
                    if (String(e.reader_id) === String(USER_ID)) return;
                    applyFloatingReadUpdate(e.read_at || new Date().toISOString());
                })
                .listen('.MessageEdited', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) {
                        var p = el.querySelector('p');
                        if (p) p.innerHTML = linkifyText(e.message, false);
                        if (!el.textContent.includes('(แก้ไขแล้ว)')) {
                            var editedSpan = document.createElement('span');
                            editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                            editedSpan.textContent = '(แก้ไขแล้ว)';
                            p.parentNode.appendChild(editedSpan);
                        }
                    }
                });
        }

        function linkifyText(text, isMine) {
            if (!text) return '';
            var safe = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            var cls = isMine ? 'chat-link chat-link-mine' : 'chat-link';
            return safe.replace(/(https?:\/\/[^\s<]+[^<.,:;"')\]\s])/gi, function(url) {
                return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="' + cls + '">' + url + '</a>';
            });
        }

        function loadMessages(jobId) {
            var win = document.getElementById('cfChatWindow');
            win.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.82rem;color:#94a3b8;">กำลังโหลด...</div>';
            fetch('/jobs/' + jobId + '/chat/messages', { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    win.innerHTML = '';
                    currentRoomId = data.room_id;
                    // Subscribe to this specific room channel for real-time updates
                    if (currentRoomId) { subscribeRoomChannel(currentRoomId); }
                    var msgs = data.messages || [];
                    if (!Array.isArray(msgs)) msgs = Object.values(msgs);
                    
                    var lastMineId = null;
                    if (msgs.length > 0) {
                        var lastMsg = msgs[msgs.length - 1];
                        var isLastMine = lastMsg.user_id == USER_ID || (lastMsg.user && lastMsg.user.id == USER_ID);
                        if (isLastMine) {
                            lastMineId = lastMsg.id;
                        }
                    }
                    msgs.forEach(function(m) { win.appendChild(buildBubble(m, m.id === lastMineId)); });
                    win.scrollTop = win.scrollHeight;
                    if (msgs.length && msgs[msgs.length - 1].created_at) { cfLastMsgTs = msgs[msgs.length - 1].created_at; }
                    resetFloatingReadGuard();
                    // Archived thread (job deleted): hide composer, show notice
                    applyFloatingReadOnly(threads.find(function(t) { return t.job_id == jobId; }));
                });
        }

        var cfNotice = null;
        function applyFloatingReadOnly(thread) {
            var form = document.getElementById('cfChatForm');
            var bar = form ? form.parentNode : null;
            if (!form || !bar) return;
            if (thread && thread.job_deleted) {
                form.style.display = 'none';
                var old = document.getElementById('cfArchivedNotice');
                if (!old) {
                    cfNotice = document.createElement('div');
                    cfNotice.id = 'cfArchivedNotice';
                    cfNotice.style.cssText = 'display:flex;align-items:center;justify-content:center;gap:6px;padding:.7rem .9rem;background:#f8fafc;font-size:.75rem;color:#b45309;font-weight:500;';
                    cfNotice.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 5 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 5-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg> ประกาศงานนี้ถูกลบแล้ว — ดูได้เฉพาะประวัติการแชท';
                    bar.parentNode.insertBefore(cfNotice, bar);
                    bar.style.display = 'none';
                } else {
                    bar.style.display = 'none';
                }
            } else {
                // Restore the flex row layout — an empty string would drop the
                // inline display:flex and stack the composer vertically
                form.style.display = 'flex';
                bar.style.display = '';
                var n = document.getElementById('cfArchivedNotice');
                if (n) n.remove();
            }
        }

        function buildBubble(msg, isLastMine) {
            var mine = msg.user_id == USER_ID || (msg.user && msg.user.id == USER_ID);
            var isTemp = String(msg.id).startsWith('tmp-');
            if (isLastMine === undefined) isLastMine = mine;

            // Always remove previous read status so it disappears when other user replies
            document.querySelectorAll('.cf-read-status').forEach(function(el){ el.remove(); });
            // Only the latest message can be edited — strip stale edit items,
            // but keep the delete menu on older own messages
            document.querySelectorAll('#cfChatWindow .cf-edit-item').forEach(function(el){ el.remove(); });

            var row = document.createElement('div');
            row.id = 'cf-msg-' + msg.id;
            row.style.cssText = 'display:flex;flex-direction:' + (mine?'row-reverse':'row') + ';align-items:flex-end;gap:.3rem;margin-bottom:.2rem;position:relative;';
            
            if (!mine) {
                var avatarDiv = document.createElement('div');
                avatarDiv.style.cssText = 'width:24px;height:24px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#94a3b8;color:#fff;font-size:0.65rem;font-weight:700;position:relative;';
                var photo = msg.user && msg.user.photo ? msg.user.photo : null;
                var label = msg.user && msg.user.name ? msg.user.name : 'ผู้ดูแล';
                if (photo) {
                    var img = document.createElement('img');
                    img.src = photo;
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:50%;';
                    avatarDiv.appendChild(img);
                } else {
                    avatarDiv.textContent = label.charAt(0).toUpperCase();
                }
                var dotSpan = document.createElement('span');
                dotSpan.className = 'cf-avatar-online-dot';
                dotSpan.style.cssText = 'display:' + (window.isStaffOnline ? 'block' : 'none') + ';position:absolute;bottom:-1px;right:-1px;width:7px;height:7px;background:#10b981;border:1.5px solid #fff;border-radius:50%;box-shadow:0 0 3px #10b981;';
                avatarDiv.appendChild(dotSpan);
                row.appendChild(avatarDiv);
            }

            var col = document.createElement('div');
            col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (mine?'flex-end':'flex-start') + ';max-width:75%;';
            
            var bubble = document.createElement('div');
            var hasOnlyImages = !msg.message && msg.attachments && msg.attachments.length > 0 && msg.attachments.every(a => (a.mime_type || '').indexOf('image/') === 0);
            if (hasOnlyImages) {
                bubble.style.cssText = 'border-radius:' + (mine?'14px 4px 14px 14px':'4px 14px 14px 14px') + ';background:transparent;padding:0;box-shadow:none;display:flex;flex-direction:column;gap:4px;';
            } else {
                if (!mine) bubble.className = 'cf-msg-bubble-other';
                bubble.style.cssText = 'padding:.45rem .75rem;border-radius:' + (mine?'14px 4px 14px 14px':'4px 14px 14px 14px') + ';background:' + (mine?'#ea580c':'#fff') + ';color:' + (mine?'#ffffff':'#1e293b') + ';font-size:.82rem;line-height:1.45;box-shadow:0 1px 2px rgba(0,0,0,.08);word-break:break-word;white-space:pre-wrap;';
            }
            
            if (msg.message) {
                var p = document.createElement('p');
                p.style.cssText = 'margin:0;padding:0;line-height:1.45;color:inherit;font-size:inherit;font-family:inherit;';
                p.innerHTML = linkifyText(msg.message, mine);
                bubble.appendChild(p);
            }
            if (msg.is_edited) {
                var editedSpan = document.createElement('span');
                editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                editedSpan.textContent = '(แก้ไขแล้ว)';
                bubble.appendChild(editedSpan);
            }
            
            if (msg.attachments && msg.attachments.length) {
                msg.attachments.forEach(function(a) {
                    var attDiv = document.createElement('div');
                    attDiv.style.marginTop = hasOnlyImages ? '0' : '.3rem';
                    if ((a.mime_type || '').indexOf('image/') === 0) {
                        var img = document.createElement('img');
                        img.src = a.url;
                        img.style.cssText = 'max-width:110px;max-height:110px;object-fit:cover;border-radius:8px;display:block;cursor:pointer;';
                        img.onclick = function() { 
                            var lb = document.getElementById('imageLightbox');
                            if(!lb) {
                                lb = document.createElement('div');
                                lb.id = 'imageLightbox';
                                lb.style.cssText = 'display:flex; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; align-items:center; justify-content:center; cursor:pointer; opacity:0; transition:opacity 0.2s;';
                                lb.onclick = function() { lb.style.opacity = '0'; setTimeout(function(){lb.style.display='none';}, 200); };
                                var lbImg = document.createElement('img');
                                lbImg.id = 'lightboxImg';
                                lbImg.style.cssText = 'max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow:0 4px 24px rgba(0,0,0,0.5); transform:scale(0.95); transition:transform 0.2s;';
                                lb.appendChild(lbImg);
                                document.body.appendChild(lb);
                            }
                            var lImg = document.getElementById('lightboxImg');
                            lImg.src = a.url;
                            lb.style.display = 'flex';
                            setTimeout(function(){ lb.style.opacity = '1'; lImg.style.transform = 'scale(1)'; }, 10);
                        };
                        attDiv.appendChild(img);
                    } else {
                        var link = document.createElement('a');
                        link.href = a.url;
                        link.target = '_blank';
                        link.style.cssText = 'font-size:.75rem;text-decoration:none;display:flex;align-items:center;gap:.2rem;color:' + (mine?'#fed7aa':'#ea580c');
                        link.innerHTML = '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ' + a.original_name;
                        attDiv.appendChild(link);
                    }
                    bubble.appendChild(attDiv);
                });
            }
            
            col.appendChild(bubble);

            if (!isTemp && mine) {
                col.style.position = 'relative';
                var actions = document.createElement('div');
                actions.className = 'msg-actions';
                actions.style.cssText = 'display:flex; position:absolute; left:-34px; bottom:18px; flex-direction:row; z-index: 20; align-items:center;';
                
                var moreBtn = document.createElement('button');
                moreBtn.innerHTML = '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>';
                moreBtn.style.cssText = 'background:transparent; border:none; cursor:pointer; padding:4px; color:#94a3b8; display:flex; align-items:center; justify-content:center; border-radius:50%; transition:all .2s; margin:0 4px;';
                
                var menu = document.createElement('div');
                menu.className = 'msg-dropdown';
                menu.style.cssText = 'display:none; position:absolute; right:34px; bottom:-4px; background:#2d2d2d; color:#f8fafc; border-radius:12px; padding:6px 0; min-width:130px; box-shadow:0 4px 12px rgba(0,0,0,0.25); flex-direction:column; z-index:30;';
                
                var tail = document.createElement('div');
                tail.style.cssText = 'position:absolute; right:-4px; bottom:12px; width:10px; height:10px; background:#2d2d2d; transform:rotate(45deg); z-index:-1; border-radius:1px;';
                menu.appendChild(tail);

                var createItem = function(text, onClick) {
                    var item = document.createElement('div');
                    item.textContent = text;
                    item.style.cssText = 'padding:8px 16px; font-size:0.85rem; cursor:pointer; transition:background .15s; user-select:none; font-weight:500;';
                    item.onmouseover = function() { this.style.background = 'rgba(255,255,255,0.1)'; };
                    item.onmouseout = function() { this.style.background = 'transparent'; };
                    item.onclick = function(e) { e.stopPropagation(); onClick(); menu.style.display = 'none'; };
                    return item;
                };

                if (isLastMine) {
                    var editItem = createItem('แก้ไข', function() { window.editStudentMessage(msg.id); });
                    editItem.className = 'cf-edit-item';
                    menu.appendChild(editItem);
                }
                menu.appendChild(createItem('ยกเลิกการส่ง', function() { window.deleteStudentMessage(msg.id); }));
                
                moreBtn.onclick = function(e) {
                    e.stopPropagation();
                    var isVis = menu.style.display === 'flex';
                    document.querySelectorAll('.msg-dropdown').forEach(function(el){ el.style.display='none'; });
                    if (!isVis) menu.style.display = 'flex';
                };
                
                row.addEventListener('mouseleave', function() {
                    menu.style.display = 'none';
                });

                actions.appendChild(menu);
                actions.appendChild(moreBtn);
                // we will append to row later
            }

            // Add time and status
            var timeStr = new Date(msg.created_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
            var statusDiv = document.createElement('div');
            statusDiv.style.cssText = 'display:flex;align-items:center;gap:.25rem;margin-top:.1rem;';
            statusDiv.innerHTML = '<span style="font-size:.6rem;color:#94a3b8;">' + timeStr + '</span>';
            
            if (mine && isLastMine) {
                var readText = isTemp ? 'กำลังส่ง...' : formatReadStatus(msg.read_at, msg.is_read);
                var statusText = document.createElement('span');
                statusText.id = 'cf-status-' + msg.id;
                statusText.className = 'cf-read-status';
                if (msg.read_at) {
                    statusText.setAttribute('data-read-at', msg.read_at);
                }
                statusText.style.cssText = 'font-size:.6rem;color:' + (isTemp ? '#94a3b8' : '#f97316') + ';';
                statusText.textContent = readText;
                statusDiv.appendChild(statusText);
            }
            col.appendChild(statusDiv);

            // Append actions inside col as absolute positioned element
            if (!isTemp && mine) {
                col.appendChild(actions);
            }

            row.appendChild(col);
            
            return row;
        }

        var cfFileInput = document.getElementById('cfFileInput');
        var cfPreview = document.getElementById('cfAttachPreview');
        cfFileInput.addEventListener('change', function() {
            cfPreview.innerHTML = '';
            if (!cfFileInput.files.length) { cfPreview.style.display = 'none'; return; }
            cfPreview.style.display = 'flex';
            Array.from(cfFileInput.files).forEach(function(f) {
                var chip = document.createElement('span');
                chip.style.cssText = 'background:#ffedd5;color:#9a3412;border-radius:6px;padding:.2rem .55rem;font-size:.78rem;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;';
                if (f.type.startsWith('image/')) {
                    chip.innerHTML = '<svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' + f.name;
                } else {
                    chip.innerHTML = '<svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>' + f.name;
                }
                cfPreview.appendChild(chip);
            });
        });

        document.getElementById('cfChatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (currentJobId === null || currentJobId === undefined) return;
            var form = this;
            var msgInput = document.getElementById('cfMsgInput');
            var text = msgInput.value.trim();
            var fileInput = document.getElementById('cfFileInput');
            if (!text && fileInput.files.length === 0) return;

            var btn = document.getElementById('cfSendBtn');

            if (currentEditId) {
                btn.disabled = true;
                fetch('/chat/messages/' + currentEditId, {
                    method: 'PUT',
                    headers: { 
                        'X-CSRF-TOKEN': CSRF,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: text })
                }).then(function(r) { return r.json(); }).then(function(res) {
                    btn.disabled = false;
                    if (res.success) {
                        var el = document.getElementById('cf-msg-' + currentEditId);
                        if (el) {
                            var p = el.querySelector('p');
                            if (p) p.textContent = text;
                            if (!el.textContent.includes('(แก้ไขแล้ว)')) {
                                var editedSpan = document.createElement('span');
                                editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                                editedSpan.textContent = '(แก้ไขแล้ว)';
                                p.parentNode.appendChild(editedSpan);
                            }
                        }
                        
                        currentEditId = null;
                        msgInput.value = '';
                        btn.innerHTML = '<svg style="width:16px;height:16px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
                        btn.style.background = '#ea580c';
                        var cancelBtn = document.getElementById('cfCancelEditBtn');
                        if (cancelBtn) cancelBtn.remove();
                        
                    } else if (res.message) {
                        alert(res.message);
                    }
                }).catch(function(err) {
                    btn.disabled = false;
                    console.error(err);
                });
                return;
            }

            var fd = new FormData(form);
            if (!fd.has('message')) fd.append('message', text);

            // Optimistic UI: Append message immediately (composer stays open for rapid-fire)
            var win = document.getElementById('cfChatWindow');
            var tempId = 'tmp-' + Date.now();
            var optimisticMsg = {
                id: tempId,
                user_id: USER_ID,
                message: text,
                attachments: [], // We can't easily preview local files here without more code, but we can show the text
                created_at: new Date().toISOString()
            };
            
            // If there are files, show a placeholder in optimistic UI
            if (fileInput.files.length > 0) {
                Array.from(fileInput.files).forEach(function(f) {
                    var isImg = f.type.startsWith('image/');
                    optimisticMsg.attachments.push({
                        original_name: f.name,
                        url: isImg ? URL.createObjectURL(f) : '#',
                        mime_type: f.type
                    });
                });
            }

            var bubble = buildBubble(optimisticMsg);
            bubble.style.opacity = '0.7'; // Sending state
            win.appendChild(bubble);
            win.scrollTop = win.scrollHeight;

            // Clear input
            msgInput.value = '';
            fileInput.value = '';
            document.getElementById('cfAttachPreview').innerHTML = '';
            document.getElementById('cfAttachPreview').style.display = 'none';

            fetch('/jobs/' + currentJobId + '/chat', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, 
                body: fd 
            })
                .then(function(r) {
                    if (!r.ok) return r.json().then(function(err) { throw err; });
                    return r.json();
                })
                .then(function(data) {
                    // Replace optimistic bubble with real one
                    if (data.message) {
                        var realBubble = buildBubble(data.message);
                        bubble.parentNode.replaceChild(realBubble, bubble);
                    }
                    recalcBadge();
                    loadThreads(); // อัพเดต list (latest message) เมื่อนักศึกษาส่งข้อความเอง
                })
                .catch(function(err) {
                    console.error('Chat Error:', err);
                    bubble.style.background = '#fee2e2'; // Error state
                    bubble.style.color = '#991b1b';
                    alert(err.error || (err.errors && err.errors.message ? err.errors.message[0] : null) || 'ไม่สามารถส่งข้อความได้');
                });
        });

        // Message delivery fallback: merge messages missed by WebSocket (widget)
        var cfLastMsgTs = null;
        function cfPollNewMessages() {
            if (currentJobId === null || currentJobId === undefined) return;
            fetch('/jobs/' + currentJobId + '/chat/messages?after=' + encodeURIComponent(cfLastMsgTs || ''), { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    var msgs = data.messages || [];
                    if (!Array.isArray(msgs)) msgs = Object.values(msgs);
                    if (!msgs.length) return;
                    var last = msgs[msgs.length - 1];
                    if (last.created_at) cfLastMsgTs = last.created_at;
                    // Archived thread may have just been deleted — keep composer state fresh
                    applyFloatingReadOnly(threads.find(function(t) { return t.job_id == currentJobId; }));
                    var win = document.getElementById('cfChatWindow');
                    msgs.forEach(function(m) {
                        if (document.getElementById('cf-msg-' + m.id)) return;
                        var mine = m.user_id == USER_ID || (m.user && m.user.id == USER_ID);
                        var optEl = win.querySelector('[id^="cf-msg-tmp-"]');
                        if (mine && optEl) return; // optimistic send still in flight
                        win.appendChild(buildBubble(m, mine));
                        win.scrollTop = win.scrollHeight;
                        if (!mine) { recalcBadge(); loadThreads(); }
                    });
                })
                .catch(function() {});
        }
        setInterval(cfPollNewMessages, 3000);

        function updateBadge(count) {
            var badge = document.getElementById('chatFloatBadge');
            if (count > 0) { badge.textContent = count; badge.style.display = 'inline-block'; }
            else { badge.style.display = 'none'; }
        }
        function recalcBadge() { updateBadge(threads.reduce(function(s,t){ return s+(t.unread||0); }, 0)); }

        // Laravel Echo — ใช้ retry เพราะ app.js โหลด async (type=module)
        // chat.student.{ID} = fallback channel สำหรับรับแจ้งเตือนเมื่อไม่ได้อยู่ในห้องนั้น
        // (Real-time ในห้องที่เปิดอยู่ใช้ chat.room.{room_id} แทน)
        (function initStudentEcho() {
            if (!window.Echo) { setTimeout(initStudentEcho, 200); return; }
            window.Echo.private('chat.student.' + USER_ID)
                .listen('.MessageSent', function(e) {
                    if (e.user && String(e.user.id) === String(USER_ID)) {
                        loadThreads();
                        return;
                    }
                    // ถ้ากำลังเปิดหน้าแชทอยู่ และตรงกับห้องนี้ ให้ render ทันที
                    if (panelOpen && currentJobId !== null && currentJobId !== undefined) {
                        var eJobId = e.room ? e.room.job_id : null;
                        var isMatch = (String(currentJobId) === String(eJobId)) ||
                                      (currentJobId === 0 && (!eJobId || eJobId === 0)) ||
                                      (currentRoomId && String(currentRoomId) === String(e.room_id));
                        if (isMatch) {
                            appendMessageToChat(e);
                        }
                    }
                    loadThreads();
                })
                .listen('.MessageEdited', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) {
                        var p = el.querySelector('p');
                        if (p) p.innerHTML = linkifyText(e.message, false);
                        if (!el.textContent.includes('(แก้ไขแล้ว)')) {
                            var editedSpan = document.createElement('span');
                            editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                            editedSpan.textContent = '(แก้ไขแล้ว)';
                            p.parentNode.appendChild(editedSpan);
                        }
                    }
                })
                .listen('.MessageDeleted', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) el.remove();
                })
                .listen('.MessagesRead', function(e) {
                    if (String(e.reader_id) === String(USER_ID)) return;
                    applyFloatingReadUpdate(e.read_at || new Date().toISOString());
                })
                .listen('.ChatDeleted', function(e) {
                    loadThreads();
                });

            window.Echo.join('online')
                .here(function(users) {
                    window.onlineUsersList = users;
                    updateFloatingOnlineStatus();
                })
                .joining(function(u) {
                    window.onlineUsersList = window.onlineUsersList.filter(function(usr) { return String(usr.id) !== String(u.id); }).concat([u]);
                    updateFloatingOnlineStatus();
                })
                .leaving(function(u) {
                    window.onlineUsersList = window.onlineUsersList.filter(function(usr) { return String(usr.id) !== String(u.id); });
                    var nowIso = new Date().toISOString();
                    document.querySelectorAll('[data-staff-id="' + u.id + '"]').forEach(function(el) {
                        el.setAttribute('data-last-seen', nowIso);
                    });
                    updateFloatingOnlineStatus();
                });

            function updateFloatingOnlineStatus() {
                // 1. Update each thread in thread list
                document.querySelectorAll('.cf-staff-online-dot').forEach(function(dot) {
                    var staffId = dot.getAttribute('data-staff-id');
                    var jobId = dot.getAttribute('data-job-id');
                    var online = isStaffMemberOnline(jobId === '0' ? 0 : staffId);
                    dot.style.display = online ? 'block' : 'none';
                });

                document.querySelectorAll('.cf-thread-status').forEach(function(st) {
                    var staffId = st.getAttribute('data-staff-id');
                    var jobId = st.getAttribute('data-job-id');
                    var online = isStaffMemberOnline(jobId === '0' ? 0 : staffId);
                    if (online) {
                        st.innerHTML = '<span style="color:#10b981;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><span style="width:6px;height:6px;border-radius:50%;background:#10b981;display:inline-block;"></span> กำลังใช้งาน</span>';
                    } else {
                        var lastSeen = st.getAttribute('data-last-seen');
                        st.innerHTML = '<span style="color:#94a3b8;">' + formatLastSeen(lastSeen) + '</span>';
                    }
                });

                // 2. Update avatar online dots in current chat view
                var currentStaffId = (currentJobId && currentJobId !== 0) ? (threads.find(function(t){ return t.job_id == currentJobId; })?.staff_id) : 0;
                var isCurrentChatOnline = isStaffMemberOnline(currentJobId === 0 ? 0 : currentStaffId);
                document.querySelectorAll('.cf-avatar-online-dot').forEach(function(dot) {
                    dot.style.display = isCurrentChatOnline ? 'block' : 'none';
                });

                // 4. Update float button glow (any staff is online)
                var anyStaffOnline = isStaffMemberOnline(0);
                var btn = document.getElementById('chatFloatBtn');
                if (btn) {
                    if (anyStaffOnline) {
                        btn.style.boxShadow = '0 4px 18px rgba(16,185,129,.5), 0 0 0 2px #10b981';
                    } else {
                        btn.style.boxShadow = '0 4px 18px rgba(234,88,12,.45)';
                    }
                }
            }

            setInterval(function() {
                updateFloatingOnlineStatus();
            }, 15000);

            window.addEventListener('beforeunload', function() {
                if (window.Echo) {
                    try { window.Echo.leave('online'); } catch(e) {}
                }
            });
        })();

        window.deleteStudentMessage = function(id) {
            if (!confirm('ต้องการลบข้อความนี้ใช่หรือไม่?')) return;
            fetch('/chat/messages/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res.success) {
                    var el = document.getElementById('cf-msg-' + id);
                    if (el) el.remove();
                }
            });
        };

        var currentEditId = null;

        window.editStudentMessage = function(id) {
            var el = document.getElementById('cf-msg-' + id);
            if (!el) return;
            var msgInput = document.getElementById('cfMsgInput');

            // Fetch the latest content from the server so editing always starts
            // from the current version, even if a real-time update was missed.
            fetch('/chat/messages/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (!res || !res.success) return;
                    var latest = res.message.body || '';

                    var p = el.querySelector('p');
                    if (p) p.textContent = latest;

                    msgInput.value = latest;
                    msgInput.focus();

                    currentEditId = id;

                    var btn = document.getElementById('cfSendBtn');
                    btn.innerHTML = '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> บันทึก';
                    btn.style.background = '#10b981';

                    if (!document.getElementById('cfCancelEditBtn')) {
                        var cancelBtn = document.createElement('button');
                        cancelBtn.id = 'cfCancelEditBtn';
                        cancelBtn.type = 'button';
                        cancelBtn.innerHTML = 'ยกเลิก';
                        cancelBtn.style.cssText = 'background:#ef4444; color:#fff; border:none; border-radius:12px; padding:0 1rem; font-weight:500; font-size:.95rem; cursor:pointer; height:42px; margin-right:4px;';
                        cancelBtn.onclick = function() {
                            currentEditId = null;
                            msgInput.value = '';
                            btn.innerHTML = '<svg style="width:16px;height:16px;transform:rotate(45deg);margin-left:-2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';
                            btn.style.background = '#ea580c';
                            this.remove();
                        };
                        btn.parentNode.insertBefore(cancelBtn, btn);
                    }
                })
                .catch(function() { alert('ไม่สามารถโหลดข้อความล่าสุดได้'); });
        };

        // โหลดข้อมูลล่าสุดตอนโหลดหน้าเว็บ เพื่ออัปเดตตัวเลขแจ้งเตือนที่ปุ่มแชท
        loadThreads();
    })();
    </script>
    @endif
    @endauth
    <!-- Auto-Linker & Hashtag Script for Descriptions and Comments -->
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
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
                        var basePath = '/activities';
                        if (window.location.pathname.startsWith('/jobs')) {
                            basePath = '/jobs';
                        } else if (window.location.pathname.startsWith('/announcements')) {
                            basePath = '/announcements';
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

        // ── Theme Mode Controller ──
        window.toggleThemeMode = function() {
            var current = document.documentElement.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            if (next === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            localStorage.setItem('app-theme', next);
            updateThemeToggleIcons(next);
        };

        window.updateThemeToggleIcons = function(theme) {
            var isDark = theme === 'dark';
            document.querySelectorAll('.theme-icon-sun').forEach(function(el) {
                el.style.display = isDark ? 'block' : 'none';
            });
            document.querySelectorAll('.theme-icon-moon').forEach(function(el) {
                el.style.display = isDark ? 'none' : 'block';
            });
        };

        var currentTheme = document.documentElement.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        window.updateThemeToggleIcons(currentTheme);

        // Immediate Socket Teardown on Logout / Unload
        document.querySelectorAll('form[action*="logout"], a[href*="logout"]').forEach(function(el) {
            el.addEventListener('click', function() {
                if (window.Echo) {
                    try { window.Echo.leave('online'); window.Echo.disconnect(); } catch(e) {}
                }
            });
        });
    });
    </script>

    @yield('scripts')
    @stack('styles')
    @stack('scripts')
</body>
</html>
