{{-- เลย์เอาต์หลักฝั่งนักศึกษา: navbar + bottom nav (mobile) + เนื้อหา --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'UNI Activity - ระบบศูนย์รวมกิจกรรม')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
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
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">ออก</button>
                </form>
            </div>
            {{-- Mobile: แสดงชื่อ + ปุ่มออก --}}
            <div class="navbar-mobile-right">
                <a href="{{ route('jobs.index') }}" class="btn btn-sm btn-outline" style="border:none;padding:.25rem .5rem;" title="หางาน">
                    <svg class="icon" style="margin:0;color:{{ request()->routeIs('jobs.*') ? '#4f46e5' : '#f97316' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
                <a href="{{ route('announcements.index') }}" class="btn btn-sm btn-outline" style="border:none;padding:.25rem .5rem;" title="ประกาศ">
                    <svg class="icon" style="margin:0;color:#6366f1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                </a>
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">ออก</button>
                </form>
            </div>
            @endauth
        </div>
    </header>

    {{-- Notification Banner (กิจกรรมเร่งด่วน) --}}
    @auth
    @if(!in_array(auth()->user()->role ?? 'student', ['admin','staff']))
    <div id="notif-banner" style="display:none;background:linear-gradient(90deg,#4f46e5,#7c3aed);color:#fff;padding:.55rem 1rem;font-size:.82rem;cursor:pointer;position:sticky;top:0;z-index:999;box-shadow:0 2px 8px rgba(79,70,229,.3);">
        <div style="max-width:100%;padding: 0 16px;margin:0 auto;display:flex;align-items:center;gap:.75rem;">
            <span id="notif-banner-icon" style="flex-shrink:0;display:flex;align-items:center;">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            </span>
            <span id="notif-banner-text" style="flex:1;"></span>
            <a id="notif-banner-link" href="#" style="color:#c7d2fe;font-weight:600;font-size:.8rem;flex-shrink:0;text-decoration:none;">ไปเลย →</a>
            <button onclick="document.getElementById('notif-banner').style.display='none'" style="background:none;border:none;color:rgba(255,255,255,.7);font-size:1.1rem;cursor:pointer;flex-shrink:0;line-height:1;">✕</button>
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
    @endauth

    @yield('scripts')
    <script>
    // ปิด dropdown "แนะนำ" เมื่อคลิกข้างนอก
    document.addEventListener('click', function(e) {
        document.querySelectorAll('.recommend-dropdown.open').forEach(function(d) {
            if (!d.contains(e.target)) d.classList.remove('open');
        });
    });
    </script>

    @auth
    @if(!in_array(auth()->user()->role ?? 'student', ['admin','staff']))
    {{-- ── Notification Polling Script ── --}}
    <script>
    (function() {
        var NOTIF_URL = '{{ route("student.notifications") }}';
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;

        function fetchNotifications() {
            fetch(NOTIF_URL, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
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
                        document.getElementById('notif-banner-icon').textContent = first.icon;
                        document.getElementById('notif-banner-text').textContent = first.title + ' — ' + first.body;
                        document.getElementById('notif-banner-link').href = first.url;
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
    <div id="chatFloatWidget" style="position:fixed;bottom:5.5rem;right:1.1rem;z-index:8500;display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;">
        <div id="chatFloatPanel" style="display:none;width:330px;height:480px;background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.2);overflow:hidden;flex-direction:column;">
            <div id="cfHeader" style="background:#4f46e5;padding:.7rem 1rem;display:flex;align-items:center;gap:.5rem;flex-shrink:0;">
                <button id="cfBackBtn" onclick="cfBackToList()" style="display:none;background:none;border:none;color:#fff;font-size:1rem;cursor:pointer;padding:.1rem .3rem;line-height:1;opacity:.85;">←</button>
                <span id="cfHeaderTitle" style="color:#fff;font-weight:700;font-size:.88rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><svg style="width:16px;height:16px;display:inline;vertical-align:-3px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> ข้อความของฉัน</span>
                <button onclick="closeChatWidget()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;line-height:1;padding:.1rem .3rem;opacity:.85;">✕</button>
            </div>
            <div id="cfViewList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;">
                <div id="cfListContent" style="flex:1;">
                    <div style="padding:1.5rem;text-align:center;font-size:.85rem;color:#94a3b8;">กำลังโหลด...</div>
                </div>
            </div>
            <div id="cfViewChat" style="display:none;flex-direction:column;flex:1;min-height:0;">
                <div id="cfChatWindow" style="flex:1;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:.45rem;background:#f8fafc;"></div>
                <div id="cfTypingBar" style="display:none;align-items:center;padding:.4rem .75rem;background:#f8fafc;font-size:.72rem;color:#6366f1;">
                    <svg style="width:12px;height:12px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    ผู้ดูแลกำลังพิมพ์...
                </div>
                <div style="border-top:1px solid #e2e8f0;padding:.5rem .75rem;background:#fff;flex-shrink:0;">
                    <div id="cfAttachPreview" style="display:none;gap:.3rem;flex-wrap:wrap;margin-bottom:.3rem;"></div>
                    <form id="cfChatForm" enctype="multipart/form-data" style="display:flex;gap:.35rem;align-items:flex-end;">
                        @csrf
                        <label style="cursor:pointer;padding:.4rem .5rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem;line-height:1;flex-shrink:0;" title="แนบไฟล์">
                            <svg style="width:16px;height:16px;display:inline;vertical-align:-2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg><input type="file" id="cfFileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
                        </label>
                        <textarea id="cfMsgInput" name="message" rows="1" placeholder="พิมพ์ข้อความ..." style="flex:1;resize:none;border:1px solid #e2e8f0;border-radius:8px;padding:.4rem .6rem;font-size:.82rem;line-height:1.4;outline:none;font-family:inherit;max-height:80px;overflow-y:auto;"></textarea>
                        <button type="submit" id="cfSendBtn" style="padding:.4rem .85rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:500;flex-shrink:0;">ส่ง</button>
                    </form>
                </div>
            </div>
        </div>
        <button id="chatFloatBtn" onclick="toggleChatWidget()" style="width:52px;height:52px;border-radius:50%;background:#4f46e5;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 18px rgba(79,70,229,.45);display:flex;align-items:center;justify-content:center;position:relative;transition:transform .15s;">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <span id="chatFloatBadge" style="display:none;position:absolute;top:-3px;right:-3px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;border:2px solid #fff;"></span>
        </button>
    </div>

    <script>
    (function () {
        var THREADS_URL = '{{ route("chat.threads") }}';
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;
        var USER_ID = '{{ auth()->id() }}';
        var MY_PHOTO = '{{ auth()->user()->profile_photo ? asset("storage/".auth()->user()->profile_photo) : "" }}';
        var MY_NAME = '{{ auth()->user()->full_name ?? auth()->user()->name ?? "คุณ" }}';

        var panelOpen = false;
        var threads = [];
        var currentJobId = null;

        window.toggleChatWidget = function () { panelOpen ? closeChatWidget() : openChatWidget(); };
        window.closeChatWidget = function () {
            panelOpen = false;
            document.getElementById('chatFloatPanel').style.display = 'none';
            document.getElementById('chatFloatBtn').style.transform = '';
        };
        window.openChatWidget = function() {
            panelOpen = true;
            document.getElementById('chatFloatPanel').style.display = 'flex';
            document.getElementById('chatFloatBtn').style.transform = 'scale(1.1)';
            showListView();
            loadThreads();
        };

        function showListView() {
            currentJobId = null;
            document.getElementById('cfViewList').style.display = 'flex';
            document.getElementById('cfViewChat').style.display = 'none';
            document.getElementById('cfBackBtn').style.display = 'none';
            document.getElementById('cfHeaderTitle').innerHTML = '<svg style="width:16px;height:16px;display:inline;vertical-align:-3px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> ข้อความของฉัน';
        }
        window.cfBackToList = function () { showListView(); loadThreads(); };

        window.showChatView = function(jobId, jobTitle) {
            currentJobId = jobId;
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
            fetch(THREADS_URL, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    threads = data.threads || [];
                    renderThreads();
                    updateBadge(data.total_unread || 0);
                });
        }

        function renderThreads() {
            var el = document.getElementById('cfListContent');
            if (!threads.length) {
                el.innerHTML = '<div style="padding:2rem 1rem;text-align:center;font-size:.83rem;color:#94a3b8;">ยังไม่มีข้อความ</div>';
                return;
            }
            el.innerHTML = threads.map(function(t) {
                var isUnread = (t.unread || 0) > 0;
                var preview = t.last_message ? (t.last_message.length > 32 ? t.last_message.slice(0,32)+'…' : t.last_message) : '<svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:2px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> ไฟล์แนบ';
                var safeTitle = (t.job_title || 'งานกิจกรรม').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                return '<div onclick="showChatView(' + t.job_id + ',\'' + safeTitle + '\')" '
                    + 'style="display:flex;align-items:center;gap:.65rem;padding:.65rem .9rem;border-bottom:1px solid #f1f5f9;cursor:pointer;background:' + (isUnread?'#faf5ff':'#fff') + ';">'
                    + '<div style="width:34px;height:34px;border-radius:50%;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;">' + safeTitle.charAt(0).toUpperCase() + '</div>'
                    + '<div style="flex:1;min-width:0;">'
                    + '<div style="font-size:.82rem;font-weight:' + (isUnread?'700':'500') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#1e293b;">' + safeTitle + '</div>'
                    + '<div style="font-size:.7rem;color:' + (isUnread?'#1e293b':'#64748b') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + preview + '</div>'
                    + '</div>'
                    + (isUnread ? '<div style="min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;">' + t.unread + '</div>' : '')
                    + '</div>';
            }).join('');
        }
        window.showChatView = showChatView;

        function loadMessages(jobId) {
            var win = document.getElementById('cfChatWindow');
            win.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.82rem;color:#94a3b8;">กำลังโหลด...</div>';
            fetch('/jobs/' + jobId + '/chat/messages', { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    win.innerHTML = '';
                    var msgs = data.messages || [];
                    if (!Array.isArray(msgs)) msgs = Object.values(msgs);
                    msgs.forEach(function(m) { win.appendChild(buildBubble(m)); });
                    win.scrollTop = win.scrollHeight;
                });
        }

        function buildBubble(msg) {
            var mine = msg.user_id == USER_ID || (msg.user && msg.user.id == USER_ID);
            var isTemp = String(msg.id).startsWith('tmp-');
            var row = document.createElement('div');
            row.id = 'cf-msg-' + msg.id;
            row.style.cssText = 'display:flex;flex-direction:' + (mine?'row-reverse':'row') + ';align-items:flex-end;gap:.3rem;margin-bottom:.2rem;position:relative;';
            
            var col = document.createElement('div');
            col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (mine?'flex-end':'flex-start') + ';max-width:75%;';
            
            var bubble = document.createElement('div');
            var hasOnlyImages = !msg.message && msg.attachments && msg.attachments.length > 0 && msg.attachments.every(a => (a.mime_type || '').indexOf('image/') === 0);
            if (hasOnlyImages) {
                bubble.style.cssText = 'border-radius:' + (mine?'14px 4px 14px 14px':'4px 14px 14px 14px') + ';background:transparent;padding:0;box-shadow:none;display:flex;flex-direction:column;gap:4px;';
            } else {
                bubble.style.cssText = 'padding:.45rem .75rem;border-radius:' + (mine?'14px 4px 14px 14px':'4px 14px 14px 14px') + ';background:' + (mine?'#4f46e5':'#fff') + ';color:' + (mine?'#fff':'#1e293b') + ';font-size:.82rem;box-shadow:0 1px 2px rgba(0,0,0,.08);word-break:break-word;white-space:pre-wrap;';
            }
            
            if (msg.message) {
                var p = document.createElement('p');
                p.style.margin = '0';
                p.textContent = msg.message;
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
                        link.style.cssText = 'font-size:.75rem;text-decoration:none;display:flex;align-items:center;gap:.2rem;color:' + (mine?'#c7d2fe':'#4f46e5');
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

                menu.appendChild(createItem('แก้ไข', function() { window.editStudentMessage(msg.id); }));
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
            
            if (mine) {
                var statusText = document.createElement('span');
                statusText.style.cssText = 'font-size:.6rem;color:' + (isTemp ? '#94a3b8' : '#6366f1') + ';';
                statusText.textContent = isTemp ? 'กำลังส่ง...' : '✓ ส่งแล้ว';
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
                chip.style.cssText = 'background:#e0e7ff;color:#3730a3;border-radius:6px;padding:.2rem .55rem;font-size:.78rem;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px;';
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
            if (!currentJobId) return;
            var form = this;
            var msgInput = document.getElementById('cfMsgInput');
            var text = msgInput.value.trim();
            var fileInput = document.getElementById('cfFileInput');
            if (!text && fileInput.files.length === 0) return;

            var btn = document.getElementById('cfSendBtn');
            btn.disabled = true;

            if (currentEditId) {
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
                        btn.innerHTML = 'ส่ง';
                        btn.style.background = '#4f46e5';
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

            var btn = document.getElementById('cfSendBtn');
            btn.disabled = true;

            // Optimistic UI: Append message immediately
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
                    btn.disabled = false;
                    // Replace optimistic bubble with real one
                    if (data.message) {
                        var realBubble = buildBubble(data.message);
                        bubble.parentNode.replaceChild(realBubble, bubble);
                    }
                    recalcBadge();
                })
                .catch(function(err) {
                    console.error('Chat Error:', err);
                    bubble.style.background = '#fee2e2'; // Error state
                    bubble.style.color = '#991b1b';
                    alert(err.error || (err.errors && err.errors.message ? err.errors.message[0] : null) || 'ไม่สามารถส่งข้อความได้');
                    btn.disabled = false;
                });
        });

        function updateBadge(count) {
            var badge = document.getElementById('chatFloatBadge');
            if (count > 0) { badge.textContent = count; badge.style.display = 'inline-block'; }
            else { badge.style.display = 'none'; }
        }
        function recalcBadge() { updateBadge(threads.reduce(function(s,t){ return s+(t.unread||0); }, 0)); }

        // Laravel Echo — ใช้ retry เพราะ app.js โหลด async (type=module)
        (function initStudentEcho() {
            if (!window.Echo) { setTimeout(initStudentEcho, 200); return; }
            // ฟังจากช่องส่วนตัวของนักศึกษา (สำหรับแจ้งเตือนรวม)
            window.Echo.private('chat.student.' + USER_ID)
                .listen('.MessageSent', function(e) {
                    if (e.user && e.user.id == USER_ID) return; // Skip optimistic duplicate

                    if (currentJobId == e.room_id || (e.room && currentJobId == e.room.job_id)) { 
                        var win = document.getElementById('cfChatWindow');
                        if (!document.getElementById('cf-msg-' + e.id)) {
                            win.appendChild(buildBubble(e));
                            win.scrollTop = win.scrollHeight;
                            // Auto mark-read if panel is open and on this room
                            if (panelOpen) {
                                fetch('/jobs/' + currentJobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
                            }
                        }
                    } else {
                        loadThreads();
                    }
                })
                .listen('.MessageDeleted', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) el.remove();
                })
                .listen('.MessageEdited', function(e) {
                    var el = document.getElementById('cf-msg-' + e.id);
                    if (el) {
                        var p = el.querySelector('p');
                        if (p) p.textContent = e.message;
                        if (!el.textContent.includes('(แก้ไขแล้ว)')) {
                            var editedSpan = document.createElement('span');
                            editedSpan.style.cssText = 'font-size:0.6rem;opacity:0.7;margin-left:5px;';
                            editedSpan.textContent = '(แก้ไขแล้ว)';
                            p.parentNode.appendChild(editedSpan);
                        }
                    }
                })
                .listen('.ChatDeleted', function(e) {
                    if (currentJobId == e.room_id) {
                        closeFloatChat();
                        loadThreads();
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
            var p = el.querySelector('p');
            if (!p) return;
            
            var currentText = p.textContent.replace('(แก้ไขแล้ว)', '').trim();
            var msgInput = document.getElementById('cfMsgInput');
            msgInput.value = currentText;
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
                    btn.innerHTML = 'ส่ง';
                    btn.style.background = '#4f46e5';
                    this.remove();
                };
                btn.parentNode.insertBefore(cancelBtn, btn);
            }
        };

        // โหลดข้อมูลล่าสุดตอนโหลดหน้าเว็บ เพื่ออัปเดตตัวเลขแจ้งเตือนที่ปุ่มแชท
        loadThreads();
    })();
    </script>
    @endif
    @endauth

    @yield('scripts')
    @stack('scripts')
</body>
</html>
