<!DOCTYPE html>
<html lang="th" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบอยู่ระหว่างปรับปรุง — UniActivity</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap');

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        ::selection { background: #ea580c; color: #fff; }

        :root {
            --bg:       #09090b;
            --surface:  #18181b;
            --border:   #27272a;
            --text:     #fafafa;
            --sub:      #a1a1aa;
            --orange:   #ea580c;
            --orange-l: #f97316;
            --orange-d: #c2410c;
            --glow:     rgba(234,88,12,0.35);
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: 'Sarabun', 'Segoe UI', sans-serif;
            line-height: 1.6;
        }

        /* ── Animated starfield background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 20% -10%, rgba(234,88,12,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 80% 110%, rgba(234,88,12,0.08) 0%, transparent 55%);
            pointer-events: none;
            z-index: 0;
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 560px;
            width: 100%;
            text-align: center;
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04) inset,
                0 25px 60px rgba(0,0,0,0.5);
        }

        /* ── SVG Gear illustration ── */
        .illustration {
            width: 120px;
            height: 120px;
            margin: 0 auto 2rem;
            position: relative;
        }

        /* Glowing halo behind gear */
        .illustration::before {
            content: '';
            position: absolute;
            inset: -16px;
            border-radius: 50%;
            background: var(--glow);
            filter: blur(24px);
            animation: halo-pulse 3s ease-in-out infinite;
        }

        @keyframes halo-pulse {
            0%, 100% { opacity: 0.5; transform: scale(0.9); }
            50%       { opacity: 1;   transform: scale(1.1); }
        }

        .illustration svg { position: relative; z-index: 1; }

        /* ── Gear spin animations ── */
        @keyframes gear-cw  { from { transform: rotate(0deg); }   to { transform: rotate(360deg); } }
        @keyframes gear-ccw { from { transform: rotate(0deg); }   to { transform: rotate(-360deg); } }

        .gear-main  { animation: gear-cw  8s linear infinite; transform-origin: center; }
        .gear-small { animation: gear-ccw 4s linear infinite; transform-origin: center; }

        /* ── Content ── */
        .code {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--orange-l);
            background: rgba(234,88,12,0.12);
            border: 1px solid rgba(234,88,12,0.25);
            border-radius: 999px;
            padding: 4px 14px;
            margin-bottom: 1.25rem;
        }

        h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }

        .description {
            font-size: 0.9375rem;
            color: var(--sub);
            line-height: 1.65;
            margin-bottom: 2rem;
        }

        /* ── ETA bar ── */
        .eta-card {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(234,88,12,0.06);
            border: 1px solid rgba(234,88,12,0.18);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        .eta-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: rgba(234,88,12,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--orange-l);
        }
        .eta-label { font-size: 0.8rem; color: var(--sub); margin-bottom: 2px; }
        .eta-value { font-size: 0.9375rem; font-weight: 700; color: var(--text); }

        /* ── Actions ── */
        .actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }

        .btn-reload {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--orange), var(--orange-l));
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 0.7rem 1.5rem;
            font: 600 0.9375rem 'Sarabun', sans-serif;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
            box-shadow: 0 4px 16px rgba(234,88,12,0.4);
        }
        .btn-reload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(234,88,12,0.5);
            filter: brightness(1.08);
        }
        .btn-reload:active { transform: translateY(0); }

        .btn-contact {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            color: var(--sub);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 0.7rem 1.25rem;
            font: 600 0.9rem 'Sarabun', sans-serif;
            cursor: pointer;
            text-decoration: none;
            transition: border-color 0.15s ease, color 0.15s ease, background 0.15s ease;
        }
        .btn-contact:hover {
            border-color: var(--orange-d);
            color: var(--orange-l);
            background: rgba(234,88,12,0.06);
        }

        /* ── Footer ── */
        .footer {
            margin-top: 2.5rem;
            font-size: 0.8rem;
            color: #52525b;
        }
        .footer a { color: var(--orange); text-decoration: none; }

        /* ── Countdown (อัปเดตด้วย JS) ── */
        #countdown {
            font-variant-numeric: tabular-nums;
            color: var(--orange-l);
            font-weight: 700;
        }

        /* Mobile */
        @media (max-width: 480px) {
            .card { padding: 2rem 1.25rem; border-radius: 18px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="card">
            <!-- Gear illustration -->
            <div class="illustration">
                <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Main gear -->
                    <g class="gear-main">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="
                            M60 30
                            a30 30 0 1 0 0 60
                            a30 30 0 1 0 0-60
                            M60 42a18 18 0 1 1 0 36 18 18 0 0 1 0-36
                        " fill="none" stroke="#ea580c" stroke-width="6"/>
                        <!-- Gear teeth (8 teeth) -->
                        <rect x="56.5" y="14" width="7" height="14" rx="3.5" fill="#ea580c"/>
                        <rect x="56.5" y="92" width="7" height="14" rx="3.5" fill="#ea580c"/>
                        <rect x="14" y="56.5" width="14" height="7" rx="3.5" fill="#ea580c"/>
                        <rect x="92" y="56.5" width="14" height="7" rx="3.5" fill="#ea580c"/>
                        <!-- Diagonal teeth -->
                        <rect x="27" y="23" width="7" height="14" rx="3.5" transform="rotate(-45 27 23)" fill="#ea580c"/>
                        <rect x="79" y="75" width="7" height="14" rx="3.5" transform="rotate(-45 79 75)" fill="#ea580c"/>
                        <rect x="23" y="79" width="14" height="7" rx="3.5" transform="rotate(-45 23 79)" fill="#ea580c"/>
                        <rect x="75" y="27" width="14" height="7" rx="3.5" transform="rotate(-45 75 27)" fill="#ea580c"/>
                        <!-- Center dot -->
                        <circle cx="60" cy="60" r="6" fill="#f97316"/>
                    </g>
                    <!-- Small satellite gear -->
                    <g class="gear-small" style="transform-origin: 95px 30px;">
                        <circle cx="95" cy="30" r="12" fill="none" stroke="#f59e0b" stroke-width="4"/>
                        <circle cx="95" cy="30" r="4" fill="#f59e0b"/>
                        <!-- Mini teeth -->
                        <rect x="92.5" y="15" width="5" height="8" rx="2.5" fill="#f59e0b"/>
                        <rect x="92.5" y="37" width="5" height="8" rx="2.5" fill="#f59e0b"/>
                        <rect x="80" y="27.5" width="8" height="5" rx="2.5" fill="#f59e0b"/>
                        <rect x="102" y="27.5" width="8" height="5" rx="2.5" fill="#f59e0b"/>
                    </g>
                </svg>
            </div>

            <!-- Badge -->
            <div class="code">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
                    <circle cx="5" cy="5" r="5">
                        <animate attributeName="opacity" values="1;0.3;1" dur="1.5s" repeatCount="indefinite"/>
                    </circle>
                </svg>
                503 — Service Unavailable
            </div>

            <h1>ระบบอยู่ระหว่าง<br>ปรับปรุงชั่วคราว</h1>

            <p class="description">
                เราขออภัยในความไม่สะดวก ทีมงานกำลังปรับปรุงระบบให้ดีขึ้น<br>
                เพื่อประสบการณ์การใช้งานที่ดีกว่าเดิม
            </p>

            <!-- ETA -->
            <div class="eta-card">
                <div class="eta-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="eta-label">คาดว่าระบบจะกลับมาใน</div>
                    <div class="eta-value">ไม่นานนี้ — กรุณาลองใหม่อีกครั้ง</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions">
                <button class="btn-reload" onclick="location.reload()">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    ลองใหม่อีกครั้ง
                </button>
                <a href="mailto:support@example.com" class="btn-contact">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    ติดต่อทีมงาน
                </a>
            </div>

            <div class="footer">
                UniActivity &mdash; ระบบกิจกรรมนักศึกษา
            </div>
        </div>
    </div>

    <script>
        // Auto-reload ทุก 30 วินาที
        let countdown = 30;
        setTimeout(function tryReload() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
