<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - ไม่มีสิทธิ์เข้าถึงระบบ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        ::selection { background: #ea580c; color: #fff; }

        :root {
            --bg: #f8fafc; --surface: #fff; --border: #e2e8f0;
            --text: #0f172a; --sub: #475569; --muted: #64748b;
            --orange: #ea580c; --orange-hover: #c2410c;
            --icon-bg: #fff7ed; --icon-border: #fed7aa;
            --btn-outline-bg: #fff; --btn-outline-border: #cbd5e1;
            --btn-outline-hover: #f8fafc; --divider: #e2e8f0;
        }
        html[data-theme="dark"] {
            --bg: #09090b; --surface: #18181b; --border: #27272a;
            --text: #f4f4f5; --sub: #a1a1aa; --muted: #71717a;
            --orange: #f97316; --orange-hover: #fb923c;
            --icon-bg: rgba(234,88,12,0.12); --icon-border: rgba(234,88,12,0.25);
            --btn-outline-bg: transparent; --btn-outline-border: #3f3f46;
            --btn-outline-hover: #27272a; --divider: #27272a;
        }
        body {
            font-family: 'Sarabun', 'Segoe UI', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center;
            justify-content: center; padding: 1.5rem;
        }
        .card {
            max-width: 440px; width: 100%; text-align: center;
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 20px; padding: 3rem 2rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        html[data-theme="dark"] .card { box-shadow: 0 4px 32px rgba(0,0,0,0.4); }
        .icon-wrap {
            width: 88px; height: 88px; margin: 0 auto 1.5rem;
            background: var(--icon-bg); border: 1px solid var(--icon-border);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; color: var(--orange);
            filter: drop-shadow(0 8px 16px rgba(234,88,12,0.15));
        }
        .code {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em;
            text-transform: uppercase; color: var(--orange);
            margin-bottom: 0.75rem; font-family: monospace;
        }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; }
        .desc { color: var(--sub); font-size: 0.9rem; line-height: 1.5; margin-bottom: 2rem; max-width: 340px; margin-left: auto; margin-right: auto; }
        .actions { display: flex; flex-direction: column; gap: 0.75rem; }
        @media (min-width: 480px) { .actions { flex-direction: row; justify-content: center; } }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; padding: 0.65rem 1.5rem; border-radius: 10px;
            font-size: 0.875rem; font-weight: 600; text-decoration: none;
            cursor: pointer; transition: all 0.15s ease; border: none;
            font-family: inherit; min-height: 44px;
        }
        .btn-primary { background: var(--orange); color: #fff; }
        .btn-primary:hover { background: var(--orange-hover); transform: translateY(-1px); }
        .btn-secondary {
            background: var(--btn-outline-bg); color: var(--text);
            border: 1px solid var(--btn-outline-border);
        }
        .btn-secondary:hover { background: var(--btn-outline-hover); }
        .divider { border: none; border-top: 1px solid var(--divider); margin: 1.5rem 0; }
        .footer { font-size: 0.75rem; color: var(--muted); }
        .theme-toggle {
            position: fixed; top: 1rem; right: 1rem;
            width: 36px; height: 36px; border-radius: 8px;
            border: 1px solid var(--border); background: var(--surface);
            color: var(--muted); cursor: pointer; display: flex;
            align-items: center; justify-content: center; transition: all 0.2s;
        }
        .theme-toggle:hover { color: var(--orange); border-color: var(--orange); }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
        <svg class="sun-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <svg class="moon-icon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
    </button>

    <div class="card">
        <div class="icon-wrap">
            <svg width="44" height="44" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
            </svg>
        </div>
        <div class="code">Error 403 — Forbidden</div>
        <h1>ไม่มีสิทธิ์เข้าถึงส่วนนี้</h1>
        <p class="desc">
            ขออภัย บัญชีผู้ใช้งานของคุณไม่ได้รับอนุญาตให้เข้าถึงเนื้อหาในส่วนนี้ กรุณาติดต่อผู้ดูแลระบบหากคุณเชื่อว่านี่คือความผิดพลาด
        </p>
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
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            document.querySelector('.sun-icon').style.display = isDark ? 'block' : 'none';
            document.querySelector('.moon-icon').style.display = isDark ? 'none' : 'block';
        }
    </script>
</body>
</html>
