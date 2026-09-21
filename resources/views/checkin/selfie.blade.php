<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ยืนยันตัวตน — สแกนใบหน้า</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ file_exists(public_path('css/app.css')) ? md5_file(public_path('css/app.css')) : time() }}">
    <link rel="stylesheet" href="{{ asset('css/face-scan-animation.css') }}?v=1">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy: #0a1628; --navy-mid: #0f2040;
            --blue: #2563eb; --blue-light: #3b82f6; --blue-glow: rgba(37,99,235,0.35);
            --green: #10b981; --amber: #f59e0b; --red: #ef4444;
            --white: #ffffff; --white-60: rgba(255,255,255,0.6);
            --white-15: rgba(255,255,255,0.15); --white-08: rgba(255,255,255,0.08);
            --panel-border: rgba(37,99,235,0.25);
        }
        html, body { width:100%; height:100%; overflow:hidden; font-family:'Inter','Sarabun',sans-serif; background:var(--navy); color:var(--white); -webkit-font-smoothing:antialiased; }
        .scan-shell { display:block; position:fixed; inset:0; width:100vw; height:100vh; height:100dvh; overflow:hidden; }
        /* Full-screen camera on EVERY device (phone + tablet + desktop):
           the old split side-panel layout is retired; the camera fills the
           viewport and all controls float over it. */
        .camera-area { position:absolute; inset:0; background:#000; }
        .side-panel { display:none !important; }

        /* Desktop / tablet split-panel media queries removed — full-screen for all */

        #cameraPreview { width:100%; height:100%; object-fit:cover; transform:scaleX(-1); display:block; image-rendering:-webkit-optimize-contrast; }

        .panel-logo { display:flex; align-items:center; gap:0.75rem; padding-bottom:0.25rem; }
        .panel-logo-icon { width:40px; height:40px; background:var(--blue); border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 20px var(--blue-glow); flex-shrink:0; }
        .panel-logo-text { font-size:0.8rem; color:var(--white-60); line-height:1.5; }
        .panel-logo-name { font-size:1rem; font-weight:700; color:var(--white); display:block; }
        .panel-divider { height:1px; background:var(--panel-border); }
        .panel-section {}
        .panel-section-label { font-size:0.65rem; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:var(--blue-light); margin-bottom:0.6rem; display:block; }
        .back-link { display:inline-flex; align-items:center; gap:0.4rem; color:var(--white-60); font-size:0.85rem; text-decoration:none; padding:0.45rem 0.75rem; border-radius:8px; border:1px solid var(--white-15); transition:background 0.2s,color 0.2s; }
        .back-link:hover { background:var(--white-08); color:var(--white); }
        .activity-card { background:var(--white-08); border:1px solid var(--panel-border); border-radius:12px; padding:1rem; }
        .activity-title { font-size:0.95rem; font-weight:600; color:var(--white); margin-bottom:0.5rem; line-height:1.5; }
        .activity-meta { display:flex; flex-direction:column; gap:0.35rem; }
        .activity-meta-row { display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--white-60); }
        .activity-meta-row svg { flex-shrink:0; opacity:0.7; }

        /* ── Full-screen HUD (floating controls, all devices) ── */
        .mobile-header {
            display: flex;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 30;
            padding: 1rem 1.25rem;
            padding-top: calc(0.9rem + env(safe-area-inset-top, 0px));
            background: linear-gradient(to bottom, rgba(5, 11, 24, 0.78) 0%, transparent 100%);
            align-items: center;
            gap: 0.85rem;
            pointer-events: auto;
        }
        .mobile-back-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.16);
            color: #ffffff;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: background 0.2s, transform 0.15s;
            flex-shrink: 0;
        }
        .mobile-back-btn:active { transform: scale(0.94); }
        .mobile-header-info { flex: 1; min-width: 0; }
        .mobile-header-label { font-size: 0.65rem; color: rgba(255, 255, 255, 0.55); letter-spacing: 0.08em; text-transform: uppercase; font-weight: 600; }
        .mobile-header-title { font-size: 0.95rem; font-weight: 600; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ── Floating Percentage Pill HUD ── */
        .mobile-bottom {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: auto;
            z-index: 30;
            padding-bottom: calc(1.5rem + env(safe-area-inset-bottom, 0px));
            pointer-events: auto;
        }
        .hud-score-pill {
            background: rgba(10, 20, 38, 0.82);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1.5px solid rgba(255, 255, 255, 0.16);
            border-radius: 9999px;
            padding: 0.55rem 1.6rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.12);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hud-score-pill.score-pass {
            border-color: rgba(52, 211, 153, 0.85);
            background: rgba(6, 30, 24, 0.85);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), 0 0 24px rgba(52, 211, 153, 0.4);
            transform: scale(1.06);
        }
        #scoreValuePanel {
            font-size: 1.45rem;
            font-weight: 700;
            color: #e2e8f0;
            letter-spacing: 0.02em;
            min-width: 48px;
            text-align: center;
            line-height: 1.2;
            transition: color 0.25s, transform 0.2s;
        }

        /* ── Toast notifications (top, stacked) ── */
        #toastStack { position:fixed; top:calc(0.7rem + env(safe-area-inset-top,0px)); left:50%; transform:translateX(-50%); z-index:10001; display:flex; flex-direction:column; gap:0.5rem; width:min(92vw,460px); pointer-events:none; }
        .toast { display:flex; align-items:center; gap:0.6rem; background:rgba(10,22,40,0.92); border:1px solid var(--white-15); border-left:4px solid var(--blue-light); border-radius:12px; padding:0.7rem 0.95rem; font-size:0.82rem; color:var(--white); backdrop-filter:blur(14px); box-shadow:0 8px 30px rgba(0,0,0,0.45); opacity:0; transform:translateY(-12px); transition:opacity 0.28s ease, transform 0.28s ease; }
        .toast.toast-in { opacity:1; transform:translateY(0); }
        .toast.toast-out { opacity:0; transform:translateY(-10px); }
        .toast-icon { flex-shrink:0; display:flex; }
        .toast-success { border-left-color:#34d399; } .toast-success .toast-icon { color:#34d399; }
        .toast-error { border-left-color:#f87171; } .toast-error .toast-icon { color:#f87171; }
        .toast-warning { border-left-color:#fbbf24; } .toast-warning .toast-icon { color:#fbbf24; }
        .toast-info { border-left-color:#60a5fa; } .toast-info .toast-icon { color:#60a5fa; }

        .btn-manual { display:none; width:100%; padding:0.7rem 1.25rem; border-radius:10px; border:1px solid var(--white-15); background:var(--white-08); color:var(--white); font-size:0.875rem; font-weight:500; cursor:pointer; transition:background 0.2s; font-family:inherit; }
        .btn-manual:hover { background:rgba(255,255,255,0.12); }

        /* ── Face Guide Oval (Biometric FaceID aesthetic) ── */
        #faceGuide {
            position: absolute;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            width: 250px;
            height: 330px;
            border-radius: 125px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 4000px rgba(5, 12, 24, 0.52);
            transition: border-color 0.4s ease, box-shadow 0.5s ease;
            overflow: hidden;
            z-index: 10;
        }
        .scan-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent 5%, rgba(56, 189, 248, 0.85) 50%, transparent 95%);
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.7);
            animation: scanMove 2.6s ease-in-out infinite;
            z-index: 20;
        }
        @keyframes scanMove { 0% { top: 6%; opacity: 0; } 12% { opacity: 1; } 88% { opacity: 1; } 100% { top: 94%; opacity: 0; } }

        .corner { position: absolute; width: 26px; height: 26px; border-color: rgba(56, 189, 248, 0.9); border-style: solid; border-width: 0; transition: border-color 0.3s; }
        .corner-tl { top: 0; left: 0; border-top-width: 2.5px; border-left-width: 2.5px; border-top-left-radius: 110px; }
        .corner-tr { top: 0; right: 0; border-top-width: 2.5px; border-right-width: 2.5px; border-top-right-radius: 110px; }
        .corner-bl { bottom: 0; left: 0; border-bottom-width: 2.5px; border-left-width: 2.5px; border-bottom-left-radius: 110px; }
        .corner-br { bottom: 0; right: 0; border-bottom-width: 2.5px; border-right-width: 2.5px; border-bottom-right-radius: 110px; }
        .grid-overlay, .face-detection-points, #faceLandmarksCanvas, #scanStatus { display:none !important; }

        .scanning-ring {
            border-color: rgba(56, 189, 248, 0.85) !important;
            animation: guidePulse 2.4s ease-in-out infinite;
        }
        @keyframes guidePulse {
            0%, 100% { box-shadow: 0 0 0 4000px rgba(5, 12, 24, 0.52), 0 0 18px rgba(56, 189, 248, 0.22); }
            50% { box-shadow: 0 0 0 4000px rgba(5, 12, 24, 0.52), 0 0 32px rgba(56, 189, 248, 0.45); }
        }
        .scanning-ring .corner { border-color: rgba(56, 189, 248, 0.9) !important; }

        .success-ring {
            border-color: rgba(52, 211, 153, 0.95) !important;
            box-shadow: 0 0 0 4000px rgba(5, 12, 24, 0.65), 0 0 40px rgba(52, 211, 153, 0.5) !important;
            animation: successPulse 0.5s ease-out !important;
        }
        .success-ring .corner { border-color: rgba(52, 211, 153, 0.95) !important; }
        .success-ring .scan-line { background: linear-gradient(90deg, transparent, rgba(52, 211, 153, 0.85), transparent); box-shadow: 0 0 10px rgba(52, 211, 153, 0.7); }
        @keyframes successPulse { 0% { transform: translate(-50%, -50%) scale(0.97); } 50% { transform: translate(-50%, -50%) scale(1.02); } 100% { transform: translate(-50%, -50%) scale(1); } }

        .warning-ring {
            border-color: rgba(251, 191, 36, 0.85) !important;
            box-shadow: 0 0 0 4000px rgba(5, 12, 24, 0.55), 0 0 22px rgba(251, 191, 36, 0.3) !important;
        }
        .warning-ring .corner { border-color: rgba(251, 191, 36, 0.85) !important; }
        .warning-ring .scan-line { background: linear-gradient(90deg, transparent, rgba(251, 191, 36, 0.75), transparent); box-shadow: 0 0 8px rgba(251, 191, 36, 0.5); }

        .error-ring {
            border-color: rgba(239, 68, 68, 0.9) !important;
            box-shadow: 0 0 0 4000px rgba(5, 12, 24, 0.65), 0 0 30px rgba(239, 68, 68, 0.35) !important;
        }
        .error-ring .corner { border-color: rgba(239, 68, 68, 0.9) !important; }

        @media (max-width: 480px) {
            #faceGuide { width: 220px; height: 290px; border-radius: 110px; }
            .corner { width: 22px; height: 22px; }
            .mobile-bottom { width: min(92vw, 380px); }
        }

        .mobile-status-box { background:rgba(10,22,40,0.75);border:1px solid var(--white-15);border-radius:14px;padding:0.85rem 1rem;backdrop-filter:blur(12px);text-align:center; }
        .mobile-status-text { font-size:0.875rem;font-weight:500;color:var(--white);line-height:1.5; }
        .mobile-score-row { display:none;align-items:center;justify-content:center;gap:0.5rem;margin-top:0.4rem; }
        .mobile-score-val { font-size:1rem;font-weight:700; }
        .mobile-score-label { font-size:0.75rem;color:var(--white-60); }

        #comparisonResult { display:none;position:absolute;inset:0;z-index:40;background:rgba(10,22,40,0.94);backdrop-filter:blur(16px);flex-direction:column;align-items:center;justify-content:center;padding:2rem 1.5rem;text-align:center;pointer-events:auto; }
        .comparison-faces { display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem; }
        .comparison-face { display:flex;flex-direction:column;align-items:center;gap:0.5rem; }
        .comparison-face img, .comparison-face canvas { width:88px;height:88px;border-radius:50%;object-fit:cover;border:2.5px solid var(--blue);box-shadow:0 0 20px var(--blue-glow); }
        .comparison-face-label { font-size:0.75rem;color:var(--white-60); }
        .comparison-arrow { color:var(--white-60);font-size:1.5rem; }
        .comparison-score-text { font-size:2.5rem;font-weight:700;letter-spacing:-0.02em;margin-bottom:0.25rem; }
        .comparison-status-text { font-size:0.95rem;color:var(--white-60);margin-bottom:2rem;max-width:320px; }
        .btn-submit { width:100%;max-width:320px;padding:0.9rem 2rem;border-radius:12px;border:none;background:var(--blue);color:var(--white);font-size:1rem;font-weight:600;cursor:pointer;box-shadow:0 4px 20px var(--blue-glow);transition:transform 0.15s,box-shadow 0.15s,background 0.2s;font-family:inherit; }
        .btn-submit:hover:not(:disabled) { background:var(--blue-light);box-shadow:0 6px 28px rgba(59,130,246,0.5); }
        .btn-submit:active:not(:disabled) { transform:scale(0.97); }
        .btn-submit:disabled { background:rgba(255,255,255,0.1);box-shadow:none;cursor:not-allowed;color:var(--white-60); }

        .error-modal-backdrop { position:fixed;inset:0;background:rgba(10,22,40,0.7);backdrop-filter:blur(8px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem; }
        .error-modal-box { background:#111d32;border:1px solid rgba(239,68,68,0.3);border-radius:20px;padding:2rem 1.5rem;max-width:380px;width:100%;text-align:center;animation:modalIn 0.3s ease-out;box-shadow:0 20px 60px rgba(0,0,0,0.5); }
        @keyframes modalIn { from{transform:scale(0.9);opacity:0} to{transform:scale(1);opacity:1} }
        .error-modal-icon { width:72px;height:72px;border-radius:50%;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;color:var(--red); }
        .error-modal-title { font-size:1.2rem;font-weight:700;color:var(--red);margin-bottom:0.5rem; }
        .error-modal-body { font-size:0.9rem;color:var(--white-60);margin-bottom:1.5rem;line-height:1.5; }
        .btn-error-close { display:block;width:100%;padding:0.8rem;border-radius:10px;border:none;background:var(--red);color:var(--white);font-size:0.95rem;font-weight:600;cursor:pointer;font-family:inherit; }

        .no-profile-warning { position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(10,22,40,0.9);border:1px solid rgba(245,158,11,0.4);border-radius:16px;padding:1.25rem 1.5rem;max-width:340px;width:calc(100% - 2rem);z-index:50;text-align:center;backdrop-filter:blur(12px); }
        .no-profile-warning-title { display:flex;align-items:center;justify-content:center;gap:0.5rem;font-size:0.9rem;font-weight:600;color:var(--amber);margin-bottom:0.5rem; }
        .no-profile-warning-text { font-size:0.8rem;color:var(--white-60);line-height:1.5; }

        .status-alert { display:none;padding:0.6rem 0.9rem;border-radius:10px;font-size:0.82rem;font-weight:600;text-align:center; }
        .status-alert.error { background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.35);color:#fca5a5; }

        .spinner { display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.2);border-top-color:var(--blue-light);border-radius:50%;animation:spin 0.7s linear infinite;vertical-align:middle;margin-right:4px; }
        @keyframes spin { to{transform:rotate(360deg)} }

        @media (min-width:640px) and (max-width:1023px) {
            .panel-section-label{margin-bottom:0.4rem}
            .activity-card{padding:0.75rem}
            .activity-title{font-size:0.85rem}
            .instructions-list{gap:0.45rem}
            .instructions-list li{font-size:0.75rem}
        }
    </style>
</head>
<body>
<div class="scan-shell">

    <!-- ── TOAST NOTIFICATIONS (top, stacked) ── -->
    <div id="toastStack" aria-live="polite"></div>

    <!-- ── SIDE PANEL ── -->
    <aside class="side-panel">
        <div class="panel-logo">
            <div class="panel-logo-icon">
                <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <span class="panel-logo-name">ยืนยันตัวตน</span>
                <span class="panel-logo-text">ระบบสแกนใบหน้าอัตโนมัติ</span>
            </div>
        </div>
        <div class="panel-divider"></div>
        <div class="panel-section">
            <a href="{{ route('activities.index') }}" class="back-link">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                กลับหน้ากิจกรรม
            </a>
        </div>
        <div class="panel-section">
            <span class="panel-section-label">กิจกรรม</span>
            <div class="activity-card">
                <p class="activity-title">{{ $activity->title }}</p>
                <div class="activity-meta">
                    <div class="activity-meta-row">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $activity->activity_date->format('d/m/Y') }}
                    </div>
                    <div class="activity-meta-row">
                        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ $activity->location }}
                    </div>
                </div>
            </div>
        </div>        <div class="panel-divider"></div>
        <div class="panel-section">
            <span class="panel-section-label">คำแนะนำ</span>
            <ul class="instructions-list">
                <li><span class="num">1</span>วางใบหน้าให้อยู่ในกรอบรูปรี</li>
                <li><span class="num">2</span>มองตรงมายังกล้อง อย่าก้มหรือเงยหน้า</li>
                <li><span class="num">3</span>ให้แสงสว่างเพียงพอ หลีกเลี่ยงแสงจ้าด้านหลัง</li>
                <li><span class="num">4</span>ถืออุปกรณ์ให้นิ่ง ห่างจากกล้องประมาณ 40–60 ซม.</li>
            </ul>
        </div>
    </aside>

    <!-- ── CAMERA AREA ── -->
    <div class="camera-area">
        <video id="cameraPreview" autoplay playsinline muted></video>
        <div id="faceGuide">
            <div class="scan-line"></div>
            <div class="corner corner-tl"></div>
            <div class="corner corner-tr"></div>
            <div class="corner corner-bl"></div>
            <div class="corner corner-br"></div>
        </div>
        <canvas id="captureCanvas" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transform:scaleX(-1);z-index:5;"></canvas>
        <div id="comparisonResult">
            <div class="comparison-faces">
                <div class="comparison-face">
                    <img id="profileThumb" src="{{ $profilePhotoUrl }}" alt="รูปโปรไฟล์">
                    <span class="comparison-face-label">รูปในระบบ</span>
                </div>
                <div class="comparison-arrow">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </div>
                <div class="comparison-face">
                    <canvas id="selfieThumb" width="88" height="88"></canvas>
                    <span class="comparison-face-label">Selfie</span>
                </div>
            </div>
            <p id="matchScoreText" class="comparison-score-text"></p>
            <p id="matchStatusText" class="comparison-status-text"></p>
            <button type="button" id="submitBtn" class="btn-submit" disabled>กำลังบันทึกข้อมูล...</button>
        </div>
        @if(!$profilePhotoUrl)
        <div class="no-profile-warning">
            <div class="no-profile-warning-title">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                ยังไม่มีภาพถ่ายโปรไฟล์ในระบบ
            </div>
            <p class="no-profile-warning-text">ระบบจะบันทึก Selfie ไว้แต่ไม่สามารถเปรียบเทียบใบหน้าได้ กรุณาอัปโหลดรูปโปรไฟล์ภายหลัง</p>
        </div>
        @endif
    </div>

    <!-- ── MOBILE HEADER ── -->
    <div class="mobile-header">
        <a href="{{ route('activities.index') }}" class="mobile-back-btn">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="mobile-header-info">
            <div class="mobile-header-label">ยืนยันตัวตน</div>
            <div class="mobile-header-title">{{ $activity->title }}</div>
        </div>
    </div>

    <!-- ── MOBILE BOTTOM (Percentage Only Floating Pill) ── -->
    <div class="mobile-bottom">
        <div id="scoreDisplayPanel" class="hud-score-pill">
            <span id="scoreValuePanel">—</span>
        </div>
        <!-- Technical elements and status kept hidden for script safety -->
        <div style="display:none;" aria-hidden="true">
            <div id="statusChip"><span class="status-dot"></span><span id="statusChipText"></span></div>
            <div id="accuracyPanel">
                <div id="accuracyState"><span id="accuracyStateIcon"></span><span id="accuracyStateText"></span></div>
                <div id="accuracyBarFill"></div>
                <span id="accuracyAvg">0</span>
                <span id="accuracyCount">0</span>
                <span id="framesSentCount">0</span>
            </div>
            <button type="button" id="manualCaptureBtnMobile" onclick="capturePhoto(true)"></button>
        </div>
    </div>

</div>

@if(session('error'))
<div id="errorPopup" class="error-modal-backdrop">
    <div class="error-modal-box">
        <div class="error-modal-icon">
            <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <p class="error-modal-title">ไม่สามารถทำรายการได้</p>
        <p class="error-modal-body">{{ session('error') }}</p>
        <button type="button" class="btn-error-close" onclick="window.location.href='{{ route('activities.show', $activity->id) }}'">กลับไปหน้ากิจกรรม</button>
    </div>
</div>
<script>setTimeout(function() { window.location.href = "{{ route('activities.show', $activity->id) }}"; }, 5500);</script>
@endif

<form id="selfieForm" method="POST" action="{{ route('checkin.store', $token) }}" style="display:none;">
    @csrf
    <input type="hidden" name="latitude" id="qr_lat">
    <input type="hidden" name="longitude" id="qr_lng">
    <input type="hidden" name="selfie" id="selfieData">
</form>

<!-- Legacy hidden elements -->
<div id="realtimeScore" style="display:none;"></div>
<div id="livenessBadge" style="display:none;"></div>
<div id="statusMsg" style="display:none;"></div>
<div id="scanInstructions" style="display:none;">กำลังเชื่อมต่อกล้อง...</div>
<button id="manualCaptureBtn" style="display:none;" onclick="capturePhoto(true)"></button>

<script defer src="{{ asset('js/face-api.min.js') }}"></script>
<script>/* ── UI Bridge ── */
function setStatusChip(state, text) {
    var chip = document.getElementById('statusChip');
    var chipText = document.getElementById('statusChipText');
    if (!chip) return;
    if (chipText) chipText.textContent = text;
    var dot = chip.querySelector('.status-dot');
    if (dot) {
        dot.className = 'status-dot';
        if (state === 'connecting' || state === 'scanning') {
            dot.classList.add('dot-scanning', 'pulse');
        } else if (state === 'warning') {
            dot.classList.add('dot-warning');
        } else if (state === 'success') {
            dot.classList.add('dot-success');
        } else if (state === 'error') {
            dot.classList.add('dot-error');
        }
    }
    var m = document.getElementById('mobileStatusText');
    if (m) m.textContent = text;
    /* Toast notifications for important alerts */
    if (state === 'error' && text) showToast(text, 'error');
    else if (state === 'warning' && text) showToast(text, 'warning');
}
function setScore(score, color) {
    var panel = document.getElementById('scoreDisplayPanel');
    var val = document.getElementById('scoreValuePanel');
    if (panel) {
        panel.style.display = 'inline-flex';
        var num = parseInt(score, 10);
        if (!isNaN(num) && num >= 60) {
            panel.classList.add('score-pass');
        } else {
            panel.classList.remove('score-pass');
        }
    }
    if (val) {
        val.textContent = score;
        if (color) val.style.color = color;
    }
    var rt = document.getElementById('realtimeScore');
    if (rt) { rt.textContent = score; rt.style.color = color; }
}
/* ── Real-time accuracy bar ── */
var accHistory = [];
function updateAccuracy(pct) {
    var fill = document.getElementById('accuracyBarFill');
    var avgEl = document.getElementById('accuracyAvg');
    var cntEl = document.getElementById('accuracyCount');
    if (!fill) return;

    var THRESH = 60, NEAR = 50;

    if (pct === null || pct === undefined) {
        fill.style.width = '0%';
        fill.style.background = '#64748b';
        fill.style.boxShadow = 'none';
        return;
    }

    accHistory.push(pct);
    if (accHistory.length > 5) accHistory.shift();
    var avg = accHistory.reduce(function(a,b){return a+b;},0) / accHistory.length;

    var widthPct = Math.min(100, Math.max(0, pct));
    fill.style.width = widthPct.toFixed(1) + '%';

    if (pct >= THRESH) {
        fill.style.background = 'linear-gradient(90deg, #38bdf8, #34d399)';
        fill.style.boxShadow = '0 0 10px rgba(52, 211, 153, 0.5)';
    } else if (pct >= NEAR) {
        fill.style.background = 'linear-gradient(90deg, #38bdf8, #fbbf24)';
        fill.style.boxShadow = '0 0 8px rgba(251, 191, 36, 0.4)';
    } else {
        fill.style.background = 'linear-gradient(90deg, #38bdf8, #f87171)';
        fill.style.boxShadow = 'none';
    }
    if (avgEl) avgEl.textContent = avg.toFixed(1) + '%';
    if (cntEl) cntEl.textContent = String(accHistory.length);
}
function showAlert(msg, type) {
    /* Toast-based now: pop-ups appear stacked at the top of the screen.
       type: 'error' | 'success' | 'warning' | 'info' (default) */
    showToast(msg, type || 'info');
    var sm = document.getElementById('statusMsg');
    if (sm) { sm.textContent = msg; }
}
/* ── Toast system: pop-up notifications at the top of the screen ── */
var TOAST_ICONS = {
    success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>',
    error:   '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12"/></svg>',
    warning: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
    info:    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 12v4"/></svg>'
};
function showToast(msg, type) {
    type = type || 'info';
    var stack = document.getElementById('toastStack');
    if (!stack) { console.log('[toast:' + type + ']', msg); return; }
    var t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.setAttribute('role', 'status');
    t.innerHTML = '<span class="toast-icon">' + (TOAST_ICONS[type] || TOAST_ICONS.info) + '</span>' +
                  '<span class="toast-msg"></span>';
    t.querySelector('.toast-msg').textContent = msg;
    stack.appendChild(t);
    requestAnimationFrame(function(){ t.classList.add('toast-in'); });
    var ttl = (type === 'error') ? 6000 : 3500;
    setTimeout(function(){
        t.classList.add('toast-out');
        setTimeout(function(){ t.remove(); }, 320);
    }, ttl);
    /* cap stack height: at most 3 visible, oldest dropped first */
    while (stack.children.length > 3) { stack.removeChild(stack.firstChild); }
}
function showManualBtn(show) {
    ['manualCaptureBtnPanel','manualCaptureBtnMobile','manualCaptureBtn'].forEach(function(id) {
        var el = document.getElementById(id); if (el) el.style.display = show ? 'block' : 'none';
    });
}

var _scanEl = document.getElementById('scanInstructions');
if (_scanEl) {
    new MutationObserver(function() {
        var t = _scanEl.textContent.trim(); if (!t) return;
        if (t.indexOf('โหลด') !== -1 || t.indexOf('เชื่อม') !== -1) setStatusChip('connecting', t);
        else if (t.indexOf('แสง') !== -1) setStatusChip('warning', t);
        else setStatusChip('scanning', t);
    }).observe(_scanEl, {childList:true, characterData:true, subtree:true});
}
var _manualBtn = document.getElementById('manualCaptureBtn');
if (_manualBtn) {
    new MutationObserver(function() { showManualBtn(_manualBtn.style.display !== 'none'); })
        .observe(_manualBtn, {attributes:true, attributeFilter:['style']});
}

/* ── Original Logic ── */
var faceScanMethod = '{{ $faceScanMethod ?? "hybrid" }}';
var isJsModeActive = (faceScanMethod === 'js');
var profileDescriptor = null, pythonFailCount = 0, pythonThrottledUntil = 0, isFaceApiLoaded = false, framesSent = 0;
/* ── Scan diagnostics beacon: makes client-side deaths visible server-side.
   Fire-and-forget posts to /api/face/scan-beacon (Log::info, no DB). ── */
function beacon(type, extra) {
    try {
        fetch('/api/face/scan-beacon',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json','X-CSRF-TOKEN':(document.querySelector('meta[name="csrf-token"]')||{getAttribute:function(){return '';}}).getAttribute('content')},body:JSON.stringify(Object.assign({type:type,frames:framesSent},extra||{}))}).catch(function(){});
    } catch(e){}
}
window.addEventListener('error',function(e){beacon('js_error',{msg:String(e.message).slice(0,200),source:(e.filename||'').split('/').pop()+':'+e.lineno});},true);
window.addEventListener('unhandledrejection',function(e){beacon('promise_rejection',{msg:String(e.reason).slice(0,200)});},true);

var audioContext = new (window.AudioContext || window.webkitAudioContext)();
function playScanSound() {
    var o=audioContext.createOscillator(),g=audioContext.createGain();
    o.connect(g);g.connect(audioContext.destination);
    o.frequency.value=1200;o.type='sine';g.gain.value=0.04;
    o.start();setTimeout(function(){o.stop();},40);
}
function playSuccessSound() {
    [523.25,659.25,783.99].forEach(function(freq,i){
        setTimeout(function(){var o=audioContext.createOscillator(),g=audioContext.createGain();o.connect(g);g.connect(audioContext.destination);o.frequency.value=freq;o.type='sine';g.gain.value=0.08;o.start();setTimeout(function(){o.stop();},150);},i*150);
    });
}
function playErrorSound() {
    var o=audioContext.createOscillator(),g=audioContext.createGain();
    o.connect(g);g.connect(audioContext.destination);
    o.frequency.value=200;o.type='sawtooth';g.gain.value=0.08;
    o.start();setTimeout(function(){o.stop();},200);
}

/* SmartFaceScanner removed: the class was referenced but never shipped,
   so every init attempt only produced a console warning and all its
   call sites were dead branches around the legacy scan loop. */

var detectionInterval=null,isScanningActive=true;
function initFaceLandmarksCanvas() {}
async function detectAndDrawFace() {}
function updateRealFaceDetectionPoints(landmarks) {}
function updateGuideFramePosition(box) {}
function resetGuideToCenter() {}
function startRealtimeDetection() {}
function stopRealtimeDetection() {}

/**
 * FaceGate — lightweight TinyFaceDetector pre-flight before Python /verify.
 *
 * Returns { detected: bool, confidence: float 0-1, quality: float 0-1 }.
 * Reuses the _faceDetCache from detectAndDrawFace() when the cached result
 * is < CACHE_MS old (avoids double GPU inference in the same frame burst).
 * Fail-open: any error or face-api not loaded → { detected:true } so the
 * scan proceeds exactly as before the gate was introduced.
 *
 * quality = (face-box area / frame area) — at 0.03 only an absent or
 * postage-stamp-sized face is blocked; a normal selfie fills 20-60%.
 */
async function checkFacePresence(canvas) {
    if(!isFaceApiLoaded||!window.faceapi) return {detected:true,confidence:1,quality:1};
    try {
        var now=Date.now(),cachedDet=null;
        /* Reuse 250ms-loop result if fresh */
        if(_faceDetCache.result!==undefined&&(now-_faceDetCache.ts)<_faceDetCache.CACHE_MS){
            cachedDet=_faceDetCache.result; /* null = no face, object = detection */
        } else {
            /* Run TinyFaceDetector on the already-captured scan canvas */
            var raw=await faceapi.detectSingleFace(canvas,new faceapi.TinyFaceDetectorOptions({inputSize:160,scoreThreshold:0.4}));
            _faceDetCache.result=raw||null;
            _faceDetCache.ts=Date.now();
            cachedDet=_faceDetCache.result;
        }
        if(!cachedDet) return {detected:false,confidence:0,quality:0};
        var box=cachedDet.box;
        var frameArea=canvas.width*canvas.height;
        var quality=frameArea>0?(box.width*box.height)/frameArea:0;
        return {detected:true,confidence:cachedDet.score||0.5,quality:quality};
    } catch(e){
        console.warn('[FaceGate] fail-open:',e);
        return {detected:true,confidence:0.5,quality:0.5};
    }
}

async function initFaceApi() {
    if(isFaceApiLoaded)return;
    var scanEl=document.getElementById('scanInstructions');
    if(scanEl)scanEl.innerHTML='<span class="spinner"></span> กำลังโหลดโมเดล AI...';
    setStatusChip('connecting','กำลังโหลดโมเดล AI...');
    try {
        var MODEL_URL='/models';
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
        var preComputed={!! $profileJsDescriptor ?? 'null' !!};
        if(preComputed){
            profileDescriptor=new Float32Array(Object.values(preComputed));
        } else {
            var profileUrl='{{ $profilePhotoUrl }}';
            if(profileUrl){var img=await faceapi.fetchImage(profileUrl);var det=await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();if(det){profileDescriptor=det.descriptor;fetch('{{ route("profile.save_js_descriptor") }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content')},body:JSON.stringify({descriptor:Array.from(profileDescriptor)})}).catch(function(){});}}
        }
        isFaceApiLoaded=true;
        setTimeout(function(){initFaceLandmarksCanvas();startRealtimeDetection();},500);
        if(scanEl)scanEl.textContent='กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
    } catch(e){
        console.error('FaceAPI Load Error',e);
        if(scanEl)scanEl.textContent='ไม่สามารถโหลดระบบสำรองได้';
        setStatusChip('error','ไม่สามารถโหลดระบบ AI ได้');
    }
}
document.addEventListener('DOMContentLoaded',function(){initFaceApi();});

var stream=null,scanTimeout=null,scanAttempts=0,cameraNoSignal=0;
var MAX_ATTEMPTS=15,THRESHOLD=60;
var isVerifying=false,stopScanning=false,isFlashOn=false;
var lastFrameAt=0,frameStartedAt=0;
/* ── FaceGate globals ── */
var noFaceFrames=0;           /* consecutive frames without a detected face */
/* Minimum ratio of detection-box area to frame area. At 0.03 (3%) only a
   very small or clearly-absent face is blocked; a normal selfie fills
   20–60% of the frame and always passes. Fail-open: if face-api is not
   loaded, the gate is bypassed entirely (same behaviour as before). */
var FACE_GATE_MIN_QUALITY=0.03;
var FACE_GATE_TOAST_AFTER=3;   /* consecutive no-face frames → toast nudge */
var FACE_GATE_BACKOFF_AFTER=5; /* consecutive no-face frames → interval increase */
/* Shared detection cache: detectAndDrawFace (250ms loop) writes here so
   checkFacePresence can skip a second TinyFaceDetector call if the cached
   result is fresh (< FACE_CACHE_MS). This halves inference work when both
   timers happen to fire within the same animation frame. */
var _faceDetCache={result:null,ts:0,CACHE_MS:200};
var SCAN_UI_BUILD='b8';/* marker sent with every verify — lets the server expose stale cached pages */

document.addEventListener('DOMContentLoaded', async function(){
    @if(session('error'))
    stopScanning=true;
    var guide=document.getElementById('faceGuide');if(guide)guide.style.display='none';
    return;
    @endif
    var preComputed={!! $profileJsDescriptor ?? 'null' !!};
    if(preComputed){profileDescriptor={embedding_128d:new Float32Array(Object.values(preComputed)),embedding_512d:null};}
    await startCamera();
    var guide=document.getElementById('faceGuide');if(guide)guide.classList.add('scanning-ring');
    var scanEl=document.getElementById('scanInstructions');if(scanEl)scanEl.textContent='กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
    console.log('[scan-ui] build '+SCAN_UI_BUILD+' loaded');
    var stEl=document.getElementById('scanStatus');if(stEl)stEl.innerHTML='<span style="color:#34d399;">AI Server</span>';
    setStatusChip('scanning','กำลังสแกนใบหน้า...');
    showLargeScreenTips();
    scanTimeout=setTimeout(scanFrame,1000);
});

async function startCamera() {
    try {
        var isLargeScreen=window.innerWidth>=1024||window.innerHeight>=768;
        var isMobile=/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        var constraints={
            video:{
                facingMode:'user',
                width:{ideal:1280,min:640},
                height:{ideal:720,min:480},
                frameRate:{ideal:30,min:15}
            },
            audio:false
        };
        try{
            stream=await navigator.mediaDevices.getUserMedia(constraints);
        }catch(e){
            try{
                stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:1280},height:{ideal:720}},audio:false});
            }catch(e2){
                stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});
            }
        }
        var video=document.getElementById('cameraPreview');
        video.srcObject=stream;
        video.onloadedmetadata=function(){adjustUIForScreenSize(isLargeScreen,isMobile);};
    } catch(e){showStatus('ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตให้ใช้กล้องในเบราว์เซอร์','error');setStatusChip('error','ไม่สามารถเปิดกล้องได้');}
}
function adjustUIForScreenSize(isLargeScreen,isMobile) {
    var faceGuide=document.getElementById('faceGuide'),video=document.getElementById('cameraPreview');
    video.style.objectFit='cover';video.style.objectPosition='center';video.style.transform='scaleX(-1)';
    if(isLargeScreen&&!isMobile){if(faceGuide){faceGuide.style.width='290px';faceGuide.style.height='380px';}}
    else{if(faceGuide){faceGuide.style.width='230px';faceGuide.style.height='300px';}video.style.transformOrigin='center';}
}
function showLargeScreenTips() {
    var isLargeScreen=window.innerWidth>=1024,isMobile=/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var el=document.getElementById('scanInstructions');
    var hint=isLargeScreen&&!isMobile?'คำแนะนำ: นั่งห่างจากกล้องประมาณ 60–80 ซม.':'คำแนะนำ: ถืออุปกรณ์ให้มั่นคง มองตรงมายังกล้อง';
    setTimeout(function(){if(el&&scanAttempts<=2){el.textContent=hint;setStatusChip('scanning',hint);setTimeout(function(){if(el&&!stopScanning){el.textContent='กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';setStatusChip('scanning','กำลังสแกนใบหน้า...');}},3500);}},2000);
}

/* Pacemaker: rescues any wedged scan state within seconds and keeps
   frames flowing at ~1 Hz regardless of individual handler bugs. */
setInterval(function(){
    if(stopScanning)return;
    if(scanTimeout===null){scanTimeout=setTimeout(scanFrame,50);}
},2000);

async function restartCamera() {
    try{ if(stream){stream.getTracks().forEach(function(t){t.stop();});} }catch(e){}
    stream=null;
    try{ await startCamera(); }catch(e){ console.warn('Camera restart failed:',e); }
}
async function scanFrame() {
    /* Pacemaker contract: scanTimeout===null means "no timer pending".
       The heartbeat watchdog re-invokes this function every 2s whenever
       that holds, so the loop can never die permanently — even if an
       await wedges, a timer is lost, or the camera hiccups. */
    scanTimeout=null;lastFrameAt=Date.now();
    if(isVerifying){
        if(Date.now()-frameStartedAt<15000)return;/* in-flight — heartbeat re-checks */
        isVerifying=false;/* hard-unlock a wedged request */
        beacon('pacemaker_rescue',{note:'request-wedged'});
        showToast('ระบบกู้คืนการสแกนอัตโนมัติ','warning');
    }
    if(stopScanning){beacon('loop_stopped',{note:'stopScanning'});return;}
    var video=document.getElementById('cameraPreview');
    // ── Camera health: Android suspends the stream on tab-switch, which
    // used to silently no-op every scan (UI frozen at the last score).
    var tracksDead=!stream||(stream.getVideoTracks&&stream.getVideoTracks().length>0&&stream.getVideoTracks()[0].readyState!=='live');
    if(video&&video.videoWidth>0){cameraNoSignal=0;}
    else{cameraNoSignal++;}
    if(tracksDead||cameraNoSignal>10){
        cameraNoSignal=0;
        beacon('camera_restart',{note:'tracksDead='+tracksDead+',noSignal='+cameraNoSignal});
        setStatusChip('warning','กล้องไม่พร้อม — กำลังเชื่อมต่อใหม่...');
        restartCamera();
        scanTimeout=setTimeout(scanFrame,2500);
        return;
    }
    if(!stream){beacon('loop_blocked',{note:'no-stream'});return;}
    if(video.videoWidth===0){beacon('loop_blocked',{note:'video-zero'});scanTimeout=setTimeout(scanFrame,1000);return;}
    isVerifying=true;frameStartedAt=Date.now();scanAttempts++;
    if(scanAttempts%3===0)playScanSound();
    try {
        await legacyScanFrame(video);
    } catch(error){console.error('Scan error:',error);}
    finally {
        isVerifying=false;
        var throttled=Date.now()<pythonThrottledUntil;
        var nextInterval=throttled?Math.max(400,pythonThrottledUntil-Date.now()+100):350;
        if(!stopScanning)scanTimeout=setTimeout(scanFrame,nextInterval);
    }
}
async function processScanResult(result) {
    if(!result.passed) return;
    stopScanning=true;isScanningActive=false;
    if(scanTimeout){clearTimeout(scanTimeout);scanTimeout=null;}
    var guide=document.getElementById('faceGuide');
    if(guide)guide.classList.replace('scanning-ring','success-ring');
    setStatusChip('success','ยืนยันตัวตนสำเร็จ!');
    playSuccessSound();
    capturePhoto(true);
}
async function legacyScanFrame(video) {
    if(stopScanning) return;
    var MAX_DIM=720,tw=video.videoWidth,th=video.videoHeight;
    if(tw>th){if(tw>MAX_DIM){th=Math.round(th*(MAX_DIM/tw));tw=MAX_DIM;}}else{if(th>MAX_DIM){tw=Math.round(tw*(MAX_DIM/th));th=MAX_DIM;}}
    var canvas=document.createElement('canvas');canvas.width=tw;canvas.height=th;
    var ctx=canvas.getContext('2d');
    ctx.imageSmoothingEnabled=true;
    ctx.imageSmoothingQuality='high';
    ctx.translate(canvas.width,0);ctx.scale(-1,1);ctx.drawImage(video,0,0,canvas.width,canvas.height);ctx.setTransform(1,0,0,1,0,0);
    handleLowLightDetection(ctx,canvas);

    var base64Image=canvas.toDataURL('image/jpeg',0.80);
    if(stopScanning) return;
    if(isJsModeActive&&isFaceApiLoaded&&profileDescriptor&&profileDescriptor.embedding_128d){await performJsVerification(canvas);}
    else{await performPythonVerification(base64Image);}
}
function handleLowLightDetection(ctx,canvas) {
    try {
        var imageData=ctx.getImageData(0,0,canvas.width,canvas.height),data=imageData.data;
        var colorSum=0,samples=0;
        for(var i=0;i<data.length;i+=40){colorSum+=(data[i]*299+data[i+1]*587+data[i+2]*114)/1000;samples++;}
        var avgBrightness=colorSum/samples;
        var video=document.getElementById('cameraPreview'),guide=document.getElementById('faceGuide'),infoEl=document.getElementById('scanInstructions');
        if(avgBrightness<65)isFlashOn=true;if(avgBrightness>100)isFlashOn=false;
        if(isFlashOn){
            var boost=Math.min(1.25,80/Math.max(avgBrightness,20));
            if(video)video.style.filter='brightness('+boost.toFixed(2)+') contrast(1.05)';
            if(ctx&&video){ctx.filter='brightness('+boost.toFixed(2)+') contrast(1.05)';ctx.translate(canvas.width,0);ctx.scale(-1,1);ctx.drawImage(video,0,0,canvas.width,canvas.height);ctx.setTransform(1,0,0,1,0,0);ctx.filter='none';}
            if(guide)guide.style.boxShadow='0 0 0 4000px rgba(10,22,40,0.5)';
            if(infoEl&&!stopScanning){infoEl.textContent='สภาวะแสงน้อย — กำลังปรับความสว่างอัตโนมัติ...';setStatusChip('warning','แสงน้อยเกินไป — ปรับความสว่างอัตโนมัติ');}
        } else {
            if(video)video.style.filter='';
            if(guide)guide.style.boxShadow='';
            if(infoEl&&infoEl.textContent.indexOf('แสง')!==-1){infoEl.textContent='กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';setStatusChip('scanning','กำลังสแกนใบหน้า...');}
        }
    } catch(e){console.warn('Brightness check error',e);}
}
async function performJsVerification(canvas) {
    try {
        var det=await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
        var score=0,passed=false;
        if(det){var dist=faceapi.euclideanDistance(profileDescriptor.embedding_128d,det.descriptor);score=Math.max(0,(1-dist)*100);passed=dist<0.5;}
        setScore(score.toFixed(0)+'%',passed?'#34d399':'#fcd34d');
        if(passed)await processScanResult({confidence:score/100,passed:true,score:score,source:'js_primary',processingTime:Date.now()%1000});
    } catch(e){console.warn('JS verification error:',e);}
}
async function performPythonVerification(base64Image) {
    var timer=null;
    try {
        var t0=Date.now();
        /* Frame-sent counter: if this keeps rising while the score freezes,
           the loop is alive and frames die server-side; if it stops, the
           phone's renderer itself died. Visible in the HUD. */
        framesSent++;
        var fsEl=document.getElementById('framesSentCount');if(fsEl)fsEl.textContent=String(framesSent);
        var ctrl=(typeof AbortController!=='undefined')?new AbortController():null;
        if(ctrl)timer=setTimeout(function(){ctrl.abort();},20000);/* 20s: frame 1 cold path takes ~10s (gpu-pc warmup + tunnel); 10s aborted slow-but-alive requests every session */
        var hdrs={'Content-Type':'application/json','Accept':'application/json','X-Scan-UI':SCAN_UI_BUILD,'X-CSRF-TOKEN':(document.querySelector('meta[name="csrf-token"]')||{getAttribute:function(){return '';}}).getAttribute('content')};
        // Session-cookie auth (auth()->user() in the controller) — no Bearer
        // header: the old code crashed here when meta[name=api-token] was
        // absent ((null||{}).getAttribute is not a function), killing every
        // scan frame before it could reach /api/face/verify.
        var res=await fetch('/api/face/verify',{method:'POST',headers:hdrs,body:JSON.stringify({image:base64Image,mode:'python',priority:'accuracy'}),signal:ctrl?ctrl.signal:undefined});
        /* NOTE: abort timer stays armed through body parse — a stalled
           response BODY (tunnel buffering) must abort too, or this await
           hangs forever and the frame wedges (pacemaker rescues at 15s). */
        var result=await res.json();
        if(timer){clearTimeout(timer);timer=null;}
        var ms=Date.now()-t0;
        /* 429 — Rate limited: back off using retry_after from the already-parsed
           JSON body (result). Do NOT call res.json() a second time — the body
           stream is already consumed and a second call throws/hangs. */
        if(res.status===429){beacon('http_429',{});var ra=(result&&result.retry_after)||30;pythonThrottledUntil=Date.now()+ra*1000;console.warn('Rate limited — backing off '+ra+'s');setScore('—','#fbbf24');setStatusChip('scanning','สแกนถี่เกิน — พักสั้นแล้วสแกนต่อ...');updateAccuracy(null);return;}
        /* 503 — AI Server / tunnel unavailable: back off 60s to stop the
           retry storm. Without this the scan loop hammers the dead endpoint
           every 1s, flooding the console and wasting bandwidth. */
        if(res.status===503){beacon('http_503',{});var bo=60;pythonThrottledUntil=Date.now()+bo*1000;console.warn('AI Server unavailable (503) — backing off '+bo+'s');setScore('—','#f87171');setStatusChip('error','AI Server ออฟไลน์ — รอ '+bo+' วินาทีแล้วลองใหม่');updateAccuracy(null);if(pythonFailCount===0)showToast('AI Server ไม่พร้อมให้บริการชั่วคราว — ระบบจะลองใหม่อัตโนมัติ','error');pythonFailCount++;return;}
        if(!res.ok){beacon('http_error',{msg:'HTTP '+res.status});throw new Error('HTTP '+res.status);}
        beacon('response',{score:result.score_percentage||0,note:(result.success===false)?(result.status||'fail'):'ok'});
        if(result.success===false){
            if(result.status==='no_face'){
                setScore('—','#fca5a5');
                setStatusChip('warning','วางใบหน้าให้อยู่ในกรอบ...');
            } else {
                setScore('—','#fca5a5');
                setStatusChip('warning',result.message||'กำลังลองอีกครั้ง...');
            }
            var fg=document.getElementById('faceGuide');
            if(fg){fg.classList.remove('scanning-ring');if(!fg.classList.contains('warning-ring'))fg.classList.add('warning-ring');}
            console.log('[scan-ui] Frame ' + framesSent + ' -> ' + (result.status || result.message || 'no face'));
            updateAccuracy(null);
            return;
        }
        var fg=document.getElementById('faceGuide');
        if(fg){fg.classList.remove('warning-ring');if(!fg.classList.contains('scanning-ring'))fg.classList.add('scanning-ring');}
        pythonFailCount=0;
        var score=result.score_percentage||0,passed=result.is_match||false;
        console.log('[scan-ui] Frame ' + framesSent + ' -> ' + score.toFixed(1) + '% (match=' + passed + ')');
        var procTime=result.processing_ms||ms;
        setScore(score.toFixed(0)+'%',passed?'#34d399':'#fcd34d');
        updateAccuracy(score);
        if(passed)await processScanResult({confidence:score/100,passed:true,score:score,source:'python_primary',processingTime:procTime});
    } catch(e){
        if(timer){clearTimeout(timer);timer=null;}
        console.warn('Python verification failed:',e);pythonFailCount++;
        beacon('fetch_exception',{msg:String(e && e.message ? e.message : e).slice(0,200)});
        /* Visible failure — this path used to be silent and looked like a freeze */
        setStatusChip('error','เชื่อมต่อ AI Server ไม่สำเร็จ — กำลังลองใหม่...');
        if(pythonFailCount===2)showToast('การเชื่อมต่อ AI Server ขัดข้อง — ระบบกำลังลองใหม่อัตโนมัติ','error');
        /* No runtime switch to local JS mode: a silent switch made every scan
           "work once on the server, then vanish". The pacemaker loop retries
           python on the next frame instead. */
    }
}
async function loadJsDescriptorFromApi() {
    if(profileDescriptor&&profileDescriptor.embedding_128d)return true;
    try {
        var res=await fetch('/api/face/verify',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content'),'Accept':'application/json'},body:JSON.stringify({image:'placeholder',mode:'js'})});
        if(res.ok){var r=await res.json();if(r.success&&r.descriptor_128d){profileDescriptor=profileDescriptor||{};profileDescriptor.embedding_128d=new Float32Array(r.descriptor_128d);return true;}}
    } catch(e){console.warn('Failed to load JS descriptor from API:',e);}
    return false;
}

function capturePhoto(autoSubmit) {
    autoSubmit = autoSubmit === undefined ? false : autoSubmit;
    stopScanning=true;isScanningActive=false;clearTimeout(scanTimeout);stopRealtimeDetection();
    var video=document.getElementById('cameraPreview'),canvas=document.getElementById('captureCanvas');
    canvas.width=video.videoWidth;canvas.height=video.videoHeight;
    var ctx=canvas.getContext('2d');
    ctx.translate(canvas.width,0);ctx.scale(-1,1);ctx.drawImage(video,0,0,canvas.width,canvas.height);
    document.getElementById('selfieData').value=canvas.toDataURL('image/jpeg',0.8);
    canvas.style.display='block';
    document.getElementById('faceGuide').style.display='none';
    var thumb=document.getElementById('selfieThumb');thumb.getContext('2d').drawImage(canvas,0,0,thumb.width,thumb.height);
    if(autoSubmit){submitSelfie();}
    else{
        showComparisonResult(0,false);
        var btn=document.getElementById('submitBtn');btn.disabled=false;btn.textContent='บันทึกรูปนี้';btn.onclick=submitSelfie;
    }
}
function showComparisonResult(score,passed) {
    var mb=document.querySelector('.mobile-bottom');if(mb)mb.style.display='none';
    var resDiv=document.getElementById('comparisonResult');resDiv.style.display='flex';
    if(passed){document.getElementById('matchScoreText').textContent=score.toFixed(1)+'%';document.getElementById('matchScoreText').style.color='#34d399';document.getElementById('matchStatusText').textContent='ใบหน้าตรงกับรูปโปรไฟล์ (AI ยืนยันแล้ว)';}
    else{document.getElementById('matchScoreText').textContent='รอการตรวจสอบ';document.getElementById('matchScoreText').style.color='#fcd34d';document.getElementById('matchStatusText').textContent='ส่งรูปเพื่อให้เจ้าหน้าที่ตรวจสอบภายหลัง';}
}
function showStatus(msg,type) {
    type = type || 'info';
    showAlert(msg);
    var typeMap={error:'error',success:'success',info:'connecting'};
    setStatusChip(typeMap[type]||'connecting',msg);
}
function submitSelfie() {
    var btn=document.getElementById('submitBtn');btn.disabled=true;btn.textContent='กำลังบันทึก...';
    if(navigator.geolocation){navigator.geolocation.getCurrentPosition(function(pos){document.getElementById('qr_lat').value=pos.coords.latitude;document.getElementById('qr_lng').value=pos.coords.longitude;document.getElementById('selfieForm').submit();},function(){document.getElementById('selfieForm').submit();},{enableHighAccuracy:true,timeout:5000});}
    else{document.getElementById('selfieForm').submit();}
}
</script>
</body>
</html>
