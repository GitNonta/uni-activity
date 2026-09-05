<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - ไม่พบหน้า</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        ::selection { background: #ea580c; color: #fff; }
        :root {
            --bg: #020617; --surface: rgba(15,23,42,0.8); --border: rgba(255,255,255,0.08);
            --text: #f8fafc; --sub: #94a3b8; --muted: #64748b;
            --orange: #f97316; --orange-hover: #fb923c;
            --btn-bg: rgba(255,255,255,0.05); --btn-border: rgba(255,255,255,0.1); --btn-hover: rgba(255,255,255,0.12);
            --divider: rgba(255,255,255,0.08);
        }
        html[data-theme="light"] {
            --bg: #f8fafc; --surface: #fff; --border: #e2e8f0;
            --text: #0f172a; --sub: #475569; --muted: #94a3b8;
            --orange: #ea580c; --orange-hover: #c2410c;
            --btn-bg: #fff; --btn-border: #cbd5e1; --btn-hover: #f8fafc;
            --divider: #e2e8f0;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 1.5rem; overflow: hidden;
        }
        .stars { position: fixed; inset: 0; pointer-events: none; z-index: 0; }
        .card {
            position: relative; z-index: 1;
            max-width: 440px; width: 100%; text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 20px; padding: 2.5rem 2rem;
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        }
        .svg-wrap { width: 140px; height: 120px; margin: 0 auto 1rem; }
        .orbitron { font-family: 'Orbitron', sans-serif; }
        .code { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--orange); margin-bottom: 0.5rem; font-family: monospace; }
        h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 0.5rem; line-height: 1.3; }
        .big-num { font-size: 3rem; font-weight: 900; line-height: 1; margin-bottom: 0.25rem; background: linear-gradient(135deg, #f97316, #a855f7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
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
        .btn-secondary { background: var(--btn-bg); color: var(--text); border: 1px solid var(--btn-border); backdrop-filter: blur(8px); }
        .btn-secondary:hover { background: var(--btn-hover); }
        .divider { border: none; border-top: 1px solid var(--divider); margin: 1.25rem 0; }
        .footer { font-size: 0.75rem; color: var(--muted); }
        .theme-toggle {
            position: fixed; top: 1rem; right: 1rem; width: 36px; height: 36px;
            border-radius: 8px; border: 1px solid var(--border); background: var(--surface);
            color: var(--muted); cursor: pointer; display: flex; align-items: center;
            justify-content: center; transition: all 0.2s; z-index: 10;
        }
        .theme-toggle:hover { color: var(--orange); border-color: var(--orange); }
        /* SVG Animations */
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes wave { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(15deg); } }
        @keyframes orbit { from { transform: rotate(0deg) translateX(18px) rotate(0deg); } to { transform: rotate(360deg) translateX(18px) rotate(-360deg); } }
        @keyframes twinkle { 0%, 100% { opacity: 0.3; } 50% { opacity: 1; } }
        .float { animation: float 3s ease-in-out infinite; }
        .wave { animation: wave 2s ease-in-out infinite; transform-origin: 70% 70%; }
        .orbit-dot { animation: orbit 4s linear infinite; transform-origin: 52px 48px; }
        .star-dot { animation: twinkle 2s ease-in-out infinite; }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <svg class="sun-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <svg class="moon-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
    </button>

    <div class="card">
        <div class="svg-wrap">
            <svg viewBox="0 0 120 100" fill="none" xmlns="http://www.w3.org/2000/svg" width="140" height="120">
                <!-- Stars -->
                <circle cx="15" cy="12" r="1.2" fill="var(--orange)" class="star-dot" style="animation-delay:0s"/>
                <circle cx="105" cy="18" r="1" fill="var(--orange)" class="star-dot" style="animation-delay:0.5s"/>
                <circle cx="25" cy="85" r="0.8" fill="var(--orange)" class="star-dot" style="animation-delay:1s"/>
                <circle cx="95" cy="78" r="1.2" fill="var(--orange)" class="star-dot" style="animation-delay:1.5s"/>
                <circle cx="8" cy="50" r="0.8" fill="var(--orange)" class="star-dot" style="animation-delay:0.8s"/>
                <circle cx="112" cy="50" r="1" fill="var(--orange)" class="star-dot" style="animation-delay:1.2s"/>
                <!-- Planet -->
                <circle cx="90" cy="25" r="10" fill="none" stroke="var(--orange)" stroke-width="1.5" opacity="0.3"/>
                <circle cx="90" cy="25" r="8" fill="var(--orange)" opacity="0.1"/>
                <ellipse cx="90" cy="25" rx="16" ry="4" fill="none" stroke="var(--orange)" stroke-width="1" opacity="0.2" transform="rotate(-20 90 25)"/>
                <!-- Astronaut (floating) -->
                <g class="float" style="animation-delay: 0.2s">
                    <!-- Helmet -->
                    <circle cx="52" cy="40" r="14" fill="#334155" stroke="var(--orange)" stroke-width="2"/>
                    <circle cx="52" cy="40" r="10" fill="#0f172a" opacity="0.6"/>
                    <!-- Visor reflection -->
                    <path d="M46 36 Q52 32 58 36" stroke="var(--orange)" stroke-width="1.5" fill="none" opacity="0.6"/>
                    <!-- Body -->
                    <rect x="42" y="52" width="20" height="18" rx="6" fill="#334155" stroke="var(--orange)" stroke-width="1.5"/>
                    <!-- Backpack -->
                    <rect x="38" y="54" width="5" height="12" rx="2" fill="#475569" stroke="var(--orange)" stroke-width="1"/>
                    <!-- Legs -->
                    <rect x="44" y="68" width="6" height="10" rx="3" fill="#334155" stroke="var(--orange)" stroke-width="1"/>
                    <rect x="54" y="68" width="6" height="10" rx="3" fill="#334155" stroke="var(--orange)" stroke-width="1"/>
                    <!-- Boots -->
                    <rect x="43" y="76" width="8" height="4" rx="2" fill="var(--orange)"/>
                    <rect x="53" y="76" width="8" height="4" rx="2" fill="var(--orange)"/>
                    <!-- Waving arm -->
                    <g class="wave">
                        <rect x="62" y="54" width="14" height="5" rx="2.5" fill="#334155" stroke="var(--orange)" stroke-width="1"/>
                        <circle cx="76" cy="56.5" r="3" fill="#334155" stroke="var(--orange)" stroke-width="1"/>
                    </g>
                    <!-- Other arm -->
                    <rect x="28" y="56" width="12" height="5" rx="2.5" fill="#334155" stroke="var(--orange)" stroke-width="1"/>
                    <!-- Antenna -->
                    <line x1="52" y1="26" x2="52" y2="20" stroke="var(--orange)" stroke-width="1.5"/>
                    <circle cx="52" cy="19" r="2" fill="var(--orange)">
                        <animate attributeName="r" values="2;3;2" dur="1.5s" repeatCount="indefinite"/>
                        <animate attributeName="opacity" values="1;0.5;1" dur="1.5s" repeatCount="indefinite"/>
                    </circle>
                </g>
                <!-- Orbiting satellite -->
                <g class="orbit-dot">
                    <rect x="48" y="44" width="8" height="6" rx="1" fill="var(--orange)" opacity="0.8"/>
                    <rect x="46" y="46" width="3" height="2" rx="0.5" fill="var(--orange)" opacity="0.5"/>
                    <rect x="57" y="46" width="3" height="2" rx="0.5" fill="var(--orange)" opacity="0.5"/>
                </g>
            </svg>
        </div>
        <div class="code">Error 404 — Not Found</div>
        <div class="big-num">404</div>
        <h1>หลงทางในอวกาศ</h1>
        <p class="desc">หน้าที่คุณกำลังมองหาไม่มีอยู่หรือถูกย้ายไปแล้ว ลองกลับไปหน้าหลักดูนะ</p>
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
        // Generate stars
        const c = document.createElement('div');
        c.className = 'stars';
        for (let i = 0; i < 80; i++) {
            const s = document.createElement('div');
            const size = Math.random() * 2 + 0.5;
            Object.assign(s.style, {
                position: 'absolute', borderRadius: '50%', background: '#fff',
                width: size + 'px', height: size + 'px',
                left: Math.random() * 100 + '%', top: Math.random() * 100 + '%',
                opacity: Math.random() * 0.5 + 0.1,
                animation: `twinkle ${Math.random() * 3 + 2}s ease-in-out infinite`,
                animationDelay: Math.random() * 3 + 's'
            });
            c.appendChild(s);
        }
        document.body.prepend(c);

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
