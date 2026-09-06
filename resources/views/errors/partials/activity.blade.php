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
            --surface-soft: #fff7ed;
            --border: #fed7aa;
            --ink: #1f2937;
            --muted: #475569;
            --faint: #64748b;
            --primary: #ea580c;
            --primary-hover: #c2410c;
            --accent: #c2410c;
            --accent-soft: #ffedd5;
            --button-ink: #ffffff;
            --shadow: rgba(16, 42, 67, .12);
        }
        html[data-theme="light"] { color-scheme: light; }
        html[data-theme="dark"] {
            color-scheme: dark;
            --bg: #0b1220;
            --surface: #111c2e;
            --surface-soft: #2a1a12;
            --border: #713f12;
            --ink: #f4f4f5;
            --muted: #d4d4d8;
            --faint: #a1a1aa;
            --primary: #f97316;
            --primary-hover: #fb923c;
            --accent: #fdba74;
            --accent-soft: #431407;
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
            background: transparent;
            border: 0;
            border-radius: 999px;
            cursor: pointer;
        }
        .theme-toggle:hover, .theme-toggle:focus-visible { color: var(--primary); background: var(--accent-soft); }
        .error-layout {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
            padding: clamp(1rem, 5vw, 2.5rem);
            text-align: center;
        }
        .illustration {
            width: min(100%, 36rem);
            min-height: 220px;
            display: grid;
            place-items: center;
        }
        .illustration svg { display: block; width: min(100%, 300px); height: auto; overflow: visible; }
        .content { width: min(100%, 36rem); display: flex; flex-direction: column; align-items: center; }
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
        .footer { width: 100%; padding-top: 1rem; color: var(--faint); font-size: .82rem; text-align: center; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        @keyframes character-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
        @keyframes character-wave { 0%, 100% { transform: rotate(8deg); } 50% { transform: rotate(-18deg); } }
        @keyframes character-blink { 0%, 92%, 100% { transform: scaleY(1); } 96% { transform: scaleY(.12); } }
        @keyframes activity-pulse { 0%, 100% { opacity: .35; transform: scale(.85); } 50% { opacity: 1; transform: scale(1); } }
        @keyframes status-pulse { 0%, 100% { opacity: .55; } 50% { opacity: 1; } }
        .character { animation: character-bob 3.2s ease-in-out infinite; transform-origin: 140px 142px; }
        .character-arm { animation: character-wave 2.4s ease-in-out infinite; transform-origin: 183px 112px; }
        .character-eye { animation: character-blink 4s ease-in-out infinite; transform-origin: center; }
        .activity-dot { animation: activity-pulse 2s ease-in-out infinite; transform-origin: center; }
        .status-mark { animation: status-pulse 2s ease-in-out infinite; }
        @media (max-width: 640px) {
            body { padding: .75rem; }
            .error-layout { gap: 1rem; padding: 1rem 0; }
            .illustration { min-height: 190px; }
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
        <section class="error-layout" aria-labelledby="error-title">
            <div class="illustration">
                <svg viewBox="0 0 280 210" role="img" aria-labelledby="activity-art-title" xmlns="http://www.w3.org/2000/svg">
                    <title id="activity-art-title">ตัวการ์ตูนระบบกิจกรรมสำหรับข้อผิดพลาด {{ $code }}</title>
                    <circle cx="38" cy="44" r="4" fill="var(--accent)" class="activity-dot"/>
                    <circle cx="242" cy="57" r="5" fill="var(--primary)" class="activity-dot" style="animation-delay:.5s"/>
                    <circle cx="222" cy="165" r="3" fill="var(--accent)" class="activity-dot" style="animation-delay:1s"/>
                    <path d="M72 178h136" stroke="var(--border)" stroke-width="3" stroke-linecap="round"/>
                    <g class="character">
                        <path d="M98 142c0-26 18-43 42-43s42 17 42 43v20H98v-20Z" fill="var(--primary)"/>
                        <path d="M111 158v20M169 158v20" stroke="var(--ink)" stroke-width="8" stroke-linecap="round"/>
                        <path d="M102 116 80 132" stroke="var(--primary)" stroke-width="13" stroke-linecap="round"/>
                        <path class="character-arm" d="M178 116 202 96" stroke="var(--primary)" stroke-width="13" stroke-linecap="round"/>
                        <circle cx="140" cy="76" r="31" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                        <path d="M114 67c5-22 47-25 53 1-10-6-36-7-53-1Z" fill="var(--accent)"/>
                        <ellipse class="character-eye" cx="129" cy="78" rx="3" ry="5" fill="var(--ink)"/>
                        <ellipse class="character-eye" cx="151" cy="78" rx="3" ry="5" fill="var(--ink)" style="animation-delay:.15s"/>
                        <path d="M133 91c5 5 10 5 15 0" fill="none" stroke="var(--ink)" stroke-width="3" stroke-linecap="round"/>
                        <path d="M187 91h28v22h-28z" fill="var(--surface)" stroke="var(--primary)" stroke-width="3"/>
                        <path d="M193 98h16M193 104h11" stroke="var(--accent)" stroke-width="2" stroke-linecap="round"/>
                    </g>
                    <g class="status-mark">
                    @if ($code === '403')
                        <rect x="48" y="105" width="28" height="28" rx="8" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                        <rect x="56" y="117" width="12" height="10" rx="2" fill="var(--accent)"/>
                        <path d="M58 117v-4a4 4 0 0 1 8 0v4" fill="none" stroke="var(--accent)" stroke-width="3" stroke-linecap="round"/>
                    @elseif ($code === '404')
                        <circle cx="61" cy="119" r="15" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                        <path d="m71 130 10 10" stroke="var(--accent)" stroke-width="4" stroke-linecap="round"/>
                    @elseif ($code === '419')
                        <circle cx="61" cy="119" r="16" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                        <path d="M61 110v9l6 4" stroke="var(--accent)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    @elseif ($code === '500')
                        <path d="m61 99 18 32H43l18-32Z" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3" stroke-linejoin="round"/>
                        <path d="M61 110v9m0 5v1" stroke="var(--accent)" stroke-width="3" stroke-linecap="round"/>
                    @else
                        <circle cx="61" cy="119" r="16" fill="var(--accent-soft)" stroke="var(--accent)" stroke-width="3"/>
                        <path d="M53 119h16M61 111v16" stroke="var(--accent)" stroke-width="3" stroke-linecap="round"/>
                    @endif
                    </g>
                </svg>
            </div>

            <div class="content">
                <p class="eyebrow">UniActivity · ศูนย์รวมกิจกรรม</p>
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
                    <button class="button button-secondary" type="button" onclick="goBack()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m7 7-7-7 7-7"/></svg>
                        ย้อนกลับ
                    </button>
                </div>
            </div>
            <p class="footer">ระบบกิจกรรมนักศึกษา · ค้นหา ลงทะเบียน และร่วมกิจกรรม</p>
        </section>
    </main>

    <script>
        function goBack() {
            if (window.history.length > 1 && document.referrer && document.referrer !== window.location.href) {
                window.history.back();
                return;
            }

            window.location.assign(@json(url('/')));
        }

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
