<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - ไม่มีสิทธิ์เข้าถึงระบบ</title>
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
        .desc { color: var(--sub); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.75rem; max-width: 320px; margin-left: auto; margin-right: auto; }
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
        @keyframes shield-pulse { 0%, 100% { transform: scale(1); opacity: 0.15; } 50% { transform: scale(1.15); opacity: 0.3; } }
        @keyframes lock-swing { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-3deg); } 75% { transform: rotate(3deg); } }
        @keyframes shield-glow { 0%, 100% { filter: drop-shadow(0 0 8px rgba(234,88,12,0.2)); } 50% { filter: drop-shadow(0 0 20px rgba(234,88,12,0.4)); } }
        .shield-pulse { animation: shield-pulse 3s ease-in-out infinite; transform-origin: center; }
        .lock-swing { animation: lock-swing 4s ease-in-out infinite; transform-origin: top center; }
        .shield-glow { animation: shield-glow 3s ease-in-out infinite; }
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
                <!-- Glow pulse behind shield -->
                <circle cx="60" cy="56" r="40" fill="#ea580c" opacity="0.15" class="shield-pulse"/>
                <!-- Shield body -->
                <g class="shield-glow">
                    <path d="M60 16 L96 32 V60 C96 82 78 100 60 108 C42 100 24 82 24 60 V32 L60 16Z" fill="var(--icon-bg)" stroke="var(--orange)" stroke-width="2.5" stroke-linejoin="round"/>
                </g>
                <!-- Lock body -->
                <g class="lock-swing" style="transform-origin: 60px 52px;">
                    <rect x="48" y="52" width="24" height="20" rx="3" fill="var(--orange)"/>
                    <path d="M52 52 V46 C52 40 56 36 60 36 C64 36 68 40 68 46 V52" fill="none" stroke="var(--orange)" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="60" cy="61" r="3" fill="#fff"/>
                    <line x1="60" y1="64" x2="60" y2="68" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
                </g>
                <!-- Small decorative dots -->
                <circle cx="38" cy="38" r="2" fill="var(--orange)" opacity="0.3">
                    <animate attributeName="opacity" values="0.3;0.8;0.3" dur="2s" repeatCount="indefinite"/>
                </circle>
                <circle cx="82" cy="38" r="2" fill="var(--orange)" opacity="0.3">
                    <animate attributeName="opacity" values="0.3;0.8;0.3" dur="2.5s" repeatCount="indefinite"/>
                </circle>
                <circle cx="46" cy="26" r="1.5" fill="var(--orange)" opacity="0.2">
                    <animate attributeName="opacity" values="0.2;0.6;0.2" dur="3s" repeatCount="indefinite"/>
                </circle>
                <circle cx="74" cy="26" r="1.5" fill="var(--orange)" opacity="0.2">
                    <animate attributeName="opacity" values="0.2;0.6;0.2" dur="2.8s" repeatCount="indefinite"/>
                </circle>
            </svg>
        </div>
        <div class="code">Error 403 — Forbidden</div>
        <h1>ไม่มีสิทธิ์เข้าถึงส่วนนี้</h1>
        <p class="desc">ขออภัย บัญชีผู้ใช้งานของคุณไม่ได้รับอนุญาตให้เข้าถึงเนื้อหาส่วนนี้ กรุณาติดต่อผู้ดูแลระบบ</p>
        <div class="actions">
            <a href="/" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                กลับสู่หน้าหลัก
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                ย้อนกลับ
            </button>
        </div>
        <hr class="divider">
        <div class="footer">UniActivity &mdash; ระบบกิจกรรมนักศึกษา</div>
    </div>
    <script>
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
