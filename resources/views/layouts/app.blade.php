{{-- เลย์เอาต์หลักฝั่งนักศึกษา: navbar + bottom nav (mobile) + เนื้อหา --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ระบบกิจกรรม')</title>
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
            <a href="{{ route('activities.index') }}" class="navbar-brand">UNI Activity</a>
            {{-- แนะนำ dropdown (Desktop) --}}
            @auth
            <div class="recommend-dropdown" style="position:relative;margin-left:.5rem;">
                <button onclick="this.parentElement.classList.toggle('open')" class="recommend-btn">
                    <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    แนะนำ ▾
                </button>
                <div class="recommend-menu">
                    <a href="{{ route('jobs.index') }}" class="recommend-item {{ request()->routeIs('jobs.*') ? 'active' : '' }}">
                        <svg class="icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        หางาน / Part-time
                    </a>
                </div>
            </div>
            @endauth
            @auth
            {{-- Desktop nav links (ซ่อนบนมือถือ) --}}
            <nav class="navbar-links navbar-desktop">
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
                <span class="navbar-user">
                    <svg class="icon-sm" style="display:inline;margin-right:.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ auth()->user()->full_name }}
                </span>
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">ออก</button>
                </form>
            </nav>
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
        <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:.75rem;">
            <span id="notif-banner-icon" style="font-size:1rem;flex-shrink:0;">🔔</span>
            <span id="notif-banner-text" style="flex:1;"></span>
            <a id="notif-banner-link" href="#" style="color:#c7d2fe;font-weight:600;font-size:.8rem;flex-shrink:0;text-decoration:none;">ไปเลย →</a>
            <button onclick="document.getElementById('notif-banner').style.display='none'" style="background:none;border:none;color:rgba(255,255,255,.7);font-size:1.1rem;cursor:pointer;flex-shrink:0;line-height:1;">✕</button>
        </div>
    </div>
    @endif
    @endauth
    {{-- เนื้อหาหลัก --}}
    <div class="container" style="padding-top:1rem; padding-bottom:5rem;">
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
        <a href="{{ route('student.calendar') }}" class="bottom-nav-item {{ request()->routeIs('student.calendar') ? 'active' : '' }}">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span>ปฏิทิน</span>
        </a>
        <a href="{{ route('student.my') }}" class="bottom-nav-item {{ request()->routeIs('student.my') ? 'active' : '' }}" style="position:relative;">
            <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <span>ของฉัน</span>
            <span id="bottom-todo-badge" style="display:none;position:absolute;top:4px;right:calc(50% - 20px);min-width:16px;height:16px;border-radius:8px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:16px;text-align:center;padding:0 3px;"></span>
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

<script src="{{ config('socket.public_url') }}/socket.io/socket.io.js"></script>
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
    var dismissedKey = 'notif_dismissed_' + Date.now().toString().slice(0,-5);
    var webNotifAsked = false;

    function requestWebNotifPermission() {
        if (!webNotifAsked && 'Notification' in window && Notification.permission === 'default') {
            webNotifAsked = true;
            Notification.requestPermission();
        }
    }

    function showWebNotif(title, body, url) {
        if ('Notification' in window && Notification.permission === 'granted') {
            var n = new Notification(title, { body: body, icon: '/favicon.ico' });
            n.onclick = function() { window.location.href = url; n.close(); };
        }
    }

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

                // Badge count
                var urgentCount = alerts.filter(function(a) { return a.type === 'checkin_open' || a.type === 'checkin_soon'; }).length;
                var count = alerts.length;
                if (navBadge) { navBadge.textContent = count; navBadge.style.display = 'inline-block'; }
                if (botBadge) { botBadge.textContent = count; botBadge.style.display = 'inline-block'; }

                // Banner (แสดงเฉพาะ checkin alerts)
                var urgent = alerts.filter(function(a) { return a.type === 'checkin_open' || a.type === 'checkin_soon'; });
                if (urgent.length && banner) {
                    var first = urgent[0];
                    document.getElementById('notif-banner-icon').textContent = first.icon;
                    document.getElementById('notif-banner-text').textContent = first.title + ' — ' + first.body;
                    document.getElementById('notif-banner-link').href = first.url;
                    banner.style.display = 'block';
                    // Web push notification
                    requestWebNotifPermission();
                    showWebNotif(first.title, first.body, first.url);
                }
            })
            .catch(function() {});
    }

    // ดึงทันทีและทุก 5 นาที
    setTimeout(fetchNotifications, 2000);
    setInterval(fetchNotifications, 5 * 60 * 1000);
})();
</script>
@endif
@endauth

@auth
@if(!in_array(auth()->user()->role ?? 'student', ['admin','staff']))
{{-- ── Floating Chat Widget ── --}}
<div id="chatFloatWidget" style="position:fixed;bottom:5.5rem;right:1.1rem;z-index:8500;display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;">

    {{-- Panel --}}
    <div id="chatFloatPanel" style="display:none;width:330px;height:480px;background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.2);overflow:hidden;flex-direction:column;">

        {{-- Dynamic header (thread-list or chat) --}}
        <div id="cfHeader" style="background:#4f46e5;padding:.7rem 1rem;display:flex;align-items:center;gap:.5rem;flex-shrink:0;">
            <button id="cfBackBtn" onclick="cfBackToList()" style="display:none;background:none;border:none;color:#fff;font-size:1rem;cursor:pointer;padding:.1rem .3rem;line-height:1;opacity:.85;">←</button>
            <span id="cfHeaderTitle" style="color:#fff;font-weight:700;font-size:.88rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">💬 ข้อความของฉัน</span>
            <button onclick="closeChatWidget()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;line-height:1;padding:.1rem .3rem;opacity:.85;">✕</button>
        </div>

        {{-- VIEW 1: Thread list --}}
        <div id="cfViewList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;">
            <div id="cfListContent" style="flex:1;">
                <div style="padding:1.5rem;text-align:center;font-size:.85rem;color:#94a3b8;">กำลังโหลด...</div>
            </div>
        </div>

        {{-- VIEW 2: Chat history (hidden by default) --}}
        <div id="cfViewChat" style="display:none;flex-direction:column;flex:1;min-height:0;">
            {{-- Messages --}}
            <div id="cfChatWindow" style="flex:1;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:.45rem;background:#f8fafc;"></div>
            {{-- Typing indicator bar --}}
            <div id="cfTypingBar" style="display:none;padding:.4rem .75rem;background:#f8fafc;font-size:.72rem;color:#6366f1;">✏️ ผู้ดูแลกำลังพิมพ์...</div>
            {{-- Input --}}
            <div style="border-top:1px solid #e2e8f0;padding:.5rem .75rem;background:#fff;flex-shrink:0;">
                <div id="cfAttachPreview" style="display:none;gap:.3rem;flex-wrap:wrap;margin-bottom:.3rem;"></div>
                <form id="cfChatForm" enctype="multipart/form-data" style="display:flex;gap:.35rem;align-items:flex-end;">
                    @csrf
                    <label style="cursor:pointer;padding:.4rem .5rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem;line-height:1;flex-shrink:0;" title="แนบไฟล์">
                        📎<input type="file" id="cfFileInput" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
                    </label>
                    <textarea id="cfMsgInput" name="message" rows="1" placeholder="พิมพ์ข้อความ..." style="flex:1;resize:none;border:1px solid #e2e8f0;border-radius:8px;padding:.4rem .6rem;font-size:.82rem;line-height:1.4;outline:none;font-family:inherit;max-height:80px;overflow-y:auto;"></textarea>
                    <button type="submit" id="cfSendBtn" style="padding:.4rem .85rem;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:.82rem;cursor:pointer;font-weight:500;flex-shrink:0;">ส่ง</button>
                </form>
            </div>
        </div>

    </div>

    {{-- Floating button --}}
    <button id="chatFloatBtn" onclick="toggleChatWidget()"
        style="width:52px;height:52px;border-radius:50%;background:#4f46e5;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 18px rgba(79,70,229,.45);display:flex;align-items:center;justify-content:center;position:relative;transition:transform .15s;">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span id="chatFloatBadge" style="display:none;position:absolute;top:-3px;right:-3px;min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.65rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;border:2px solid #fff;"></span>
    </button>
</div>

<script>
(function () {
    var THREADS_URL = '{{ route("chat.threads") }}';
    var CSRF        = document.querySelector('meta[name="csrf-token"]').content;
    var USER_ID     = {{ auth()->id() }};
    var MY_PHOTO    = '{{ auth()->user()->profile_photo ? asset("storage/".auth()->user()->profile_photo) : "" }}';
    var MY_NAME     = '{{ auth()->user()->full_name ?? auth()->user()->name ?? "คุณ" }}';
    var STUDENT_ROOM = 'chat:student:' + USER_ID;
    var STUDENT_TOKEN = '{{ \App\Services\SocketService::roomToken("chat:student:" . auth()->id()) }}';

    var panelOpen    = false;
    var threads      = [];
    var currentJobId = null;
    var sock         = null;

    // ════════════════════════════════════════
    // Panel open / close
    // ════════════════════════════════════════
    window.toggleChatWidget = function () { panelOpen ? closeChatWidget() : openChatWidget(); };
    window.closeChatWidget  = function () {
        panelOpen = false;
        document.getElementById('chatFloatPanel').style.display = 'none';
        document.getElementById('chatFloatBtn').style.transform = '';
    };
    function openChatWidget() {
        panelOpen = true;
        document.getElementById('chatFloatPanel').style.display = 'flex';
        document.getElementById('chatFloatBtn').style.transform = 'scale(1.1)';
        showListView();
        loadThreads();
    }

    // ════════════════════════════════════════
    // View switching
    // ════════════════════════════════════════
    function showListView() {
        currentJobId = null;
        document.getElementById('cfViewList').style.display = 'flex';
        document.getElementById('cfViewChat').style.display = 'none';
        document.getElementById('cfBackBtn').style.display  = 'none';
        document.getElementById('cfHeaderTitle').textContent = '💬 ข้อความของฉัน';
        if (sock) sock.emit('leave', 'chat:thread:__prev__');
    }
    window.cfBackToList = function () { showListView(); loadThreads(); };

    function showChatView(jobId, jobTitle) {
        currentJobId = jobId;
        document.getElementById('cfViewList').style.display = 'none';
        document.getElementById('cfViewChat').style.display = 'flex';
        document.getElementById('cfBackBtn').style.display  = 'inline-block';
        document.getElementById('cfHeaderTitle').textContent = jobTitle;
        updateHeaderOnline();
        var thread = threads.find(function(t){ return t.job_id == jobId; });
        if (sock && thread) sock.emit('join', { room: thread.thread_room, token: thread.thread_token });
        loadMessages(jobId);
        // Mark read
        fetch('/jobs/' + jobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
        // Clear unread for this thread locally
        var idx = threads.findIndex(function(t){ return t.job_id == jobId; });
        if (idx >= 0) { threads[idx].unread = 0; recalcBadge(); }
        // Dispatch event for admin online status check
        window.dispatchEvent(new CustomEvent('cfChatOpened', { detail: { jobId: jobId } }));
    }

    // ════════════════════════════════════════
    // Load threads
    // ════════════════════════════════════════
    function loadThreads() {
        fetch(THREADS_URL, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                threads = data.threads || [];
                renderThreads();
                updateBadge(data.total_unread || 0);
            })
            .catch(function() {
                document.getElementById('cfListContent').innerHTML =
                    '<div style="padding:1rem;text-align:center;font-size:.82rem;color:#94a3b8;">ไม่สามารถโหลดได้</div>';
            });
    }

    function renderThreads() {
        var el = document.getElementById('cfListContent');
        if (!threads.length) {
            el.innerHTML = '<div style="padding:2rem 1rem;text-align:center;font-size:.83rem;color:#94a3b8;">ยังไม่มีข้อความ</div>';
            return;
        }
        el.innerHTML = threads.map(function(t) {
            var isUnread   = t.unread > 0;
            var isMine     = t.last_sender_role === 'student';
            var readStatus = isMine
                ? (t.last_read_at
                    ? '<span style="color:#6366f1;font-size:.68rem;" title="อ่านแล้ว">✓✓</span>'
                    : '<span style="color:#94a3b8;font-size:.68rem;" title="ส่งแล้ว">✓</span>')
                : '';
            var badge   = isUnread ? '<span style="min-width:18px;height:18px;border-radius:9px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:700;line-height:18px;text-align:center;padding:0 4px;">' + t.unread + '</span>' : '';
            var preview = t.last_message ? (t.last_message.length > 32 ? t.last_message.slice(0,32)+'…' : t.last_message) : '📎 ไฟล์แนบ';
            return '<div onclick="cfOpenThread(' + t.job_id + ',\'' + escJs(t.job_title) + '\')" '
                + 'style="display:flex;align-items:center;gap:.65rem;padding:.65rem .9rem;border-bottom:1px solid #f1f5f9;cursor:pointer;background:' + (isUnread?'#faf5ff':'#fff') + ';"'
                + ' onmouseover="this.style.background=\'#f3f4f6\'" onmouseout="this.style.background=\'' + (isUnread?'#faf5ff':'#fff') + '\'">'
                + '<div style="width:34px;height:34px;border-radius:50%;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;flex-shrink:0;">' + escHtml(t.job_title.charAt(0).toUpperCase()) + '</div>'
                + '<div style="flex:1;min-width:0;">'
                + '<div style="font-size:.82rem;font-weight:' + (isUnread?'700':'500') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#1e293b;">' + escHtml(t.job_title) + '</div>'
                + '<div style="display:flex;align-items:center;gap:.2rem;margin-top:.08rem;">'
                + (isMine ? '<span style="font-size:.7rem;color:#94a3b8;flex-shrink:0;">คุณ:</span>' : '')
                + readStatus
                + '<span style="font-size:.7rem;color:' + (isUnread?'#1e293b':'#64748b') + ';font-weight:' + (isUnread?'600':'400') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(preview) + '</span>'
                + '</div></div>'
                + '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:.2rem;flex-shrink:0;">' + badge + '<span style="font-size:.62rem;color:#94a3b8;">' + (t.last_time_human||'') + '</span></div>'
                + '</div>';
        }).join('');
    }

    window.cfOpenThread = function(jobId, jobTitle) { showChatView(jobId, jobTitle); };

    // ════════════════════════════════════════
    // Load & render chat messages
    // ════════════════════════════════════════
    function loadMessages(jobId) {
        var win = document.getElementById('cfChatWindow');
        win.innerHTML = '<div style="padding:1.5rem;text-align:center;font-size:.82rem;color:#94a3b8;">กำลังโหลด...</div>';
        fetch('/jobs/' + jobId + '/chat/messages', { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                win.innerHTML = '';
                var msgs = data.messages || [];
                if (!msgs.length) {
                    win.innerHTML = '<div style="margin:auto;font-size:.82rem;color:#94a3b8;text-align:center;">ยังไม่มีข้อความ</div>';
                    return;
                }
                msgs.forEach(function(m) {
                    var el = buildBubble(m);
                    if (el && el.parentNode !== win) win.appendChild(el);
                });
                win.scrollTop = win.scrollHeight;
            })
            .catch(function() {
                win.innerHTML = '<div style="padding:1rem;text-align:center;font-size:.8rem;color:#94a3b8;">โหลดข้อความไม่สำเร็จ</div>';
            });
    }

    function buildBubble(msg) {
        var existing = document.getElementById('cfm-' + msg.id);
        if (existing) return existing; // Return existing element instead of creating duplicate

        var mine  = msg.sender_id == USER_ID;
        var label = mine ? 'คุณ' : (msg.sender_name || 'ผู้ดูแล');
        var photo = msg.sender_photo || null;

        var row = document.createElement('div');
        row.id  = 'cfm-' + msg.id;
        row.style.cssText = 'display:flex;flex-direction:' + (mine?'row-reverse':'row') + ';align-items:flex-end;gap:.3rem;';

        // Avatar with online dot wrapper for admin
        var avWrap = document.createElement('div');
        avWrap.style.cssText = 'position:relative;flex-shrink:0;';
        
        var av = document.createElement(photo ? 'img' : 'div');
        if (photo) { av.src = photo; av.alt = label; av.style.cssText = 'width:24px;height:24px;border-radius:50%;object-fit:cover;'; }
        else { av.textContent = label.charAt(0).toUpperCase(); av.style.cssText = 'width:24px;height:24px;border-radius:50%;background:' + (mine?'#4f46e5':'#64748b') + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:700;'; }
        avWrap.appendChild(av);
        
        // Online dot for admin messages (not mine = from admin)
        if (!mine) {
            var onlineDot = document.createElement('span');
            onlineDot.id = 'admin-dot-' + msg.id;
            onlineDot.className = 'admin-online-dot';
            onlineDot.style.cssText = 'display:none;position:absolute;bottom:0;right:0;width:10px;height:10px;background:#10b981;border-radius:50%;border:2px solid #f8fafc;';
            avWrap.appendChild(onlineDot);
        }
        
        row.appendChild(avWrap);

        // Column
        var col = document.createElement('div');
        col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (mine?'flex-end':'flex-start') + ';max-width:75%;';

        var nameEl = document.createElement('span');
        nameEl.style.cssText = 'font-size:.63rem;color:#94a3b8;margin-bottom:.1rem;';
        nameEl.textContent = label;
        col.appendChild(nameEl);

        var bubble = document.createElement('div');
        bubble.style.cssText = 'padding:.42rem .72rem;border-radius:' + (mine?'14px 3px 14px 14px':'3px 14px 14px 14px') + ';background:' + (mine?'#4f46e5':'#fff') + ';color:' + (mine?'#fff':'#1e293b') + ';font-size:.82rem;box-shadow:0 1px 3px rgba(0,0,0,.07);word-break:break-word;';

        if (msg.message) { var p = document.createElement('p'); p.style.margin='0'; p.textContent = msg.message; bubble.appendChild(p); }
        (msg.attachments||[]).forEach(function(att) {
            var isImg = (att.mime_type||'').startsWith('image/');
            if (isImg) {
                var img = document.createElement('img');
                img.src = att.url; img.alt = att.original_name;
                img.style.cssText = 'max-width:100%;border-radius:6px;margin-top:.25rem;display:block;cursor:pointer;';
                img.onclick = function(){ window.open(att.url,'_blank'); };
                bubble.appendChild(img);
            } else {
                var a = document.createElement('a');
                a.href = att.url; a.target='_blank'; a.download = att.original_name;
                a.style.cssText = 'display:flex;align-items:center;gap:.3rem;margin-top:.25rem;color:' + (mine?'#c7d2fe':'#4f46e5') + ';font-size:.75rem;text-decoration:none;';
                a.innerHTML = '📎 ' + escHtml(att.original_name);
                bubble.appendChild(a);
            }
        });
        col.appendChild(bubble);

        // Read/sent indicator (only for own messages)
        if (mine) {
            var st = document.createElement('span');
            st.id = 'cfm-status-' + msg.id;
            st.style.cssText = 'font-size:.6rem;margin-top:.08rem;';
            if (msg.read_at) {
                st.style.color='#6366f1';
                var dt = new Date(msg.read_at);
                st.textContent = '✓✓ เห็นเมื่อ ' + dt.toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
            } else {
                st.style.color='#94a3b8';
                st.textContent = '✓ ส่งแล้ว';
            }
            col.appendChild(st);
        } else {
            var tm = document.createElement('span');
            tm.style.cssText = 'font-size:.6rem;color:#94a3b8;margin-top:.08rem;';
            tm.textContent = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'}) : '';
            col.appendChild(tm);
        }

        row.appendChild(col);
        return row;
    }

    // ════════════════════════════════════════
    // Send message
    // ════════════════════════════════════════
    document.getElementById('cfChatForm').addEventListener('submit', function(e) {
        e.preventDefault();
        if (!currentJobId) return;
        var text  = document.getElementById('cfMsgInput').value.trim();
        var files = document.getElementById('cfFileInput').files;
        if (!text && !files.length) return;

        var btn = document.getElementById('cfSendBtn');
        btn.disabled = true; btn.textContent = '...';

        // Optimistic bubble
        var noMsg = document.getElementById('cfNoMsg');
        if (noMsg) noMsg.remove();
        var optimistic = { id: 'tmp-'+Date.now(), sender_id: USER_ID, sender_role:'student',
            sender_name: MY_NAME, sender_photo: MY_PHOTO||null,
            message: text, attachments:[], read_at: null,
            created_at: new Date().toISOString() };
        var win = document.getElementById('cfChatWindow');
        win.appendChild(buildBubble(optimistic));
        win.scrollTop = win.scrollHeight;

        var fd = new FormData(document.getElementById('cfChatForm'));
        fetch('/jobs/' + currentJobId + '/chat', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF}, body: fd })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                var tmp = document.getElementById('cfm-tmp-'+optimistic.id.split('-')[1]);
                if (tmp && data.message) { var real = buildBubble(data.message); tmp.replaceWith(real); }
                // Update thread preview
                var idx = threads.findIndex(function(t){ return t.job_id == currentJobId; });
                if (idx >= 0) { threads[idx].last_message = text; threads[idx].last_sender_role = 'student'; threads[idx].last_read_at = null; threads[idx].last_time_human = 'เมื่อกี้'; }
            })
            .catch(function(){})
            .finally(function(){
                btn.disabled = false; btn.textContent = 'ส่ง';
                document.getElementById('cfMsgInput').value = '';
                document.getElementById('cfFileInput').value = '';
                document.getElementById('cfAttachPreview').innerHTML = '';
                document.getElementById('cfAttachPreview').style.display = 'none';
            });
    });

    // Auto-grow textarea
    document.getElementById('cfMsgInput').addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });
    document.getElementById('cfMsgInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); document.getElementById('cfSendBtn').click(); }
    });

    // Attach file preview
    document.getElementById('cfFileInput').addEventListener('change', function() {
        var prev = document.getElementById('cfAttachPreview');
        prev.innerHTML = '';
        if (!this.files.length) { prev.style.display='none'; return; }
        prev.style.display = 'flex';
        Array.from(this.files).forEach(function(f) {
            var chip = document.createElement('span');
            chip.style.cssText = 'padding:.18rem .5rem;background:#e0e7ff;border-radius:12px;font-size:.7rem;color:#4f46e5;';
            chip.textContent = f.name.length > 18 ? f.name.slice(0,18)+'...' : f.name;
            prev.appendChild(chip);
        });
    });

    // ════════════════════════════════════════
    // Badge helpers
    // ════════════════════════════════════════
    function updateBadge(count) {
        var badge = document.getElementById('chatFloatBadge');
        if (count > 0) { badge.textContent = count > 99 ? '99+' : count; badge.style.display = 'inline-block'; }
        else           { badge.style.display = 'none'; }
    }
    function recalcBadge() {
        updateBadge(threads.reduce(function(s,t){ return s+(t.unread||0); }, 0));
    }

    // ════════════════════════════════════════
    // Initial badge load
    // ════════════════════════════════════════
    fetch(THREADS_URL, { headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'} })
        .then(function(r){ return r.json(); })
        .then(function(data){ threads = data.threads||[]; updateBadge(data.total_unread||0); })
        .catch(function(){});

    // ════════════════════════════════════════
    // Socket.io
    // ════════════════════════════════════════
    if (typeof io !== 'undefined') {
        sock = io('{{ config("socket.public_url") }}', { transports: ['websocket','polling'] });
        sock.emit('join', { room: STUDENT_ROOM, token: STUDENT_TOKEN });

        sock.on('chat:message', function(msg) {
            var idx = threads.findIndex(function(t){ return t.job_id == msg.job_id; });

            if (msg.sender_role === 'admin') {
                // ── Admin reply ──
                if (idx >= 0) {
                    if (currentJobId != msg.job_id) threads[idx].unread = (threads[idx].unread||0) + 1;
                    threads[idx].last_message = msg.message; threads[idx].last_sender_role = 'admin';
                    threads[idx].last_read_at = null; threads[idx].last_time_human = 'เมื่อกี้';
                    threads.unshift(threads.splice(idx,1)[0]);
                } else {
                    fetch(THREADS_URL, {headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}})
                        .then(function(r){return r.json();})
                        .then(function(d){ threads=d.threads||[]; updateBadge(d.total_unread||0); if(panelOpen) renderThreads(); });
                    return;
                }
                recalcBadge();

            } else if (msg.sender_role === 'student') {
                // ── Student's own message (sent from popup or full chat page) ──
                if (idx >= 0) {
                    threads[idx].last_message = msg.message || (msg.attachments&&msg.attachments.length ? '📎 ไฟล์แนบ' : '');
                    threads[idx].last_sender_role = 'student';
                    threads[idx].last_read_at = null;
                    threads[idx].last_time_human = 'เมื่อกี้';
                    threads.unshift(threads.splice(idx,1)[0]);
                } else {
                    // New thread not yet in list — pull from server to get job_title
                    fetch(THREADS_URL, {headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}})
                        .then(function(r){return r.json();})
                        .then(function(d){ threads=d.threads||[]; updateBadge(d.total_unread||0); if(panelOpen) renderThreads(); });
                    return;
                }
            }

            // ── If chat view for this job is open, append bubble (skip if already rendered) ──
            if (currentJobId == msg.job_id) {
                var win = document.getElementById('cfChatWindow');
                if (win && !document.getElementById('cfm-' + msg.id)) {
                    win.appendChild(buildBubble(msg));
                    win.scrollTop = win.scrollHeight;
                }
                if (msg.sender_role === 'admin') {
                    fetch('/jobs/'+msg.job_id+'/chat/read', {method:'POST', headers:{'X-CSRF-TOKEN':CSRF}});
                }
            } else if (panelOpen && document.getElementById('cfViewList').style.display !== 'none') {
                renderThreads();
            }
        });

        sock.on('chat:read', function(data) {
            var idx = threads.findIndex(function(t){ return t.job_id == data.job_id; });
            if (idx >= 0) { threads[idx].unread = 0; recalcBadge(); }
            if (panelOpen && document.getElementById('cfViewList').style.display !== 'none') renderThreads();
            
            // Update read status on own message bubbles in current chat view
            if (currentJobId == data.job_id) {
                document.querySelectorAll('[id^="cfm-status-"]').forEach(function(st) {
                    if (st.textContent.includes('ส่งแล้ว')) {
                        st.textContent = '✓✓ เห็นเมื่อ ' + new Date().toLocaleTimeString('th-TH',{hour:'2-digit',minute:'2-digit'});
                        st.style.color = '#6366f1';
                    }
                });
            }
        });

        // Typing indicator from admin
        sock.on('typing', function(d) {
            if (currentJobId && d.toRoom === 'chat:admin:' + currentJobId) {
                var bar = document.getElementById('cfTypingBar');
                if (bar) {
                    bar.style.display = 'block';
                    clearTimeout(window.cfTypingTimer);
                    window.cfTypingTimer = setTimeout(function(){ bar.style.display='none'; }, 3000);
                }
            }
        });

        // Online status from admin (user:online:{userId})
        sock.on('user:online', function(d) {
            // Could be used to update UI elsewhere if needed
        });
    }

    // Ping every 60s to keep last_seen fresh
    setInterval(function() {
        fetch('/user/ping', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF} }).catch(function(){});
    }, 60000);

    // Typing emit
    document.getElementById('cfMsgInput').addEventListener('input', function() {
        if (!currentJobId) return;
        var thread = threads.find(function(t){ return t.job_id == currentJobId; });
        if (!thread) return;
        sock.emit('typing', {
            toRoom: thread.typing_room,
            token: thread.typing_token,
            userId: USER_ID,
            name: MY_NAME
        });
    });

    // Online dot in header when chat view is open
    function updateHeaderOnline() {
        if (!currentJobId) return;
        // For now, assume admin is online when chat is open; could be refined with /users/{id}/status
        var header = document.getElementById('cfHeaderTitle');
        if (header && !header.querySelector('.online-dot')) {
            var dot = document.createElement('span');
            dot.className = 'online-dot';
            dot.style.cssText = 'display:inline-block;width:8px;height:8px;background:#10b981;border-radius:50%;margin-left:.4rem;vertical-align:middle;box-shadow:0 0 0 2px #fff;';
            header.appendChild(dot);
        }
    }

    // Poll admin online status every 30s
    function updateAdminOnlineDots() {
        if (!currentJobId) return;
        fetch('/jobs/' + currentJobId + '/admin-online', {headers:{'Accept':'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(d) {
                var show = d.is_online ? 'inline-block' : 'none';
                document.querySelectorAll('.admin-online-dot').forEach(function(el) {
                    el.style.display = show;
                });
            })
            .catch(function(){});
    }
    setInterval(updateAdminOnlineDots, 30000);
    // Initial check when chat view opens
    window.addEventListener('cfChatOpened', updateAdminOnlineDots);

    // ════════════════════════════════════════
    // Click outside to close
    // ════════════════════════════════════════
    document.addEventListener('click', function(e) {
        if (!panelOpen) return;
        var widget = document.getElementById('chatFloatWidget');
        if (widget && !widget.contains(e.target)) closeChatWidget();
    });

    // ════════════════════════════════════════
    // Utilities
    // ════════════════════════════════════════
    function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escJs(s)   { return String(s).replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
})();
</script>
@endif
@endauth
</body>
</html>
