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
        setInterval(fetchNotifications, 5 * 60 * 1000);
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
                <span id="cfHeaderTitle" style="color:#fff;font-weight:700;font-size:.88rem;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">💬 ข้อความของฉัน</span>
                <button onclick="closeChatWidget()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;line-height:1;padding:.1rem .3rem;opacity:.85;">✕</button>
            </div>
            <div id="cfViewList" style="flex:1;overflow-y:auto;display:flex;flex-direction:column;">
                <div id="cfListContent" style="flex:1;">
                    <div style="padding:1.5rem;text-align:center;font-size:.85rem;color:#94a3b8;">กำลังโหลด...</div>
                </div>
            </div>
            <div id="cfViewChat" style="display:none;flex-direction:column;flex:1;min-height:0;">
                <div id="cfChatWindow" style="flex:1;overflow-y:auto;padding:.75rem;display:flex;flex-direction:column;gap:.45rem;background:#f8fafc;"></div>
                <div id="cfTypingBar" style="display:none;padding:.4rem .75rem;background:#f8fafc;font-size:.72rem;color:#6366f1;">✏️ ผู้ดูแลกำลังพิมพ์...</div>
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
        function openChatWidget() {
            panelOpen = true;
            document.getElementById('chatFloatPanel').style.display = 'flex';
            document.getElementById('chatFloatBtn').style.transform = 'scale(1.1)';
            showListView();
            loadThreads();
        }

        function showListView() {
            currentJobId = null;
            document.getElementById('cfViewList').style.display = 'flex';
            document.getElementById('cfViewChat').style.display = 'none';
            document.getElementById('cfBackBtn').style.display = 'none';
            document.getElementById('cfHeaderTitle').textContent = '💬 ข้อความของฉัน';
        }
        window.cfBackToList = function () { showListView(); loadThreads(); };

        function showChatView(jobId, jobTitle) {
            currentJobId = jobId;
            document.getElementById('cfViewList').style.display = 'none';
            document.getElementById('cfViewChat').style.display = 'flex';
            document.getElementById('cfBackBtn').style.display = 'inline-block';
            document.getElementById('cfHeaderTitle').textContent = jobTitle;
            loadMessages(jobId);
            fetch('/jobs/' + jobId + '/chat/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } });
            var idx = threads.findIndex(function(t){ return t.job_id == jobId; });
            if (idx >= 0) { threads[idx].unread = 0; recalcBadge(); }
        }

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
                var preview = t.last_message ? (t.last_message.length > 32 ? t.last_message.slice(0,32)+'…' : t.last_message) : '📎 ไฟล์แนบ';
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
            var mine = msg.sender_role === 'student';
            var row = document.createElement('div');
            row.style.cssText = 'display:flex;flex-direction:' + (mine?'row-reverse':'row') + ';align-items:flex-end;gap:.3rem;margin-bottom:.2rem;';
            
            var col = document.createElement('div');
            col.style.cssText = 'display:flex;flex-direction:column;align-items:' + (mine?'flex-end':'flex-start') + ';max-width:75%;';
            
            var bubble = document.createElement('div');
            bubble.style.cssText = 'padding:.45rem .75rem;border-radius:' + (mine?'14px 4px 14px 14px':'4px 14px 14px 14px') + ';background:' + (mine?'#4f46e5':'#fff') + ';color:' + (mine?'#fff':'#1e293b') + ';font-size:.82rem;box-shadow:0 1px 2px rgba(0,0,0,.08);word-break:break-word;';
            
            if (msg.message) {
                var p = document.createElement('p');
                p.style.margin = '0';
                p.textContent = msg.message;
                bubble.appendChild(p);
            }
            
            if (msg.attachments && msg.attachments.length) {
                msg.attachments.forEach(function(a) {
                    var attDiv = document.createElement('div');
                    attDiv.style.marginTop = '.3rem';
                    if ((a.mime_type || '').indexOf('image/') === 0) {
                        var img = document.createElement('img');
                        img.src = a.url;
                        img.style.cssText = 'max-width:100%;border-radius:8px;display:block;cursor:pointer;';
                        img.onclick = function() { window.open(a.url, '_blank'); };
                        attDiv.appendChild(img);
                    } else {
                        var link = document.createElement('a');
                        link.href = a.url;
                        link.target = '_blank';
                        link.style.cssText = 'font-size:.75rem;text-decoration:none;display:flex;align-items:center;gap:.2rem;color:' + (mine?'#c7d2fe':'#4f46e5');
                        link.innerHTML = '📎 ' + a.original_name;
                        attDiv.appendChild(link);
                    }
                    bubble.appendChild(attDiv);
                });
            }
            
            col.appendChild(bubble);
            row.appendChild(col);
            return row;
        }

        document.getElementById('cfChatForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!currentJobId) return;
            var form = this;
            var msgInput = document.getElementById('cfMsgInput');
            var text = msgInput.value.trim();
            var fd = new FormData(form);
            
            // มั่นใจว่ามี message ส่งไปแน่นอน
            if (!fd.has('message')) fd.append('message', text);

            var btn = document.getElementById('cfSendBtn');
            btn.disabled = true;

            fetch('/jobs/' + currentJobId + '/chat', { 
                method: 'POST', 
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, 
                body: fd 
            })
                .then(function(r) {
                    if (!r.ok) {
                        return r.json().then(function(err) { throw err; });
                    }
                    return r.json();
                })
                .then(function(data) {
                    msgInput.value = '';
                    loadMessages(currentJobId);
                    btn.disabled = false;
                })
                .catch(function(err) {
                    console.error('Chat Error:', err);
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

        // Laravel Echo
        if (window.Echo) {
            // ฟังจากช่องส่วนตัวของนักศึกษา (สำหรับแจ้งเตือนรวม)
            window.Echo.private('chat.student.' + USER_ID)
                .listen('ChatMessageEvent', function(e) {
                    if (currentJobId == e.job_id) { loadMessages(currentJobId); }
                    else { loadThreads(); }
                });
        }
    })();
    </script>
    @endif
    @endauth
</body>
</html>
