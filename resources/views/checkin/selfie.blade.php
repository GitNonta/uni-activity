<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ยืนยันตัวตน — สแกนใบหน้า</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
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
        .scan-shell { display:flex; width:100vw; height:100vh; height:100dvh; position:relative; }

        /* ─ Desktop ─ */
        @media (min-width:1024px) {
            .scan-shell { flex-direction:row; }
            .side-panel { display:flex; flex-direction:column; width:340px; min-width:300px; max-width:380px; flex-shrink:0; background:var(--navy); border-right:1px solid var(--panel-border); padding:2rem 1.75rem; gap:1.5rem; z-index:20; overflow-y:auto; }
            .camera-area { flex:1; position:relative; overflow:hidden; background:#000; }
            .mobile-header, .mobile-bottom { display:none; }
        }
        /* ─ Tablet ─ */
        @media (min-width:640px) and (max-width:1023px) {
            .scan-shell { flex-direction:column; }
            .side-panel { display:flex; flex-direction:row; flex-wrap:wrap; gap:1rem; width:100%; order:2; padding:1.25rem 1.5rem; background:var(--navy); border-top:1px solid var(--panel-border); z-index:20; flex-shrink:0; max-height:40vh; overflow-y:auto; }
            .camera-area { flex:1; position:relative; overflow:hidden; background:#000; order:1; min-height:0; }
            .side-panel .panel-logo, .side-panel .panel-divider { display:none; }
            .side-panel .panel-section { flex:1 1 45%; min-width:200px; }
            .mobile-header, .mobile-bottom { display:none; }
        }
        /* ─ Mobile ─ */
        @media (max-width:639px) {
            .scan-shell { flex-direction:column; }
            .side-panel { display:none; }
            .camera-area { position:absolute; inset:0; background:#000; }
            .mobile-header { display:flex; position:absolute; top:0; left:0; right:0; z-index:30; padding:1rem 1.25rem; padding-top:calc(1rem + env(safe-area-inset-top,0px)); background:linear-gradient(to bottom,rgba(10,22,40,0.85),transparent); align-items:center; gap:0.75rem; pointer-events:auto; }
            .mobile-bottom { display:flex; flex-direction:column; position:absolute; bottom:0; left:0; right:0; z-index:30; padding:1.25rem 1.25rem calc(1.25rem + env(safe-area-inset-bottom,0px)); background:linear-gradient(to top,rgba(10,22,40,0.92) 60%,transparent); gap:0.75rem; pointer-events:auto; }
        }

        #cameraPreview { width:100%; height:100%; object-fit:cover; transform:scaleX(-1); display:block; }

        .panel-logo { display:flex; align-items:center; gap:0.75rem; padding-bottom:0.25rem; }
        .panel-logo-icon { width:40px; height:40px; background:var(--blue); border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 0 20px var(--blue-glow); flex-shrink:0; }
        .panel-logo-text { font-size:0.8rem; color:var(--white-60); line-height:1.3; }
        .panel-logo-name { font-size:1rem; font-weight:700; color:var(--white); display:block; }
        .panel-divider { height:1px; background:var(--panel-border); }
        .panel-section {}
        .panel-section-label { font-size:0.65rem; font-weight:600; letter-spacing:0.12em; text-transform:uppercase; color:var(--blue-light); margin-bottom:0.6rem; display:block; }
        .back-link { display:inline-flex; align-items:center; gap:0.4rem; color:var(--white-60); font-size:0.85rem; text-decoration:none; padding:0.45rem 0.75rem; border-radius:8px; border:1px solid var(--white-15); transition:background 0.2s,color 0.2s; }
        .back-link:hover { background:var(--white-08); color:var(--white); }
        .activity-card { background:var(--white-08); border:1px solid var(--panel-border); border-radius:12px; padding:1rem; }
        .activity-title { font-size:0.95rem; font-weight:600; color:var(--white); margin-bottom:0.5rem; line-height:1.4; }
        .activity-meta { display:flex; flex-direction:column; gap:0.35rem; }
        .activity-meta-row { display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; color:var(--white-60); }
        .activity-meta-row svg { flex-shrink:0; opacity:0.7; }

        .status-chip { display:inline-flex; align-items:center; gap:0.5rem; padding:0.55rem 1rem; border-radius:30px; font-size:0.85rem; font-weight:600; border:1px solid; transition:all 0.3s; width:100%; }
        .status-chip.connecting { background:rgba(37,99,235,0.12); border-color:rgba(37,99,235,0.35); color:var(--blue-light); }
        .status-chip.scanning { background:rgba(96,165,250,0.1); border-color:rgba(96,165,250,0.4); color:#93c5fd; }
        .status-chip.success { background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.4); color:#34d399; }
        .status-chip.warning { background:rgba(245,158,11,0.12); border-color:rgba(245,158,11,0.4); color:#fcd34d; }
        .status-chip.error { background:rgba(239,68,68,0.12); border-color:rgba(239,68,68,0.4); color:#fca5a5; }
        .status-dot { width:8px; height:8px; border-radius:50%; background:currentColor; flex-shrink:0; }
        .status-dot.pulse { animation:statusPulse 1.5s ease-in-out infinite; }
        @keyframes statusPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.4;transform:scale(0.7)} }

        .score-display { display:none; align-items:center; justify-content:space-between; background:var(--white-08); border:1px solid var(--panel-border); border-radius:10px; padding:0.75rem 1rem; font-size:0.85rem; margin-top:0.75rem; }
        .score-label { color:var(--white-60); }
        .score-value { font-weight:700; font-size:1rem; }

        .instructions-list { list-style:none; display:flex; flex-direction:column; gap:0.6rem; }
        .instructions-list li { display:flex; align-items:flex-start; gap:0.6rem; font-size:0.82rem; color:var(--white-60); line-height:1.4; }
        .instructions-list li .num { flex-shrink:0; width:20px; height:20px; border-radius:50%; background:rgba(37,99,235,0.2); border:1px solid rgba(37,99,235,0.4); color:var(--blue-light); font-size:0.7rem; font-weight:700; display:flex; align-items:center; justify-content:center; }

        .btn-manual { display:none; width:100%; padding:0.7rem 1.25rem; border-radius:10px; border:1px solid var(--white-15); background:var(--white-08); color:var(--white); font-size:0.875rem; font-weight:500; cursor:pointer; transition:background 0.2s; font-family:inherit; }
        .btn-manual:hover { background:rgba(255,255,255,0.12); }

        #faceGuide { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:260px; height:340px; border-radius:130px; border:2px solid rgba(96,165,250,0.7); box-shadow:0 0 0 4000px rgba(10,22,40,0.65),0 0 0 1px rgba(96,165,250,0.2),inset 0 0 30px rgba(96,165,250,0.05); transition:border-color 0.4s,box-shadow 0.6s; overflow:hidden; z-index:10; }
        .scan-line { position:absolute; width:100%; height:2px; background:linear-gradient(90deg,transparent 5%,rgba(96,165,250,0.8) 50%,transparent 95%); box-shadow:0 0 8px rgba(96,165,250,0.6); animation:scanMove 2.5s ease-in-out infinite; z-index:20; }
        @keyframes scanMove { 0%{top:5%;opacity:0} 10%{opacity:1} 90%{opacity:1} 100%{top:95%;opacity:0} }
        .corner { position:absolute; width:28px; height:28px; border-color:rgba(96,165,250,0.9); border-style:solid; border-width:0; transition:border-color 0.3s; }
        .corner-tl { top:0;left:0;border-top-width:2.5px;border-left-width:2.5px;border-top-left-radius:120px; }
        .corner-tr { top:0;right:0;border-top-width:2.5px;border-right-width:2.5px;border-top-right-radius:120px; }
        .corner-bl { bottom:0;left:0;border-bottom-width:2.5px;border-left-width:2.5px;border-bottom-left-radius:120px; }
        .corner-br { bottom:0;right:0;border-bottom-width:2.5px;border-right-width:2.5px;border-bottom-right-radius:120px; }
        .grid-overlay { position:absolute;inset:0;background-image:linear-gradient(rgba(96,165,250,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(96,165,250,0.06) 1px,transparent 1px);background-size:24px 24px;animation:gridPulse 4s ease-in-out infinite;z-index:10; }
        @keyframes gridPulse { 0%,100%{opacity:0.5} 50%{opacity:1} }
        .face-detection-points { position:absolute;inset:0;z-index:15; }
        .detection-point { position:absolute;width:3px;height:3px;background:rgba(96,165,250,0.9);border-radius:50%;animation:pointPulse 1.8s ease-in-out infinite; }
        @keyframes pointPulse { 0%,100%{opacity:0.3;transform:scale(1)} 50%{opacity:1;transform:scale(1.8)} }
        #faceLandmarksCanvas { position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:24; }
        #scanStatus { position:absolute;top:8px;left:8px;background:rgba(10,22,40,0.7);color:rgba(255,255,255,0.6);padding:4px 8px;border-radius:6px;font-size:9px;backdrop-filter:blur(4px);z-index:25;display:none; }

        .scanning-ring { border-color:rgba(96,165,250,0.9) !important; animation:guidePulse 2s ease-in-out infinite; }
        @keyframes guidePulse { 0%,100%{box-shadow:0 0 0 4000px rgba(10,22,40,0.65),0 0 20px rgba(96,165,250,0.2)} 50%{box-shadow:0 0 0 4000px rgba(10,22,40,0.65),0 0 35px rgba(96,165,250,0.4)} }
        .scanning-ring .corner { border-color:rgba(96,165,250,0.9) !important; }
        .success-ring { border-color:rgba(16,185,129,0.9) !important; box-shadow:0 0 0 4000px rgba(10,22,40,0.65),0 0 40px rgba(16,185,129,0.4) !important; animation:successPulse 0.6s ease-out !important; }
        .success-ring .corner { border-color:rgba(16,185,129,0.9) !important; }
        .success-ring .scan-line { background:linear-gradient(90deg,transparent,rgba(16,185,129,0.8),transparent); box-shadow:0 0 8px rgba(16,185,129,0.6); }
        .success-ring .grid-overlay { background-image:linear-gradient(rgba(16,185,129,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(16,185,129,0.06) 1px,transparent 1px); }
        .success-ring .detection-point { background:rgba(16,185,129,0.9); }
        @keyframes successPulse { 0%{transform:translate(-50%,-50%) scale(0.97)} 60%{transform:translate(-50%,-50%) scale(1.02)} 100%{transform:translate(-50%,-50%) scale(1)} }
        .error-ring { border-color:rgba(239,68,68,0.9) !important; box-shadow:0 0 0 4000px rgba(10,22,40,0.65),0 0 30px rgba(239,68,68,0.35) !important; animation:errorShake 0.35s ease-out !important; }
        .error-ring .corner { border-color:rgba(239,68,68,0.9) !important; }
        .error-ring .scan-line { background:linear-gradient(90deg,transparent,rgba(239,68,68,0.8),transparent); box-shadow:0 0 8px rgba(239,68,68,0.6); }
        @keyframes errorShake { 0%,100%{transform:translate(-50%,-50%)} 25%{transform:translate(-52%,-50%)} 75%{transform:translate(-48%,-50%)} }

        @media (min-width:640px) and (max-width:1023px) { #faceGuide{width:220px;height:290px} }
        @media (min-width:1024px) { #faceGuide{width:290px;height:380px} .corner{width:32px;height:32px} }
        @media (max-width:480px) { #faceGuide{width:210px;height:280px} .corner{width:24px;height:24px} }

        .mobile-back-btn { display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:rgba(10,22,40,0.6);border:1px solid var(--white-15);color:var(--white);text-decoration:none;backdrop-filter:blur(8px);flex-shrink:0; }
        .mobile-header-info { flex:1;min-width:0; }
        .mobile-header-label { font-size:0.65rem;color:var(--white-60);letter-spacing:0.08em;text-transform:uppercase; }
        .mobile-header-title { font-size:0.9rem;font-weight:600;color:var(--white);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }

        .mobile-status-box { background:rgba(10,22,40,0.75);border:1px solid var(--white-15);border-radius:14px;padding:0.85rem 1rem;backdrop-filter:blur(12px);text-align:center; }
        .mobile-status-text { font-size:0.875rem;font-weight:500;color:var(--white);line-height:1.4; }
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
        </div>
        <div class="panel-section">
            <span class="panel-section-label">สถานะระบบ</span>
            <div id="statusChip" class="status-chip connecting">
                <span class="status-dot pulse"></span>
                <span id="statusChipText">กำลังเชื่อมต่อกล้อง...</span>
            </div>
            <div id="scoreDisplayPanel" class="score-display">
                <span class="score-label">คะแนนความคล้าย</span>
                <span id="scoreValuePanel" class="score-value">—</span>
            </div>
            <div id="statusAlertPanel" class="status-alert error" style="margin-top:0.75rem;"></div>
        </div>
        <div class="panel-divider"></div>
        <div class="panel-section">
            <span class="panel-section-label">คำแนะนำ</span>
            <ul class="instructions-list">
                <li><span class="num">1</span>วางใบหน้าให้อยู่ในกรอบรูปรี</li>
                <li><span class="num">2</span>มองตรงมายังกล้อง อย่าก้มหรือเงยหน้า</li>
                <li><span class="num">3</span>ให้แสงสว่างเพียงพอ หลีกเลี่ยงแสงจ้าด้านหลัง</li>
                <li><span class="num">4</span>ถืออุปกรณ์ให้นิ่ง ห่างจากกล้องประมาณ 40–60 ซม.</li>
            </ul>
        </div>
        <div class="panel-section" style="margin-top:auto;">
            <button type="button" id="manualCaptureBtnPanel" class="btn-manual" onclick="capturePhoto(true)">
                ถ่ายภาพด้วยตนเอง
            </button>
        </div>
    </aside>

    <!-- ── CAMERA AREA ── -->
    <div class="camera-area">
        <video id="cameraPreview" autoplay playsinline muted></video>
        <div id="faceGuide">
            <div class="grid-overlay"></div>
            <div class="scan-line"></div>
            <div class="face-detection-points" id="faceDetectionPoints"></div>
            <canvas id="faceLandmarksCanvas"></canvas>
            <div id="scanStatus"><span style="color:#60a5fa;">Initializing</span></div>
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
                <div class="comparison-arrow">&#10231;</div>
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

    <!-- ── MOBILE BOTTOM ── -->
    <div class="mobile-bottom">
        <div class="mobile-status-box">
            <div id="mobileStatusText" class="mobile-status-text">กำลังเชื่อมต่อกล้อง...</div>
            <div id="mobileScoreRow" class="mobile-score-row">
                <span id="mobileScoreVal" class="mobile-score-val"></span>
                <span class="mobile-score-label">ความคล้ายใบหน้า</span>
            </div>
        </div>
        <div id="mobileAlertBox" class="status-alert error"></div>
        <button type="button" id="manualCaptureBtnMobile" class="btn-manual" onclick="capturePhoto(true)" style="display:none;">ถ่ายภาพด้วยตนเอง</button>
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
    chip.className = 'status-chip ' + state;
    if (chipText) chipText.textContent = text;
    var dot = chip.querySelector('.status-dot');
    if (dot) dot.classList.toggle('pulse', state === 'connecting' || state === 'scanning');
    var m = document.getElementById('mobileStatusText');
    if (m) m.textContent = text;
}
function setScore(score, color) {
    var panel = document.getElementById('scoreDisplayPanel');
    var val = document.getElementById('scoreValuePanel');
    if (panel) panel.style.display = 'flex';
    if (val) { val.textContent = score; val.style.color = color; }
    var mRow = document.getElementById('mobileScoreRow');
    var mVal = document.getElementById('mobileScoreVal');
    if (mRow) mRow.style.display = 'flex';
    if (mVal) { mVal.textContent = score; mVal.style.color = color; }
    var rt = document.getElementById('realtimeScore');
    if (rt) { rt.textContent = score; rt.style.color = color; }
}
function showAlert(msg) {
    var ap = document.getElementById('statusAlertPanel');
    var mb = document.getElementById('mobileAlertBox');
    if (ap) { ap.textContent = msg; ap.style.display = msg ? 'block' : 'none'; }
    if (mb) { mb.textContent = msg; mb.style.display = msg ? 'block' : 'none'; }
    var sm = document.getElementById('statusMsg');
    if (sm) { sm.textContent = msg; sm.style.display = msg ? 'block' : 'none'; }
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
var _msgEl = document.getElementById('statusMsg');
if (_msgEl) {
    new MutationObserver(function() { var t=_msgEl.textContent.trim(); showAlert(t); if(t) setStatusChip('error',t); })
        .observe(_msgEl, {childList:true, characterData:true, subtree:true});
}
var _rtEl = document.getElementById('realtimeScore');
if (_rtEl) {
    new MutationObserver(function() { var t=_rtEl.textContent.trim(),c=_rtEl.style.color||'white'; if(t) setScore(t,c); })
        .observe(_rtEl, {childList:true, characterData:true, subtree:true, attributes:true, attributeFilter:['style']});
}
var _manualBtn = document.getElementById('manualCaptureBtn');
if (_manualBtn) {
    new MutationObserver(function() { showManualBtn(_manualBtn.style.display !== 'none'); })
        .observe(_manualBtn, {attributes:true, attributeFilter:['style']});
}

/* ── Original Logic ── */
var faceScanMethod = '{{ $faceScanMethod ?? "hybrid" }}';
var isJsModeActive = (faceScanMethod === 'js');
var profileDescriptor = null, pythonFailCount = 0, pythonThrottledUntil = 0, isFaceApiLoaded = false;
var smartScanner = null;
var performanceMonitor = {requests:0, successRate:0, avgResponseTime:0, lastUpdate:Date.now()};

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

async function initSmartScanner() {
    if (!window.SmartFaceScanner) { console.warn('SmartFaceScanner not loaded'); return false; }
    smartScanner = new SmartFaceScanner({maxConcurrentRequests:1,adaptiveThrottling:true,fallbackThreshold:2,minInterval:800,maxInterval:3000,baseInterval:1500,preferAccuracy:faceScanMethod==='python',hybridMode:faceScanMethod==='hybrid'||faceScanMethod==='python'});
    setInterval(updatePerformanceMonitor, 5000);
    return true;
}
function updatePerformanceMonitor() {
    if (!smartScanner) return;
    var status=smartScanner.getStatus();
    performanceMonitor={requests:performanceMonitor.requests+1,mode:status.mode,fallbackActive:status.fallbackActive,avgResponseTime:status.performance.avgPythonTime||status.performance.avgJsTime||0,currentInterval:status.currentInterval,lastUpdate:Date.now()};
    updateScanStatusUI(status);
}
function updateScanStatusUI(status) {
    var el=document.getElementById('scanStatus'); if(!el) return;
    var map={python:['AI Server','#34d399'],js:['JavaScript',status.fallbackActive?'#fcd34d':'#93c5fd'],hybrid:['Hybrid','#c4b5fd']};
    var pair=map[status.mode]||['Unknown','#fff'];
    el.innerHTML='<span style="color:'+pair[1]+'">'+pair[0]+'</span>'+(status.fallbackActive?' (Fallback)':'')+'<br><small>'+status.performance.avgPythonTime+'ms avg</small>';
}

var faceLandmarksCanvas=null,faceLandmarksCtx=null,detectionInterval=null,isScanningActive=true;
function initFaceLandmarksCanvas() {
    faceLandmarksCanvas=document.getElementById('faceLandmarksCanvas');
    if(faceLandmarksCanvas){var v=document.getElementById('cameraPreview');faceLandmarksCanvas.width=v.videoWidth||640;faceLandmarksCanvas.height=v.videoHeight||480;faceLandmarksCtx=faceLandmarksCanvas.getContext('2d');}
}
async function detectAndDrawFace() {
    if(!isScanningActive||!isFaceApiLoaded) return;
    var video=document.getElementById('cameraPreview');
    if(video.videoWidth===0) return;
    if(!faceLandmarksCanvas||faceLandmarksCanvas.width!==video.videoWidth) initFaceLandmarksCanvas();
    try {
        var det=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks();
        faceLandmarksCtx.clearRect(0,0,faceLandmarksCanvas.width,faceLandmarksCanvas.height);
        if(det){
            var box=det.detection.box;
            faceLandmarksCtx.strokeStyle='rgba(96,165,250,0.7)';faceLandmarksCtx.lineWidth=2;
            faceLandmarksCtx.strokeRect(box.x,box.y,box.width,box.height);
            faceLandmarksCtx.fillStyle='rgba(96,165,250,0.9)';
            det.landmarks.positions.forEach(function(pt,i){
                faceLandmarksCtx.beginPath();faceLandmarksCtx.arc(pt.x,pt.y,1.5,0,2*Math.PI);faceLandmarksCtx.fill();
                if(i%5===0){faceLandmarksCtx.shadowBlur=8;faceLandmarksCtx.shadowColor='rgba(96,165,250,0.8)';faceLandmarksCtx.beginPath();faceLandmarksCtx.arc(pt.x,pt.y,3,0,2*Math.PI);faceLandmarksCtx.fill();faceLandmarksCtx.shadowBlur=0;}
            });
            updateRealFaceDetectionPoints(det.landmarks);
            updateGuideFramePosition(box);
        }
    } catch(e){console.warn('Face detection error:',e);}
}
function updateRealFaceDetectionPoints(landmarks) {
    var c=document.getElementById('faceDetectionPoints');if(!c)return;
    var video=document.getElementById('cameraPreview');c.innerHTML='';
    [36,39,42,45,33,48,54,0,16,19,24,8].forEach(function(idx,i){
        if(!landmarks.positions[idx])return;
        var pt=landmarks.positions[idx],dot=document.createElement('div');
        dot.className='detection-point';
        dot.style.left=(pt.x/video.videoWidth*100)+'%';
        dot.style.top=(pt.y/video.videoHeight*100)+'%';
        dot.style.animationDelay=(i*0.12)+'s';
        c.appendChild(dot);
    });
}
function updateGuideFramePosition(box) {
    var guide=document.getElementById('faceGuide'),video=document.getElementById('cameraPreview');
    if(!guide||!video||video.videoWidth===0)return;
    guide.style.transition='left 0.3s ease-out,top 0.3s ease-out';
    guide.style.left=((box.x+box.width/2)/video.videoWidth*100)+'%';
    guide.style.top=((box.y+box.height/2)/video.videoHeight*100)+'%';
}
function startRealtimeDetection(){if(detectionInterval)clearInterval(detectionInterval);detectionInterval=setInterval(function(){if(isScanningActive&&isFaceApiLoaded)detectAndDrawFace();},100);}
function stopRealtimeDetection(){if(detectionInterval){clearInterval(detectionInterval);detectionInterval=null;}if(faceLandmarksCtx)faceLandmarksCtx.clearRect(0,0,faceLandmarksCanvas.width,faceLandmarksCanvas.height);}

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

var stream=null,scanTimeout=null,scanAttempts=0;
var MAX_ATTEMPTS=15,THRESHOLD=60;
var isVerifying=false,stopScanning=false,isFlashOn=false;

document.addEventListener('DOMContentLoaded', async function(){
    @if(session('error'))
    stopScanning=true;
    var guide=document.getElementById('faceGuide');if(guide)guide.style.display='none';
    return;
    @endif
    var smartScannerReady=await initSmartScanner();
    var preComputed={!! $profileJsDescriptor ?? 'null' !!};
    if(preComputed){profileDescriptor={embedding_128d:new Float32Array(Object.values(preComputed)),embedding_512d:null};}
    else if(smartScannerReady){await loadJsDescriptorFromApi();}
    await startCamera();
    var guide=document.getElementById('faceGuide');if(guide)guide.classList.add('scanning-ring');
    var scanEl=document.getElementById('scanInstructions');if(scanEl)scanEl.textContent='กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
    setStatusChip('scanning','กำลังสแกนใบหน้า...');
    showLargeScreenTips();
    scanTimeout=setTimeout(scanFrame,1000);
});

async function startCamera() {
    try {
        var isLargeScreen=window.innerWidth>=1024||window.innerHeight>=768;
        var isMobile=/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        var constraints=isLargeScreen&&!isMobile?{video:{facingMode:'user',width:{ideal:640,max:1280},height:{ideal:480,max:720},frameRate:{ideal:30,max:30}},audio:false}:{video:{facingMode:'user'},audio:false};
        try{stream=await navigator.mediaDevices.getUserMedia(constraints);}catch(e){stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});}
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

async function scanFrame() {
    if(isVerifying||!stream||stopScanning)return;
    var video=document.getElementById('cameraPreview');
    if(video.videoWidth===0){scanTimeout=setTimeout(scanFrame,1000);return;}
    isVerifying=true;scanAttempts++;
    if(scanAttempts%3===0)playScanSound();
    try {
        if(smartScanner&&profileDescriptor){var result=await smartScanner.scanFrame(video,profileDescriptor);if(result){await processScanResult(result);return;}}
        else{await legacyScanFrame(video);}
    } catch(error){console.error('Scan error:',error);await legacyScanFrame(video);}
    finally {
        isVerifying=false;
        var throttled=Date.now()<pythonThrottledUntil;
        var nextInterval=throttled?5000:(smartScanner?smartScanner.currentInterval:1000);
        if(!stopScanning)scanTimeout=setTimeout(scanFrame,nextInterval);
    }
}
async function processScanResult(result) {
    var scoreStr=result.source+': '+result.score.toFixed(1)+'% ('+result.processingTime+'ms)';
    var scoreColor=result.passed?'#34d399':'#fcd34d';
    setScore(scoreStr,scoreColor);
    if(result.passed&&result.confidence>0.7){
        stopScanning=true;isScanningActive=false;clearTimeout(scanTimeout);
        var guide=document.getElementById('faceGuide');if(guide)guide.classList.replace('scanning-ring','success-ring');
        setStatusChip('success','ยืนยันตัวตนสำเร็จ!');
        playSuccessSound();capturePhoto(true);
    }
}
async function legacyScanFrame(video) {
    var MAX_DIM=480,tw=video.videoWidth,th=video.videoHeight;
    if(tw>th){if(tw>MAX_DIM){th=Math.round(th*(MAX_DIM/tw));tw=MAX_DIM;}}else{if(th>MAX_DIM){tw=Math.round(tw*(MAX_DIM/th));th=MAX_DIM;}}
    var canvas=document.createElement('canvas');canvas.width=tw;canvas.height=th;
    var ctx=canvas.getContext('2d');
    ctx.translate(canvas.width,0);ctx.scale(-1,1);ctx.drawImage(video,0,0,canvas.width,canvas.height);ctx.setTransform(1,0,0,1,0,0);
    handleLowLightDetection(ctx,canvas);
    var base64Image=canvas.toDataURL('image/jpeg',0.6);
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
        if(avgBrightness<75)isFlashOn=true;if(avgBrightness>110)isFlashOn=false;
        if(isFlashOn){
            var boost=Math.min(3.5,90/Math.max(avgBrightness,10));
            if(video)video.style.filter='brightness('+boost.toFixed(2)+') contrast(1.15)';
            if(ctx&&video){ctx.filter='brightness('+boost.toFixed(2)+') contrast(1.15)';ctx.translate(canvas.width,0);ctx.scale(-1,1);ctx.drawImage(video,0,0,canvas.width,canvas.height);ctx.setTransform(1,0,0,1,0,0);ctx.filter='none';}
            if(guide)guide.style.boxShadow='0 0 0 4000px rgba(10,22,40,0.6)';
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
        setScore('JS (128D): '+score.toFixed(1)+'%',passed?'#34d399':'#fcd34d');
        if(passed)await processScanResult({confidence:score/100,passed:true,score:score,source:'js_primary',processingTime:Date.now()%1000});
    } catch(e){console.warn('JS verification error:',e);}
}
async function performPythonVerification(base64Image) {
    try {
        var t0=Date.now();
        var res=await fetch('/api/face/verify',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').getAttribute('content'),'Accept':'application/json','Authorization':'Bearer '+(document.querySelector('meta[name="api-token"]')||{}).getAttribute('content')},body:JSON.stringify({image:base64Image,mode:'python',priority:'accuracy'})});
        var ms=Date.now()-t0;
        if(res.status===429){var ra=30;try{var j=await res.json();ra=j.retry_after||30;}catch(_){}pythonThrottledUntil=Date.now()+ra*1000;console.warn('Rate limited — backing off '+ra+'s');return;}
        if(!res.ok)throw new Error('HTTP '+res.status);
        var result=await res.json();
        if(result.success!==false){
            pythonFailCount=0;
            var score=result.score_percentage||0,passed=result.is_match||false;
            setScore('Python (512D): '+score.toFixed(1)+'% ('+(result.processing_ms||ms)+'ms)',passed?'#34d399':'#fcd34d');
            if(passed)await processScanResult({confidence:score/100,passed:true,score:score,source:'python_primary',processingTime:result.processing_ms||ms});
        } else if(result.fallback_recommended){isJsModeActive=true;pythonFailCount++;}
    } catch(e){console.warn('Python verification failed:',e);pythonFailCount++;if(pythonFailCount>=2&&isFaceApiLoaded&&profileDescriptor&&profileDescriptor.embedding_128d){isJsModeActive=true;}}
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
