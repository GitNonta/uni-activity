<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - หมดเวลาการเชื่อมต่อ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        ::selection { background: #ea580c; color: #fff; }
        :root {
            --bg: #f8fafc; --surface: #fff; --border: #e2e8f0;
            --text: #0f172a; --sub: #475569; --muted: #94a3b8;
            --orange: #ea580c; --orange-hover: #c2410c;
            --icon-bg: #fff7ed; --icon-border: #fed7aa;
            --btn-bg: #fff; --btn-border: #cbd5e1; --btn-hover: #f8fafc;
            --divider: #e2e8f0;
        }
        html[data-theme="dark"] {
            --bg: #09090b; --surface: #18181b; --border: #27272a;
            --text: #f4f4f5; --sub: #a1a1aa; --muted: #71717a;
            --orange: #f97316; --orange-hover: #fb923c;
            --icon-bg: rgba(234,88,12,0.12); --icon-border: rgba(234,88,12,0.25);
            --btn-bg: transparent; --btn-border: #3f3f46; --btn-hover: #27272a;
            --divider: #27272a;
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
        .svg-wrap { width: 120px; height: 120px; margin: 0 auto 1.5rem; }
        .code { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--orange); margin-bottom: 0.5rem; font-family: monospace; }
        h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
        .desc { color: var(--sub); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.75rem; }
        .countdown { display: inline-flex; align-items: center; gap: 6px; background: var(--icon-bg); border: 1px solid var(--icon-border); border-radius: 10px; padding: 0.5rem 1rem; margin-bottom: 1.75rem; font-size: 0.875rem; font-weight: 600; color: var(--orange); }
        .countdown-num { font-size: 1.25rem; font-weight: 800; font-variant-numeric: tabular-nums; }
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
        @keyframes sand-fall { 0% { transform: translateY(-10px); opacity: 0; } 20% { opacity: 1; } 80% { opacity: 1; } 100% { transform: translateY(14px); opacity: 0; } }
        @keyframes glow-pulse { 0%, 100% { filter: drop-shadow(0 0 6px rgba(234,88,12,0.2)); } 50% { filter: drop-shadow(0 0 16px rgba(234,88,12,0.4)); } }
        .sand-grain { animation: sand-fall 2s ease-in infinite; }
        .glow { animation: glow-pulse 3s ease-in-out infinite; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <svg class="sun-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <svg class="moon-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
    </button>

    <div class="card">
        <div class="svg-wrap">
            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" width="120" height="120">
                <!-- Glow -->
                <circle cx="60" cy="60" r="45" fill="#ea580c" opacity="0.08" class="glow"/>
                <!-- Hourglass frame -->
                <g class="glow">
                    <!-- Top bar -->
                    <rect x="35" y="18" width="50" height="5" rx="2.5" fill="var(--orange)"/>
                    <!-- Bottom bar -->
                    <rect x="35" y="97" width="50" height="5" rx="2.5" fill="var(--orange)"/>
                    <!-- Glass outline -->
                    <path d="M40 23 L40 45 Q60 60 60 60 Q60 60 80 45 L80 23" fill="none" stroke="var(--orange)" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M40 97 L40 75 Q60 60 60 60 Q60 60 80 75 L80 97" fill="none" stroke="var(--orange)" stroke-width="2.5" stroke-linecap="round"/>
                    <!-- Top sand -->
                    <path d="M44 28 L44 42 Q60 54 60 54 Q60 54 76 42 L76 28 Z" fill="var(--orange)" opacity="0.2"/>
                    <!-- Bottom sand (filling) -->
                    <path d="M44 92 L44 78 Q60 66 60 66 Q60 66 76 78 L76 92 Z" fill="var(--orange)" opacity="0.35"/>
                    <!-- Falling sand grains -->
                    <circle cx="58" cy="58" r="1.5" fill="var(--orange)" class="sand-grain" style="animation-delay:0s"/>
                    <circle cx="60" cy="56" r="1" fill="var(--orange)" class="sand-grain" style="animation-delay:0.5s"/>
                    <circle cx="62" cy="59" r="1.2" fill="var(--orange)" class="sand-grain" style="animation-delay:1s"/>
                    <circle cx="59" cy="57" r="0.8" fill="var(--orange)" class="sand-grain" style="animation-delay:1.5s"/>
                </g>
                <!-- Clock hands overlay -->
                <circle cx="60" cy="60" r="12" fill="none" stroke="var(--orange)" stroke-width="1.5" opacity="0.4"/>
                <line x1="60" y1="60" x2="60" y2="52" stroke="var(--orange)" stroke-width="2" stroke-linecap="round" opacity="0.6">
                    <animateTransform attributeName="transform" type="rotate" from="0 60 60" to="360 60 60" dur="8s" repeatCount="indefinite"/>
                </line>
                <line x1="60" y1="60" x2="60" y2="54" stroke="var(--orange)" stroke-width="1.5" stroke-linecap="round" opacity="0.4">
                    <animateTransform attributeName="transform" type="rotate" from="0 60 60" to="360 60 60" dur="60s" repeatCount="indefinite"/>
                </line>
                <circle cx="60" cy="60" r="2" fill="var(--orange)"/>
            </svg>
        </div>
        <div class="code">Error 419 — Page Expired</div>
        <h1>หมดเวลาการเชื่อมต่อ</h1>
        <p class="desc">เซสชันของคุณหมดอายุ กรุณารีเฟรชหน้าเว็บหรือเข้าสู่ระบบใหม่</p>
        <div class="countdown">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            นำทางใน <span class="countdown-num" id="countdown">5</span> วินาที
        </div>
        <div class="actions">
            <button onclick="location.reload()" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                รีเฟรช
            </button>
            <a href="{{ url('/login') }}" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                เข้าสู่ระบบใหม่
            </a>
        </div>
        <hr class="divider">
        <div class="footer">UniActivity &mdash; ระบบกิจกรรมนักศึกษา</div>
    </div>
    <script>
        let s = 5;
        const el = document.getElementById('countdown');
        const t = setInterval(() => { s--; if (el) el.textContent = s; if (s <= 0) { clearInterval(t); window.location.href = '{{ url("/login") }}'; } }, 1000);
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
