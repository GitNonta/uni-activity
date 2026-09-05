<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - ระบบขัดข้อง</title>
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
        .svg-wrap { width: 130px; height: 120px; margin: 0 auto 1.5rem; }
        .code { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--orange); margin-bottom: 0.5rem; font-family: monospace; }
        h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
        .desc { color: var(--sub); font-size: 0.875rem; line-height: 1.5; margin-bottom: 1.75rem; }
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
        @keyframes shake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); } 20%, 40%, 60%, 80% { transform: translateX(2px); } }
        @keyframes spark { 0%, 100% { opacity: 0; } 50% { opacity: 1; } }
        .gear-cw { animation: gear-cw 6s linear infinite; transform-origin: center; }
        .gear-ccw { animation: gear-ccw 4s linear infinite; transform-origin: center; }
        .shake { animation: shake 0.6s ease-in-out infinite; }
        .spark { animation: spark 1.5s ease-in-out infinite; }
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
                <circle cx="65" cy="55" r="40" fill="#ea580c" opacity="0.06"/>
                <!-- Main gear (broken, shaking) -->
                <g class="shake">
                    <g class="gear-cw" style="transform-origin: 55px 52px;">
                        <circle cx="55" cy="52" r="22" fill="none" stroke="var(--orange)" stroke-width="3"/>
                        <circle cx="55" cy="52" r="8" fill="var(--orange)" opacity="0.2"/>
                        <circle cx="55" cy="52" r="4" fill="var(--orange)"/>
                        <!-- Teeth -->
                        <rect x="52" y="26" width="6" height="8" rx="2" fill="var(--orange)"/>
                        <rect x="52" y="70" width="6" height="8" rx="2" fill="var(--orange)"/>
                        <rect x="29" y="49" width="8" height="6" rx="2" fill="var(--orange)"/>
                        <rect x="73" y="49" width="8" height="6" rx="2" fill="var(--orange)"/>
                        <rect x="34" y="31" width="6" height="8" rx="2" fill="var(--orange)" transform="rotate(-45 37 35)"/>
                        <rect x="70" y="65" width="6" height="8" rx="2" fill="var(--orange)" transform="rotate(-45 73 69)"/>
                        <rect x="65" y="31" width="8" height="6" rx="2" fill="var(--orange)" transform="rotate(45 69 34)"/>
                        <rect x="29" y="65" width="8" height="6" rx="2" fill="var(--orange)" transform="rotate(45 33 68)"/>
                    </g>
                    <!-- Crack line -->
                    <path d="M45 42 L52 48 L48 55 L55 58" stroke="var(--orange)" stroke-width="2" stroke-linecap="round" fill="none" opacity="0.7"/>
                </g>
                <!-- Small gear (broken) -->
                <g class="shake" style="animation-delay: 0.1s;">
                    <g class="gear-ccw" style="transform-origin: 88px 35px;">
                        <circle cx="88" cy="35" r="12" fill="none" stroke="var(--orange)" stroke-width="2.5"/>
                        <circle cx="88" cy="35" r="4" fill="var(--orange)" opacity="0.3"/>
                        <circle cx="88" cy="35" r="2" fill="var(--orange)"/>
                        <rect x="86" y="21" width="4" height="5" rx="2" fill="var(--orange)"/>
                        <rect x="86" y="43" width="4" height="5" rx="2" fill="var(--orange)"/>
                        <rect x="74" y="33" width="5" height="4" rx="2" fill="var(--orange)"/>
                        <rect x="97" y="33" width="5" height="4" rx="2" fill="var(--orange)"/>
                    </g>
                </g>
                <!-- Sparks -->
                <circle cx="70" cy="45" r="2" fill="#fbbf24" class="spark" style="animation-delay:0s"/>
                <circle cx="75" cy="40" r="1.5" fill="#fbbf24" class="spark" style="animation-delay:0.3s"/>
                <circle cx="68" cy="50" r="1" fill="#fbbf24" class="spark" style="animation-delay:0.6s"/>
                <circle cx="80" cy="42" r="1.5" fill="#fbbf24" class="spark" style="animation-delay:0.9s"/>
                <!-- Warning triangle -->
                <g transform="translate(10, 80)">
                    <path d="M10 0 L20 16 H0 Z" fill="none" stroke="var(--orange)" stroke-width="1.5" opacity="0.4"/>
                    <text x="10" y="13" text-anchor="middle" fill="var(--orange)" font-size="10" font-weight="bold" opacity="0.5">!</text>
                </g>
            </svg>
        </div>
        <div class="code">Error 500 — Server Error</div>
        <h1>ระบบขัดข้องชั่วคราว</h1>
        <p class="desc">ขออภัย ระบบเกิดข้อผิดพลาดภายใน ทีมงานกำลังเร่งแก้ไข กรุณาลองใหม่อีกครั้ง</p>
        <div class="actions">
            <button onclick="location.reload()" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                ลองใหม่อีกครั้ง
            </button>
            <a href="/" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                กลับสู่หน้าหลัก
            </a>
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
