<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - ระบบอยู่ระหว่างปรับปรุง</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        ::selection { background: #ea580c; color: #fff; }
        :root {
            --bg: #09090b; --surface: #18181b; --border: #27272a;
            --text: #f4f4f5; --sub: #a1a1aa; --muted: #71717a;
            --orange: #f97316; --orange-hover: #fb923c;
            --icon-bg: rgba(234,88,12,0.12); --icon-border: rgba(234,88,12,0.25);
            --btn-bg: transparent; --btn-border: #3f3f46; --btn-hover: #27272a;
            --divider: #27272a;
        }
        html[data-theme="light"] {
            --bg: #f8fafc; --surface: #fff; --border: #e2e8f0;
            --text: #0f172a; --sub: #475569; --muted: #94a3b8;
            --orange: #ea580c; --orange-hover: #c2410c;
            --icon-bg: #fff7ed; --icon-border: #fed7aa;
            --btn-bg: #fff; --btn-border: #cbd5e1; --btn-hover: #f8fafc;
            --divider: #e2e8f0;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 1.5rem;
        }
        .card {
            max-width: 420px; width: 100%; text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 20px; padding: 2.5rem 2rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        html[data-theme="dark"] .card { box-shadow: 0 8px 40px rgba(0,0,0,0.4); }
        .svg-wrap { width: 130px; height: 120px; margin: 0 auto 1.5rem; }
        .code { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--orange); margin-bottom: 0.5rem; font-family: monospace; }
        h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
        .desc { color: var(--sub); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.75rem; }
        .eta-card {
            display: flex; align-items: center; gap: 10px;
            background: var(--icon-bg); border: 1px solid var(--icon-border);
            border-radius: 12px; padding: 0.75rem 1rem;
            margin-bottom: 1.75rem; text-align: left;
        }
        .eta-icon { flex-shrink: 0; width: 36px; height: 36px; background: var(--icon-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--orange); }
        .eta-label { font-size: 0.75rem; color: var(--muted); }
        .eta-value { font-size: 0.85rem; font-weight: 700; color: var(--text); }
        .actions { display: flex; flex-direction: column; gap: 0.625rem; }
        @media (min-width: 480px) { .actions { flex-direction: row; justify-content: center; } }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 0.6rem 1.25rem; border-radius: 10px;
            font-size: 0.875rem; font-weight: 600; text-decoration: none;
            cursor: pointer; transition: all 0.15s ease; border: none;
            font-family: inherit; min-height: 44px;
        }
        .btn-primary { background: var(--orange); color: #fff; }
        .btn-primary:hover { background: var(--orange-hover); transform: translateY(-1px); }
        .btn-secondary { background: var(--btn-bg); color: var(--text); border: 1px solid var(--btn-border); }
        .btn-secondary:hover { background: var(--btn-hover); }
        .divider { border: none; border-top: 1px solid var(--divider); margin: 1.25rem 0; }
        .footer { font-size: 0.75rem; color: var(--muted); }
        .theme-toggle {
            position: fixed; top: 1rem; right: 1rem; width: 36px; height: 36px;
            border-radius: 8px; border: 1px solid var(--border); background: var(--surface);
            color: var(--muted); cursor: pointer; display: flex; align-items: center;
            justify-content: center; transition: all 0.2s;
        }
        .theme-toggle:hover { color: var(--orange); border-color: var(--orange); }
        /* SVG Animations */
        @keyframes gear-cw { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes gear-ccw { from { transform: rotate(0deg); } to { transform: rotate(-360deg); } }
        @keyframes wrench-turn { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(-20deg); } }
        @keyframes bolt-pulse { 0%, 100% { opacity: 0.3; } 50% { opacity: 0.8; } }
        .gear-cw { animation: gear-cw 8s linear infinite; transform-origin: center; }
        .gear-ccw { animation: gear-ccw 5s linear infinite; transform-origin: center; }
        .wrench-turn { animation: wrench-turn 3s ease-in-out infinite; transform-origin: 75% 25%; }
        .bolt-pulse { animation: bolt-pulse 2s ease-in-out infinite; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <svg class="sun-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <svg class="moon-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
    </button>

    <div class="card">
        <div class="svg-wrap">
            <svg viewBox="0 0 130 120" fill="none" xmlns="http://www.w3.org/2000/svg" width="130" height="120">
                <!-- Glow -->
                <circle cx="60" cy="55" r="42" fill="#ea580c" opacity="0.06"/>
                <!-- Main gear -->
                <g class="gear-cw" style="transform-origin: 50px 55px;">
                    <circle cx="50" cy="55" r="24" fill="none" stroke="var(--orange)" stroke-width="3"/>
                    <circle cx="50" cy="55" r="9" fill="var(--orange)" opacity="0.15"/>
                    <circle cx="50" cy="55" r="4" fill="var(--orange)"/>
                    <rect x="47" y="27" width="6" height="8" rx="2" fill="var(--orange)"/>
                    <rect x="47" y="75" width="6" height="8" rx="2" fill="var(--orange)"/>
                    <rect x="22" y="52" width="8" height="6" rx="2" fill="var(--orange)"/>
                    <rect x="70" y="52" width="8" height="6" rx="2" fill="var(--orange)"/>
                    <rect x="29" y="33" width="6" height="8" rx="2" fill="var(--orange)" transform="rotate(-45 32 37)"/>
                    <rect x="65" y="69" width="6" height="8" rx="2" fill="var(--orange)" transform="rotate(-45 68 73)"/>
                    <rect x="63" y="33" width="8" height="6" rx="2" fill="var(--orange)" transform="rotate(45 67 36)"/>
                    <rect x="22" y="69" width="8" height="6" rx="2" fill="var(--orange)" transform="rotate(45 26 72)"/>
                </g>
                <!-- Small gear -->
                <g class="gear-ccw" style="transform-origin: 90px 32px;">
                    <circle cx="90" cy="32" r="14" fill="none" stroke="var(--orange)" stroke-width="2.5"/>
                    <circle cx="90" cy="32" r="5" fill="var(--orange)" opacity="0.2"/>
                    <circle cx="90" cy="32" r="2.5" fill="var(--orange)"/>
                    <rect x="88" y="16" width="4" height="6" rx="2" fill="var(--orange)"/>
                    <rect x="88" y="42" width="4" height="6" rx="2" fill="var(--orange)"/>
                    <rect x="74" y="30" width="6" height="4" rx="2" fill="var(--orange)"/>
                    <rect x="100" y="30" width="6" height="4" rx="2" fill="var(--orange)"/>
                </g>
                <!-- Wrench -->
                <g class="wrench-turn">
                    <path d="M80 65 L105 40" stroke="var(--orange)" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="108" cy="37" r="6" fill="none" stroke="var(--orange)" stroke-width="2.5"/>
                    <path d="M105 34 L111 34 M105 40 L111 40" stroke="var(--orange)" stroke-width="2" stroke-linecap="round"/>
                    <rect x="74" y="63" width="12" height="8" rx="3" fill="var(--orange)" opacity="0.3"/>
                </g>
                <!-- Progress dots -->
                <circle cx="30" cy="95" r="3" fill="var(--orange)" opacity="0.3" class="bolt-pulse" style="animation-delay:0s"/>
                <circle cx="45" cy="95" r="3" fill="var(--orange)" opacity="0.3" class="bolt-pulse" style="animation-delay:0.5s"/>
                <circle cx="60" cy="95" r="3" fill="var(--orange)" opacity="0.3" class="bolt-pulse" style="animation-delay:1s"/>
                <circle cx="75" cy="95" r="3" fill="var(--orange)" opacity="0.3" class="bolt-pulse" style="animation-delay:1.5s"/>
                <circle cx="90" cy="95" r="3" fill="var(--orange)" opacity="0.3" class="bolt-pulse" style="animation-delay:2s"/>
            </svg>
        </div>
        <div class="code">Error 503 — Maintenance</div>
        <h1>ระบบอยู่ระหว่างปรับปรุง</h1>
        <p class="desc">เราขออภัยในความไม่สะดวก ทีมงานกำลังปรับปรุงระบบให้ดีขึ้น</p>
        <div class="eta-card">
            <div class="eta-icon">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <div class="eta-label">คาดว่าระบบจะกลับมาใน</div>
                <div class="eta-value">ไม่นานนี้ — กรุณาลองใหม่</div>
            </div>
        </div>
        <div class="actions">
            <button onclick="location.reload()" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                ลองใหม่อีกครั้ง
            </button>
            <a href="mailto:support@uniactivity.com" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                ติดต่อทีมงาน
            </a>
        </div>
        <hr class="divider">
        <div class="footer">UniActivity &mdash; ระบบกิจกรรมนักศึกษา</div>
    </div>
    <script>
        // Auto-reload every 30 seconds
        setTimeout(() => location.reload(), 30000);
        function toggleTheme() {
            const h = document.documentElement;
            const d = h.getAttribute('data-theme') === 'dark';
            h.setAttribute('data-theme', d ? 'light' : 'dark');
            document.querySelector('.sun-icon').style.display = d ? 'block' : 'none';
            document.querySelector('.moon-icon').style.display = d ? 'none' : 'block';
        }
    </script>
</body>
</html>
