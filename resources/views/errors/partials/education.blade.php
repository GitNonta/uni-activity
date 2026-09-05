<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $code }} - {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        :root {
            --bg: #f3f8fc;
            --surface: #ffffff;
            --surface-soft: #e8f1fb;
            --border: #c9d8e8;
            --ink: #102a43;
            --muted: #486581;
            --faint: #627d98;
            --primary: #1d4ed8;
            --primary-hover: #1e40af;
            --accent: #b45309;
            --accent-soft: #fff4d6;
            --button-ink: #ffffff;
            --shadow: rgba(16, 42, 67, .12);
        }
        html[data-theme="light"] { color-scheme: light; }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0b1220;
            --surface: #111c2e;
            --surface-soft: #172943;
            --border: #304761;
            --ink: #edf5ff;
            --muted: #c2d2e5;
            --faint: #a9bdd3;
            --primary: #75a9ff;
            --primary-hover: #9bc2ff;
            --accent: #f6c453;
            --accent-soft: #3b3018;
            --button-ink: #0b1220;
            --shadow: rgba(0, 0, 0, .35);
        }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.25rem;
            color: var(--ink);
            background: var(--bg);
            font-family: 'Sarabun', sans-serif;
            line-height: 1.5;
        }
        .page-shell { width: min(100%, 680px); position: relative; }
        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 2;
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
        }
        .theme-toggle:hover, .theme-toggle:focus-visible { color: var(--primary); border-color: var(--primary); }
        .card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
            padding: clamp(1.25rem, 5vw, 3rem);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 18px 50px var(--shadow);
            text-align: center;
        }
        .art-panel {
            width: min(100%, 360px);
            min-height: 220px;
            display: grid;
            place-items: center;
            padding: 1rem;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 20px;
        }
        .art-panel svg { display: block; width: min(100%, 300px); height: auto; overflow: visible; }
        .content { width: 100%; display: flex; flex-direction: column; align-items: center; }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            margin: 0;
            color: var(--accent);
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .eyebrow::before, .eyebrow::after { content: ''; width: 28px; height: 3px; border-radius: 99px; background: var(--accent); }
        .status { margin: .3rem 0 0; color: var(--primary); font-size: clamp(2.5rem, 8vw, 4.5rem); font-weight: 800; line-height: 1; }
        h1 { max-width: 36rem; margin: .65rem 0 .5rem; font-size: clamp(1.35rem, 3vw, 1.85rem); line-height: 1.3; }
        .message { max-width: 38rem; margin: 0 0 1.35rem; color: var(--muted); font-size: 1rem; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .7rem; }
        .button {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .65rem 1rem;
            border-radius: 10px;
            border: 1px solid transparent;
            font: inherit;
            font-size: .95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .button-primary { color: var(--button-ink); background: var(--primary); }
        .button-primary:hover, .button-primary:focus-visible { background: var(--primary-hover); }
        .button-secondary { color: var(--ink); background: transparent; border-color: var(--border); }
        .button-secondary:hover, .button-secondary:focus-visible { color: var(--primary); border-color: var(--primary); }
        :focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
        .footer { width: 100%; padding-top: 1rem; border-top: 1px solid var(--border); color: var(--faint); font-size: .82rem; text-align: center; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        @keyframes float-cap { 0%, 100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-8px) rotate(2deg); } }
        @keyframes pencil-write { 0%, 100% { transform: rotate(-8deg) translate(0, 0); } 50% { transform: rotate(-3deg) translate(3px, -2px); } }
        @keyframes page-wave { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(2deg); } }
        @keyframes dot-pulse { 0%, 100% { opacity: .25; transform: scale(.8); } 50% { opacity: 1; transform: scale(1); } }
        @keyframes status-pulse { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
        .cap { animation: float-cap 3.2s ease-in-out infinite; transform-origin: 140px 44px; }
        .pencil { animation: pencil-write 2.4s ease-in-out infinite; transform-origin: 206px 125px; }
        .page { animation: page-wave 3s ease-in-out infinite; transform-origin: 140px 142px; }
        .dot { animation: dot-pulse 2s ease-in-out infinite; transform-origin: center; }
        .status-mark { animation: status-pulse 2s ease-in-out infinite; }
        @media (max-width: 640px) {
            body { padding: .75rem; }
            .card { gap: 1rem; padding: 1rem; }
            .art-panel { min-height: 190px; }
            .actions { width: 100%; flex-direction: column; }
            .button { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" type="button" onclick="toggleTheme()" aria-label="สลับธีมสี">
        <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3l1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3l1.42-1.42"/></svg>
        <svg class="moon-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" d="M20.8 15.2A8.5 8.5 0 0 1 8.8 3.2 8.5 8.5 0 1 0 20.8 15.2Z"/></svg>
    </button>

    <main class="page-shell">
        <section class="card" aria-labelledby="error-title">
            <div class="art-panel">
                <svg viewBox="0 0 280 210" role="img" aria-labelledby="education-art-title" xmlns="http://www.w3.org/2000/svg">
                    <title id="education-art-title">ภาพประกอบการเรียนรู้สำหรับข้อผิดพลาด {{ $code }}</title>
                    <circle cx="32" cy="34" r="4" fill="var(--accent)" class="dot"/>
                    <circle cx="246" cy="42" r="5" fill="var(--primary)" class="dot" style="animation-delay:.5s"/>
                    <circle cx="224" cy="178" r="3" fill="var(--accent)" class="dot" style="animation-delay:1s"/>
                    <path d="M30 160h220" stroke="var(--border)" stroke-width="3" stroke-linecap="round"/>
                    <g class="page">
                        <path d="M45 139c30-12 61-12 95 2v35c-34-14-65-14-95-2v-35Z" fill="var(--surface)" stroke="var(--primary)" stroke-width="3"/>
                        <path d="M235 139c-30-12-61-12-95 2v35c34-14 65-14 95-2v-35Z" fill="var(--surface)" stroke="var(--primary)" stroke-width="3"/>
                        <path d="M63 148h54m-54 10h42m58-10h54m-54 10h42" stroke="var(--muted)" stroke-width="3" stroke-linecap="round"/>
                        <path d="M137 145h6v33h-6z" fill="var(--accent)"/>
                    </g>
                    <g class="cap">
                        <path d="m140 22 66 28-66 28-66-28 66-28Z" fill="var(--primary)" stroke="var(--ink)" stroke-width="3" stroke-linejoin="round"/>
                        <path d="M94 69v20c23 17 69 17 92 0V69" fill="var(--surface-soft)" stroke="var(--primary)" stroke-width="3"/>
                        <path d="M206 50v39" stroke="var(--accent)" stroke-width="4" stroke-linecap="round"/>
                        <circle cx="206" cy="94" r="7" fill="var(--accent)"/>
                    </g>
                    <g class="pencil">
                        <path d="m183 123 31-31 12 12-31 31-16 4 4-16Z" fill="var(--accent)" stroke="var(--ink)" stroke-width="2" stroke-linejoin="round"/>
                        <path d="m214 92 6-6 12 12-6 6" fill="var(--primary)" stroke="var(--ink)" stroke-width="2"/>
                        <path d="m183 123-4 16 16-4" fill="var(--surface)" stroke="var(--ink)" stroke-width="2"/>
                        <path d="m179 139 6-6" stroke="var(--ink)" stroke-width="2" stroke-linecap="round"/>
                    </g>
                    @if ($code === '403')
                        <g class="status-mark">
                            <circle cx="52" cy="88" r="24" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                            <rect x="43" y="87" width="18" height="15" rx="3" fill="var(--accent)"/>
                            <path d="M47 87v-5a5 5 0 0 1 10 0v5" fill="none" stroke="var(--accent)" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="52" cy="94" r="2" fill="var(--surface)"/>
                        </g>
                    @elseif ($code === '404')
                        <g class="status-mark">
                            <circle cx="50" cy="85" r="15" fill="none" stroke="var(--accent)" stroke-width="4"/>
                            <path d="m61 96 12 12" stroke="var(--accent)" stroke-width="5" stroke-linecap="round"/>
                        </g>
                    @elseif ($code === '419')
                        <g class="status-mark">
                            <circle cx="50" cy="87" r="18" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                            <path d="M50 77v11l7 4" stroke="var(--accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        </g>
                    @elseif ($code === '500')
                        <g class="status-mark">
                            <path d="m50 68 20 35H30l20-35Z" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3" stroke-linejoin="round"/>
                            <path d="M50 80v10m0 6v1" stroke="var(--accent)" stroke-width="3" stroke-linecap="round"/>
                        </g>
                    @else
                        <g class="status-mark">
                            <circle cx="50" cy="87" r="19" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                            <path d="M41 87h18M50 78v18" stroke="var(--accent)" stroke-width="3" stroke-linecap="round"/>
                        </g>
                    @endif
                </svg>
            </div>

            <div class="content">
                <p class="eyebrow">UniActivity · พื้นที่การเรียนรู้</p>
                <p class="status" aria-label="รหัสข้อผิดพลาด {{ $code }}">{{ $code }}</p>
                <h1 id="error-title">{{ $title }}</h1>
                <p class="message">{{ $message }}</p>
                <div class="actions">
                    @if ($code === '419')
                        <a class="button button-primary" href="{{ url('/login') }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5m5 5H3"/></svg>
                            เข้าสู่ระบบใหม่
                        </a>
                    @elseif ($code === '500' || $code === '503')
                        <button class="button button-primary" type="button" onclick="location.reload()">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0 1 12-3l2 3M19 15a7 7 0 0 1-12 3l-2-3"/></svg>
                            ลองใหม่อีกครั้ง
                        </button>
                    @else
                        <a class="button button-primary" href="{{ url('/') }}">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10Z"/></svg>
                            กลับหน้าหลัก
                        </a>
                    @endif
                    <button class="button button-secondary" type="button" onclick="history.back()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7"/></svg>
                        ย้อนกลับ
                    </button>
                </div>
            </div>
            <p class="footer">ระบบกิจกรรมนักศึกษา · เรียนรู้ เติบโต และก้าวไปด้วยกัน</p>
        </section>
    </main>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const dark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', dark ? 'light' : 'dark');
            document.querySelector('.sun-icon').style.display = dark ? 'block' : 'none';
            document.querySelector('.moon-icon').style.display = dark ? 'none' : 'block';
        }
    </script>
</body>
</html>
