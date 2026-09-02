{{-- Main layout: navbar + bottom nav (mobile) + content --}}
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
    {{-- JS loaded async (non-render-blocking) --}}
    @vite(['resources/js/app.js'])
    @stack('head')
</head>
<body>
    {{-- Top Navbar --}}
    <header class="navbar">
        <div class="navbar-inner">
            <div class="navbar-left">
                <a href="{{ route('activities.index') }}" class="navbar-brand" style="display:flex; align-items:center; gap:8px;">
                    <img src="{{ asset('logo.svg') }}" alt="Logo" style="height: 32px; width: 32px;" width="32" height="32">
                    UNI Activity
                </a>
            </div>
            @auth
            <nav class="navbar-center navbar-desktop">
                <a href="{{ route('activities.index') }}" class="{{ request()->routeIs('activities.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    กิจกรรม
                </a>
                <a href="{{ route('jobs.index') }}" class="{{ request()->routeIs('jobs.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    หางาน
                </a>
                <a href="{{ route('map.index') }}" class="{{ request()->routeIs('map.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    แผนที่
                </a>
                @if(auth()->user()->isStaff() || auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.*') ? 'active' : '' }}">
                    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    จัดการ
                </a>
                @endif
            </nav>
            @endauth
            <div class="navbar-right">
                @auth
                    <div class="dropdown" id="profileDropdown">
                        <button class="btn btn-ghost btn-sm" onclick="document.getElementById('profileMenu').classList.toggle('hidden')">
                            @if(auth()->user()->profile_photo)
                                <img src="{{ asset('storage/' . auth()->user()->profile_photo) }}" alt="" class="avatar-sm">
                            @else
                                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            @endif
                        </button>
                        <div id="profileMenu" class="dropdown-menu hidden">
                            <a href="{{ route('student.profile') }}" class="dropdown-item">
                                <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                โปรไฟล์
                            </a>
                            <hr style="margin:4px 0; border-color:#e5e7eb;">
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item text-red-600">
                                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    ออกจากระบบ
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">เข้าสู่ระบบ</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Main Content --}}
    <main class="container" style="padding-top: 70px; padding-bottom: 80px; min-height: 80vh;">
        @yield('content')
    </main>

    {{-- Mobile Bottom Navigation --}}
    <nav class="bottom-nav navbar-mobile" id="bottomNav">
        <a href="{{ route('activities.index') }}" class="bottom-nav-item {{ request()->routeIs('activities.*') ? 'active' : '' }}">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span>กิจกรรม</span>
        </a>
        <a href="{{ route('jobs.index') }}" class="bottom-nav-item {{ request()->routeIs('jobs.*') ? 'active' : '' }}">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>หางาน</span>
        </a>
        <a href="{{ route('map.index') }}" class="bottom-nav-item {{ request()->routeIs('map.*') ? 'active' : '' }}">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>แผนที่</span>
        </a>
        @auth
        <a href="{{ route('student.profile') }}" class="bottom-nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            <span>โปรไฟล์</span>
        </a>
        @else
        <a href="{{ route('login') }}" class="bottom-nav-item">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
            <span>เข้าสู่ระบบ</span>
        </a>
        @endauth
    </nav>

    <button id="themeToggle" class="theme-toggle" onclick="toggleTheme()" title="Toggle dark mode">
        <svg class="theme-icon-sun" style="display:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <svg class="theme-icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
    </button>

    <script>
        function toggleTheme() {
            var html = document.documentElement;
            var current = html.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeToggleIcons(next);
        }

        function updateThemeToggleIcons(theme) {
            var isDark = theme === 'dark';
            document.querySelectorAll('.theme-icon-sun').forEach(function(el) {
                el.style.display = isDark ? 'block' : 'none';
            });
            document.querySelectorAll('.theme-icon-moon').forEach(function(el) {
                el.style.display = isDark ? 'none' : 'block';
            });
        }

        var currentTheme = document.documentElement.getAttribute('data-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        updateThemeToggleIcons(currentTheme);
    </script>

    @yield('scripts')
    @stack('scripts')
</body>
</html>

<script>
// Close dropdown on outside click
document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('profileDropdown');
    var menu = document.getElementById('profileMenu');
    if (dropdown && menu && !dropdown.contains(e.target)) {
        menu.classList.add('hidden');
    }
});
</script>
