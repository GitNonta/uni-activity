<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ยืนยันตัวตน - Selfie</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/face-scan-animation.css') }}?v=1">
    <style>
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; font-family: 'Sarabun', sans-serif; }
        #cameraContainer { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }
        #cameraPreview { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; /* เปลี่ยนจาก cover เป็น contain เพื่อแสดงภาพเต็ม */
            transform: scaleX(-1);
            /* ป้องกันการซูมมากเกินไปบนจอใหญ่ */
            max-width: none;
            max-height: none;
        }
        
        /* ปรับสำหรับมือถือให้แสดงมุมมองกว้างขึ้น */
        @media (max-width: 1023px) {
            #cameraPreview {
                /* เปลี่ยนเป็น contain เพื่อแสดงภาพเต็มไม่โดนตัด */
                object-fit: contain !important;
                object-position: center;
                /* ลดการซูมด้วยการปรับ scale */
                transform: scaleX(-1) scale(0.7) !important;
                transform-origin: center;
            }
            
            #cameraContainer {
                background: #000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* ปรับ face guide ให้เล็กลงเล็กน้อยเพื่อให้เห็นมุมมองกว้างขึ้น */
            #faceGuide {
                width: 260px !important;
                height: 350px !important;
                border-width: 2.5px !important;
                top: 50% !important; /* จัดให้อยู่กึ่งกลางจอ */
            }
            
            /* ปรับ corner brackets */
            .corner {
                width: 35px !important;
                height: 35px !important;
            }
        }
        
        /* ปรับสำหรับมือถือขนาดเล็กมาก */
        @media (max-width: 480px) {
            #cameraPreview {
                object-fit: contain !important;
                transform: scaleX(-1) scale(0.6) !important;
            }
            
            #faceGuide {
                width: 240px !important;
                height: 320px !important;
                top: 50% !important;
            }
            
            .status-text {
                font-size: 1rem !important;
                padding: 10px 20px !important;
            }
        }
        .overlay-ui { position: absolute; z-index: 10; pointer-events: none; }
        
        .top-bar { top: 0; left: 0; right: 0; padding: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; background: linear-gradient(to bottom, rgba(0,0,0,0.5), transparent); pointer-events: auto; }
        .back-btn { color: white; background: rgba(0,0,0,0.3); border-radius: 50%; padding: 12px; backdrop-filter: blur(8px); display: inline-flex; transition: background 0.3s; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); }
        .back-btn:active { background: rgba(255,255,255,0.4); }

        .status-container { position: absolute; bottom: 10%; left: 0; right: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 1.5rem; pointer-events: auto; }
        .status-text { color: white; background: rgba(0,0,0,0.6); padding: 12px 24px; border-radius: 30px; text-align: center; font-size: 1.1rem; backdrop-filter: blur(8px); transition: all 0.3s; border: 1px solid rgba(255,255,255,0.15); margin-bottom: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.3); max-width: 100%; width: 100%; }
        
        #faceGuide { 
            position: absolute; 
            top: 45%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            width: 280px; 
            height: 380px; 
            border: 3px solid rgba(255,255,255,0.6); 
            border-radius: 120px; 
            box-shadow: 0 0 0 4000px rgba(0,0,0,0.6); 
            transition: border-color 0.3s, box-shadow 0.8s ease, width 0.3s ease, height 0.3s ease; 
            overflow: hidden; 
        }
        
        /* ปรับขนาด face guide สำหรับจอใหญ่ */
        @media (min-width: 1024px) and (min-height: 768px) {
            #faceGuide {
                width: 320px;
                height: 420px;
                border-width: 4px;
            }
            
            .status-text {
                font-size: 1.2rem;
                padding: 14px 28px;
            }
            
            .corner {
                width: 50px;
                height: 50px;
            }
        }
        
        /* Scanning animation elements */
        .scan-line { position: absolute; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, #00ff88, transparent); box-shadow: 0 0 10px #00ff88, 0 0 20px #00ff88; animation: scanMove 2s linear infinite; z-index: 20; }
        @keyframes scanMove { 0% { top: 0%; opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { top: 100%; opacity: 0; } }
        
        .corner { position: absolute; width: 40px; height: 40px; border-color: #00ff88; border-style: solid; border-width: 0; transition: all 0.3s; }
        .corner-tl { top: 0; left: 0; border-top-width: 3px; border-left-width: 3px; border-top-left-radius: 120px; }
        .corner-tr { top: 0; right: 0; border-top-width: 3px; border-right-width: 3px; border-top-right-radius: 120px; }
        .corner-bl { bottom: 0; left: 0; border-bottom-width: 3px; border-left-width: 3px; border-bottom-left-radius: 120px; }
        .corner-br { bottom: 0; right: 0; border-bottom-width: 3px; border-right-width: 3px; border-bottom-right-radius: 120px; }
        
        .grid-overlay { position: absolute; inset: 0; background-image: linear-gradient(rgba(0, 255, 136, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 255, 136, 0.1) 1px, transparent 1px); background-size: 20px 20px; animation: gridPulse 3s ease-in-out infinite; z-index: 10; }
        @keyframes gridPulse { 0%, 100% { opacity: 0.3; } 50% { opacity: 0.6; } }
        
        .face-detection-points { position: absolute; inset: 0; z-index: 15; }
        .detection-point { position: absolute; width: 4px; height: 4px; background: #00ff88; border-radius: 50%; animation: pointPulse 1.5s ease-in-out infinite; }
        @keyframes pointPulse { 0%, 100% { opacity: 0.3; transform: scale(1); } 50% { opacity: 1; transform: scale(1.5); } }
        
        .scanning-ring { border-color: #ea580c !important; }
        .scanning-ring .corner { border-color: #00ff88 !important; animation: cornerPulse 1s ease-in-out infinite; }
        @keyframes cornerPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        
        .success-ring { border-color: #10b981 !important; background: rgba(16,185,129,0.15); }
        .success-ring .scan-line, .success-ring .grid-overlay, .success-ring .detection-point { display: none; }
        .success-ring .corner { border-color: #10b981 !important; animation: successCorner 0.5s ease-out; }
        @keyframes successCorner { 0% { transform: scale(1); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }
        
        .error-ring { border-color: #ef4444 !important; background: rgba(239,68,68,0.15); }
        .error-ring .scan-line { background: linear-gradient(90deg, transparent, #ef4444, transparent); box-shadow: 0 0 10px #ef4444, 0 0 20px #ef4444; }
        .error-ring .corner { border-color: #ef4444 !important; }
        .error-ring .grid-overlay { background-image: linear-gradient(rgba(239, 68, 68, 0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(239, 68, 68, 0.1) 1px, transparent 1px); }
        .error-ring .detection-point { background: #ef4444; }
        
        #comparisonResult { display:none; position:absolute; inset:0; z-index:20; background:rgba(0,0,0,0.85); flex-direction:column; align-items:center; justify-content:center; color:white; backdrop-filter: blur(10px); pointer-events: auto; padding: 2rem; text-align: center; }
        
        .btn-action { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 16px 32px; border-radius: 30px; font-weight: 700; font-size: 1.2rem; border: none; box-shadow: 0 4px 15px rgba(16,185,129,0.4); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; width: 100%; max-width: 300px; }
        .btn-action:disabled { background: #4b5563; box-shadow: none; cursor: not-allowed; }
        .btn-action:active:not(:disabled) { transform: scale(0.95); }
        
        .btn-outline-white { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.5); padding: 10px 20px; border-radius: 20px; font-size: 1rem; cursor: pointer; backdrop-filter: blur(4px); }
        
        /* Success animation */
        @keyframes successPulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        .success-ring { animation: successPulse 0.6s ease-out; }
        
        /* Error shake animation */
        @keyframes errorShake { 0%, 100% { transform: translate(-50%, -50%) rotate(0deg); } 25% { transform: translate(-52%, -50%) rotate(-2deg); } 75% { transform: translate(-48%, -50%) rotate(2deg); } }
        .error-ring { animation: errorShake 0.3s ease-out; }
    </style>
</head>
<body>
    <div id="cameraContainer">
        <video id="cameraPreview" autoplay playsinline muted></video>
        <div id="faceGuide" class="overlay-ui">
            <!-- Animated Grid Overlay -->
            <div class="grid-overlay"></div>
            
            <!-- Scanning Line -->
            <div class="scan-line"></div>
            
            <!-- Face Detection Points (will be dynamically positioned) -->
            <div class="face-detection-points" id="faceDetectionPoints"></div>
            
            <!-- Face Detection Canvas -->
            <canvas id="faceLandmarksCanvas" style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 24;"></canvas>
            
            <!-- Performance Status Indicators -->
            <div id="realtimeScore" style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 8px 12px; border-radius: 15px; font-size: 12px; display: none; backdrop-filter: blur(4px);">
                준비 중...
            </div>
            <div id="scanStatus" style="position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 6px 10px; border-radius: 12px; font-size: 10px; backdrop-filter: blur(4px);">
                <span style="color: #60a5fa;">Initializing</span>
            </div>
            
            <!-- Corner Brackets -->
            <div class="corner corner-tl"></div>
            <div class="corner corner-tr"></div>
            <div class="corner corner-bl"></div>
            <div class="corner corner-br"></div>
        </div>
        <canvas id="captureCanvas" style="display:none; position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transform:scaleX(-1); z-index:5;"></canvas>
    </div>

    <!-- Top Bar -->
    <div class="overlay-ui top-bar">
        <a href="{{ route('activities.index') }}" class="back-btn">
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </a>
    </div>

    <!-- Status Messages -->
    <div class="status-container overlay-ui">
        <div id="statusMsg" style="display:none; color:white; background:rgba(239,68,68,0.9); padding:10px 20px; border-radius:20px; margin-bottom:15px; font-weight:600; font-size:1rem; backdrop-filter: blur(4px); text-align: center; width: 100%;"></div>
        
        <div class="status-text" id="scanInstructions">กำลังเชื่อมต่อกล้อง...</div>
        
        <div id="realtimeScore" style="display:none; font-weight:bold; font-size:1.2rem; margin-bottom: 15px; background:rgba(0,0,0,0.6); padding:8px 20px; border-radius:20px; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); text-align:center;"></div>

        <!-- Liveness Badge -->
        <div id="livenessBadge" style="display:none; font-size:0.85rem; margin-bottom: 10px; padding:5px 14px; border-radius:20px; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); background:rgba(0,0,0,0.5); text-align:center;"></div>

        <button type="button" id="manualCaptureBtn" class="btn-outline-white" style="display:none;" onclick="capturePhoto(true)">
            ถ่ายภาพและส่งด้วยตัวเอง
        </button>
    </div>
    
    <!-- Face comparison result -->
    <div id="comparisonResult">
        <div style="display:flex; gap:1.5rem; align-items:center; margin-bottom:2rem;">
             <div style="text-align:center;">
                 <img id="profileThumb" src="{{ $profilePhotoUrl }}" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #10b981;">
                 <p style="font-size:0.85rem; margin-top:0.5rem; opacity:0.8;">รูปในระบบ</p>
             </div>
             <span style="font-size:2rem; opacity:0.7;">⟷</span>
             <div style="text-align:center;">
                 <canvas id="selfieThumb" width="90" height="90" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #10b981;"></canvas>
                 <p style="font-size:0.85rem; margin-top:0.5rem; opacity:0.8;">Selfie</p>
             </div>
        </div>
        <h2 id="matchScoreText" style="font-size:2rem;font-weight:bold;margin:0;"></h2>
        <p id="matchStatusText" style="margin-top:0.5rem; font-size:1.1rem; opacity:0.9; margin-bottom: 2.5rem;"></p>
        
        <button type="button" id="submitBtn" class="btn-action" disabled>กำลังบันทึกข้อมูล...</button>
    </div>

    @if(!$profilePhotoUrl)
    <div style="position:absolute; top:20%; left:5%; right:5%; background:rgba(254,243,199,0.95); color:#92400e; padding:15px; border-radius:15px; border:1px solid #fde68a; z-index: 50; text-align: center;">
        <div style="display:inline-flex;align-items:center;gap:6px;font-weight:bold;margin-bottom:5px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            ยังไม่มีภาพถ่ายโปรไฟล์ในระบบ
        </div>
        <span style="font-size:0.9rem;display:block;">ระบบจะบันทึก Selfie ไว้แต่ไม่สามารถเปรียบเทียบใบหน้าได้ กรุณาอัปโหลดรูปโปรไฟล์ภายหลัง</span>
    </div>
    @endif

    @if(session('error'))
    <div id="errorPopup" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index: 9999; display:flex; justify-content:center; align-items:center;">
        <div style="background:white; padding:30px; border-radius:20px; text-align: center; max-width: 85%; box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: popIn 0.3s ease-out;">
            <!-- SVG Icon: Outline Exclamation Circle -->
            <svg style="width:80px; height:80px; margin:0 auto 15px auto; color:#ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <strong style="display:block;margin-bottom:10px;font-size:1.4rem;color:#ef4444;">ไม่สามารถทำรายการได้</strong>
            <span style="font-size:1.1rem; display:block; margin-bottom: 25px; color:#374151;">{{ session('error') }}</span>
            <button type="button" onclick="window.location.href='{{ route('activities.show', $activity->id) }}'" style="background:#ef4444; color:white; border:none; padding:12px 30px; border-radius:30px; font-weight:bold; font-size:1.1rem; cursor:pointer; width:100%; box-shadow: 0 4px 10px rgba(239,68,68,0.3);">กลับไปหน้ากิจกรรม</button>
        </div>
    </div>
    <style>
        @keyframes popIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
    <script>
        // Auto-redirect back to activity page after 5 seconds
        setTimeout(() => {
            window.location.href = "{{ route('activities.show', $activity->id) }}";
        }, 5000);
    </script>
    @endif

    <!-- Hidden form -->
    <form id="selfieForm" method="POST" action="{{ route('checkin.store', $token) }}" style="display:none;">
        @csrf
        <input type="hidden" name="latitude" id="qr_lat">
        <input type="hidden" name="longitude" id="qr_lng">
        <input type="hidden" name="selfie" id="selfieData">
    </form>

    <script defer src="{{ asset('js/face-api.min.js') }}"></script>
    <script>
        const faceScanMethod = '{{ $faceScanMethod ?? "hybrid" }}'; // Changed default to hybrid
        let isJsModeActive = (faceScanMethod === 'js');
        let profileDescriptor = null;
        let pythonFailCount = 0;
        let isFaceApiLoaded = false;
        
        // ========================================
        // SMART FACE SCANNER - Balanced Processing
        // ========================================
        let smartScanner = null;
        let performanceMonitor = {
            requests: 0,
            successRate: 0,
            avgResponseTime: 0,
            lastUpdate: Date.now()
        };
        
        // ===== Audio Effects for Face Detection =====
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        function playScanSound() {
            // Soft beep sound during scanning
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.frequency.value = 1200;
            oscillator.type = 'sine';
            gainNode.gain.value = 0.05;
            oscillator.start();
            setTimeout(() => oscillator.stop(), 50);
        }
        
        function playSuccessSound() {
            // Success melody
            const notes = [523.25, 659.25, 783.99]; // C5, E5, G5
            notes.forEach((freq, i) => {
                setTimeout(() => {
                    const osc = audioContext.createOscillator();
                    const gain = audioContext.createGain();
                    osc.connect(gain);
                    gain.connect(audioContext.destination);
                    osc.frequency.value = freq;
                    osc.type = 'sine';
                    gain.gain.value = 0.1;
                    osc.start();
                    setTimeout(() => osc.stop(), 150);
                }, i * 150);
            });
        }
        
        function playErrorSound() {
            // Error buzz
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            osc.connect(gain);
            gain.connect(audioContext.destination);
            osc.frequency.value = 200;
            osc.type = 'sawtooth';
            gain.gain.value = 0.1;
            osc.start();
            setTimeout(() => osc.stop(), 200);
        }

        // ===== Smart Face Scanner Integration =====
        async function initSmartScanner() {
            if (!window.SmartFaceScanner) {
                console.warn('SmartFaceScanner not loaded, falling back to legacy mode');
                return false;
            }

            const scannerOptions = {
                maxConcurrentRequests: 1,
                adaptiveThrottling: true,
                fallbackThreshold: 2,
                minInterval: 800,
                maxInterval: 3000,
                baseInterval: 1500,
                preferAccuracy: faceScanMethod === 'python',
                hybridMode: faceScanMethod === 'hybrid' || faceScanMethod === 'python'
            };

            smartScanner = new SmartFaceScanner(scannerOptions);
            console.log('🧠 Smart Face Scanner initialized');

            // Setup performance monitoring
            setInterval(updatePerformanceMonitor, 5000);
            
            return true;
        }

        function updatePerformanceMonitor() {
            if (!smartScanner) return;

            const status = smartScanner.getStatus();
            performanceMonitor = {
                requests: performanceMonitor.requests + 1,
                mode: status.mode,
                fallbackActive: status.fallbackActive,
                avgResponseTime: status.performance.avgPythonTime || status.performance.avgJsTime || 0,
                currentInterval: status.currentInterval,
                lastUpdate: Date.now()
            };

            // Update UI indicators
            updateScanStatusUI(status);
        }

        function updateScanStatusUI(status) {
            const statusElement = document.getElementById('scanStatus');
            if (statusElement) {
                let modeText = '';
                let colorClass = '';

                switch(status.mode) {
                    case 'python':
                        modeText = 'AI Server';
                        colorClass = 'text-green-500';
                        break;
                    case 'js':
                        modeText = 'JavaScript';
                        colorClass = status.fallbackActive ? 'text-yellow-500' : 'text-blue-500';
                        break;
                    case 'hybrid':
                        modeText = 'Hybrid';
                        colorClass = 'text-purple-500';
                        break;
                }

                statusElement.innerHTML = `
                    <span class="${colorClass}">${modeText}</span>
                    ${status.fallbackActive ? ' (Fallback)' : ''}
                    <br><small>${status.performance.avgPythonTime}ms avg</small>
                `;
            }
        }
        
        // ===== Face Detection Canvas Setup =====
        let faceLandmarksCanvas = null;
        let faceLandmarksCtx = null;
        let detectionInterval = null;
        let isScanningActive = true;
        
        function initFaceLandmarksCanvas() {
            faceLandmarksCanvas = document.getElementById('faceLandmarksCanvas');
            if (faceLandmarksCanvas) {
                const video = document.getElementById('cameraPreview');
                faceLandmarksCanvas.width = video.videoWidth || 640;
                faceLandmarksCanvas.height = video.videoHeight || 480;
                faceLandmarksCtx = faceLandmarksCanvas.getContext('2d');
            }
        }
        
        // ===== Real-time Face Detection and Landmark Drawing =====
        async function detectAndDrawFace() {
            if (!isScanningActive || !isFaceApiLoaded) return;
            
            const video = document.getElementById('cameraPreview');
            if (video.videoWidth === 0) return;
            
            // Ensure canvas is sized correctly
            if (!faceLandmarksCanvas || faceLandmarksCanvas.width !== video.videoWidth) {
                initFaceLandmarksCanvas();
            }
            
            try {
                // Detect face with landmarks
                const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks();
                
                // Clear canvas
                faceLandmarksCtx.clearRect(0, 0, faceLandmarksCanvas.width, faceLandmarksCanvas.height);
                
                if (detection) {
                    const landmarks = detection.landmarks;
                    const positions = landmarks.positions;
                    
                    // Draw face bounding box
                    const box = detection.detection.box;
                    faceLandmarksCtx.strokeStyle = '#00ff88';
                    faceLandmarksCtx.lineWidth = 3;
                    faceLandmarksCtx.strokeRect(box.x, box.y, box.width, box.height);
                    
                    // Draw landmarks as points
                    faceLandmarksCtx.fillStyle = '#00ff88';
                    positions.forEach((point, i) => {
                        faceLandmarksCtx.beginPath();
                        faceLandmarksCtx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                        faceLandmarksCtx.fill();
                        
                        // Add glow effect to key points
                        if (i % 5 === 0) {
                            faceLandmarksCtx.shadowBlur = 10;
                            faceLandmarksCtx.shadowColor = '#00ff88';
                            faceLandmarksCtx.beginPath();
                            faceLandmarksCtx.arc(point.x, point.y, 4, 0, 2 * Math.PI);
                            faceLandmarksCtx.fill();
                            faceLandmarksCtx.shadowBlur = 0;
                        }
                    });
                    
                    // Update detection points container with real positions
                    updateRealFaceDetectionPoints(landmarks);
                    
                    // Update guide frame position to follow face
                    updateGuideFramePosition(box);
                }
            } catch (error) {
                console.warn('Face detection error:', error);
            }
        }
        
        // Update detection points to match real face landmarks
        function updateRealFaceDetectionPoints(landmarks) {
            const pointsContainer = document.getElementById('faceDetectionPoints');
            if (!pointsContainer) return;
            
            const video = document.getElementById('cameraPreview');
            const videoRect = video.getBoundingClientRect();
            
            // Clear existing points
            pointsContainer.innerHTML = '';
            
            // Key facial landmarks indices
            const keyPoints = [
                36, 39, 42, 45,  // Eyes
                33,              // Nose tip
                48, 54,          // Mouth corners
                0, 16,           // Jaw corners
                19, 24,          // Eyebrows
                8                // Chin
            ];
            
            keyPoints.forEach((index, i) => {
                if (landmarks.positions[index]) {
                    const point = landmarks.positions[index];
                    const dot = document.createElement('div');
                    dot.className = 'detection-point';
                    
                    // Convert video coordinates to percentage
                    const leftPercent = (point.x / video.videoWidth) * 100;
                    const topPercent = (point.y / video.videoHeight) * 100;
                    
                    dot.style.left = `${leftPercent}%`;
                    dot.style.top = `${topPercent}%`;
                    dot.style.animationDelay = `${i * 0.1}s`;
                    
                    pointsContainer.appendChild(dot);
                }
            });
        }
        
        // Update guide frame to follow detected face
        function updateGuideFramePosition(box) {
            const guide = document.getElementById('faceGuide');
            const video = document.getElementById('cameraPreview');
            
            if (!guide || !video || video.videoWidth === 0) return;
            
            // Calculate center and size
            const centerX = (box.x + box.width / 2) / video.videoWidth * 100;
            const centerY = (box.y + box.height / 2) / video.videoHeight * 100;
            
            // Smooth transition to new position
            guide.style.transition = 'left 0.3s ease-out, top 0.3s ease-out';
            guide.style.left = `${centerX}%`;
            guide.style.top = `${centerY}%`;
        }
        
        // แสดงคำแนะนำสำหรับจอใหญ่
        function showLargeScreenTips() {
            const isLargeScreen = window.innerWidth >= 1024 || window.innerHeight >= 768;
            const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isLargeScreen && !isMobile) {
                const instructionEl = document.getElementById('scanInstructions');
                setTimeout(() => {
                    if (instructionEl && scanAttempts <= 2) {
                        instructionEl.innerHTML = 'คำแนะนำ: กรุณานั่งหรือยืนให้ห่างจากกล้องประมาณ 60-80 ซม. เพื่อผลการตรวจสอบที่แม่นยำ';
                        setTimeout(() => {
                            if (instructionEl && !stopScanning) {
                                instructionEl.textContent = 'กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
                            }
                        }, 4000);
                    }
                }, 2000);
            } else {
                // มือถือ: แสดงคำแนะนำสำหรับมุมมองแบบใหม่
                const instructionEl = document.getElementById('scanInstructions');
                setTimeout(() => {
                    if (instructionEl && scanAttempts <= 2) {
                        instructionEl.innerHTML = 'คำแนะนำ: กรุณาถืออุปกรณ์ให้มั่นคงและมองตรงมายังกล้อง';
                        setTimeout(() => {
                            if (instructionEl && !stopScanning) {
                                instructionEl.textContent = 'กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
                            }
                        }, 3500);
                    }
                }, 1500);
            }
        }
        
        // Start real-time detection
        function startRealtimeDetection() {
            if (detectionInterval) clearInterval(detectionInterval);
            
            detectionInterval = setInterval(() => {
                if (isScanningActive && isFaceApiLoaded) {
                    detectAndDrawFace();
                }
            }, 100); // Detect every 100ms (10 FPS)
        }
        
        // Stop detection
        function stopRealtimeDetection() {
            if (detectionInterval) {
                clearInterval(detectionInterval);
                detectionInterval = null;
            }
            if (faceLandmarksCtx) {
                faceLandmarksCtx.clearRect(0, 0, faceLandmarksCanvas.width, faceLandmarksCanvas.height);
            }
        }
        
        async function initFaceApi() {
            if (isFaceApiLoaded) return;
            const instructionEl = document.getElementById('scanInstructions');
            if (instructionEl) instructionEl.innerHTML = '<span class="spinner"></span> กำลังโหลดโมเดล AI บนเครื่อง...';
            
            try {
                // โหลดโมเดลจากเซิร์ฟเวอร์ตัวเองโดยตรง (ไม่ดึงจากเว็บนอก) เพื่อให้โหลดเร็วและเสถียรที่สุด
                const MODEL_URL = '/models';
                
                // Load tiny detector for real-time performance
                await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                
                // Also load SSD for final verification if needed
                if (faceScanMethod === 'js') {
                    await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
                }
                
                // Setup pre-computed descriptor or compute it
                const preComputed = {!! $profileJsDescriptor ?? 'null' !!};
                if (preComputed) {
                    profileDescriptor = new Float32Array(Object.values(preComputed));
                    console.log('Loaded JS descriptor from DB');
                } else {
                    const profileUrl = '{{ $profilePhotoUrl }}';
                    if (profileUrl) {
                        // Process base profile image
                        const img = await faceapi.fetchImage(profileUrl);
                        const detection = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();
                        if (detection) {
                            profileDescriptor = detection.descriptor;
                            
                            // Auto-save to backend
                            fetch('{{ route("profile.save_js_descriptor") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({ descriptor: Array.from(profileDescriptor) })
                            }).then(r => r.json()).then(d => console.log('Saved JS descriptor', d)).catch(e => console.error(e));
                        }
                    }
                }
                
                isFaceApiLoaded = true;
                
                // Initialize canvas for landmarks
                setTimeout(() => {
                    initFaceLandmarksCanvas();
                    startRealtimeDetection();
                }, 500);
                
                if (instructionEl) instructionEl.textContent = 'กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
                
            } catch (e) {
                console.error("FaceAPI Load Error", e);
                if (instructionEl) instructionEl.textContent = 'ไม่สามารถโหลดระบบสำรองได้';
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            // Always load face-api for real-time visualization
            // even if using Python for final verification
            initFaceApi();
        });
        
    let stream = null;
    let scanTimeout = null;
    let scanAttempts = 0;
    const MAX_ATTEMPTS = 15;
    const THRESHOLD = 60;
    let isVerifying = false;
    let stopScanning = false;
    let isFlashOn = false;

    // ===== 1. เริ่มระบบ =====
        document.addEventListener('DOMContentLoaded', async () => {
            @if(session('error'))
            // หากมี Popup แจ้งเตือนข้อผิดพลาด ให้หยุดการสแกนและไม่ต้องเปิดกล้องเลย
            stopScanning = true;
            const guide = document.getElementById('faceGuide');
            if (guide) guide.style.display = 'none';
            const instructionEl = document.getElementById('scanInstructions');
            if (instructionEl) instructionEl.style.display = 'none';
            return;
            @endif

            // Initialize Smart Scanner
            const smartScannerReady = await initSmartScanner();
            
            // Prepare profile descriptor for smart scanning
            const preComputed = {!! $profileJsDescriptor ?? 'null' !!};
            if (preComputed) {
                profileDescriptor = {
                    embedding_128d: new Float32Array(Object.values(preComputed)),
                    embedding_512d: null // Will be loaded if needed
                };
                console.log('✅ Profile descriptor loaded for smart scanning');
            } else if (smartScannerReady) {
                // Try to load from API if not pre-computed
                await loadJsDescriptorFromApi();
            }

            await startCamera();
            
            const guide = document.getElementById('faceGuide');
            if (guide) guide.classList.add('scanning-ring');
            
            const instructionEl = document.getElementById('scanInstructions');
            if (instructionEl) instructionEl.textContent = 'กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
            
            // แสดงคำแนะนำสำหรับจอใหญ่
            showLargeScreenTips();
            
            scanTimeout = setTimeout(scanFrame, 1000);
        });

    // ===== 2. เปิดกล้องหน้า =====
    async function startCamera() {
        try {
            // ตรวจจับขนาดจอเพื่อปรับ resolution และ zoom ให้เหมาะสม
            const isLargeScreen = window.innerWidth >= 1024 || window.innerHeight >= 768;
            const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            let videoConstraints;
            
            if (isLargeScreen && !isMobile) {
                // จอใหญ่: ใช้ resolution ต่ำกว่าเพื่อลด zoom และเพิ่มมุมมอง
                videoConstraints = {
                    video: {
                        facingMode: 'user',
                        width: { ideal: 640, max: 1280 },
                        height: { ideal: 480, max: 720 },
                        frameRate: { ideal: 30, max: 30 }
                    },
                    audio: false
                };
                console.log('🖥️ Large screen detected: Using wider view settings');
            } else {
                // มือถือ: ใช้กล้องค่าเริ่มต้น ไม่ระบุ resolution
                videoConstraints = {
                    video: { facingMode: 'user' },
                    audio: false
                };
                console.log('📱 Mobile detected: Using default camera settings');
            }
            
            // พยายามเปิดกล้องด้วยการตั้งค่าที่เหมาะสม
            try {
                stream = await navigator.mediaDevices.getUserMedia(videoConstraints);
            } catch (specificError) {
                console.warn('Specific constraints failed, trying fallback:', specificError);
                // Fallback ไปใช้การตั้งค่าพื้นฐาน
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false
                });
            }
            
            const video = document.getElementById('cameraPreview');
            video.srcObject = stream;
            
            // รอให้วิดีโอโหลดแล้วปรับขนาด UI ให้เหมาะสม
            video.onloadedmetadata = () => {
                console.log(`📹 Camera resolution: ${video.videoWidth}x${video.videoHeight}`);
                adjustUIForScreenSize(isLargeScreen, isMobile);
            };
            
        } catch (e) {
            showStatus('ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตให้ใช้กล้องในเบราว์เซอร์', 'error');
            console.error('Camera error:', e);
        }
    }
    
    // ปรับ UI ให้เหมาะสมกับขนาดหน้าจอ
    function adjustUIForScreenSize(isLargeScreen, isMobile) {
        const faceGuide = document.getElementById('faceGuide');
        const video = document.getElementById('cameraPreview');
        
        if (isLargeScreen && !isMobile) {
            // จอใหญ่: ปรับขนาด face guide ให้เหมาะสม
            faceGuide.style.width = '320px';
            faceGuide.style.height = '420px';
            faceGuide.style.borderWidth = '4px';
            
            // ปรับ object-fit เพื่อให้เห็นภาพกว้างขึ้น
            video.style.objectFit = 'cover';
            video.style.objectPosition = 'center';
            video.style.transform = 'scaleX(-1)';
            
            console.log('🖥️ UI adjusted for large screen');
        } else {
            // มือถือ: ใช้ contain และ scale เล็กลงเพื่อแสดงมุมมองกว้างขึ้น
            faceGuide.style.width = '260px';
            faceGuide.style.height = '350px';
            faceGuide.style.borderWidth = '2.5px';
            faceGuide.style.top = '50%';
            
            // บังคับใช้ object-fit: contain และ scale ที่เล็กลง
            video.style.objectFit = 'contain';
            video.style.objectPosition = 'center';
            video.style.transform = 'scaleX(-1) scale(0.7)';
            video.style.transformOrigin = 'center';
            
            // ปรับ container ให้จัดกึ่งกลาง
            const container = document.getElementById('cameraContainer');
            if (container) {
                container.style.display = 'flex';
                container.style.alignItems = 'center';
                container.style.justifyContent = 'center';
                container.style.background = '#000';
            }
            
            console.log('📱 UI adjusted for mobile with contain + scale(0.7) for wider view');
        }
    }

    // ===== 3. ส่งภาพสแกนชั่วคราวให้ Backend (Smart Balanced Processing) =====
    async function scanFrame() {
        if (isVerifying || !stream || stopScanning) return;
        
        const video = document.getElementById('cameraPreview');
        if (video.videoWidth === 0) {
            scanTimeout = setTimeout(scanFrame, 1000);
            return;
        }
        
        isVerifying = true;
        scanAttempts++;
        
        // Play soft scan sound every few attempts
        if (scanAttempts % 3 === 0) {
            playScanSound();
        }

        try {
            // Use Smart Scanner if available, fallback to legacy
            if (smartScanner && profileDescriptor) {
                const result = await smartScanner.scanFrame(video, profileDescriptor);
                
                if (result) {
                    await processScanResult(result);
                    return;
                }
            } else {
                // Legacy fallback
                await legacyScanFrame(video);
            }
        } catch (error) {
            console.error('Smart scan error, falling back:', error);
            await legacyScanFrame(video);
        } finally {
            isVerifying = false;
            
            // Schedule next scan with adaptive timing
            const nextInterval = smartScanner ? smartScanner.currentInterval : 1000;
            if (!stopScanning) {
                scanTimeout = setTimeout(scanFrame, nextInterval);
            }
        }
    }

    async function processScanResult(result) {
        const rtScore = document.getElementById('realtimeScore');
        
        if (rtScore) {
            rtScore.style.display = 'block';
            rtScore.textContent = `${result.source}: ${result.score.toFixed(1)}% (${result.processingTime}ms)`;
            rtScore.style.color = result.passed ? '#10b981' : '#f59e0b';
        }

        // Success handling
        if (result.passed && result.confidence > 0.7) {
            stopScanning = true;
            isScanningActive = false;
            clearTimeout(scanTimeout);
            
            const guide = document.getElementById('faceGuide');
            if (guide) guide.classList.replace('scanning-ring', 'success-ring');
            
            playSuccessSound();
            
            // Trigger photo capture for server-side verification
            capturePhoto(true);
        }
    }

    async function legacyScanFrame(video) {
        // Original legacy scanning logic for fallback
        const MAX_DIM = 480;
        let targetWidth = video.videoWidth;
        let targetHeight = video.videoHeight;
        
        if (targetWidth > targetHeight) {
            if (targetWidth > MAX_DIM) {
                targetHeight = Math.round(targetHeight * (MAX_DIM / targetWidth));
                targetWidth = MAX_DIM;
            }
        } else {
            if (targetHeight > MAX_DIM) {
                targetWidth = Math.round(targetWidth * (MAX_DIM / targetHeight));
                targetHeight = MAX_DIM;
            }
        }

        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        const ctx = canvas.getContext('2d');
        
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        
        // Low light detection and flash handling
        handleLowLightDetection(ctx, canvas);

        const base64Image = canvas.toDataURL('image/jpeg', 0.6);
        
        // JS Mode or Python verification
        if (isJsModeActive && isFaceApiLoaded && profileDescriptor.embedding_128d) {
            await performJsVerification(canvas);
        } else {
            await performPythonVerification(base64Image);
        }
    }

    function handleLowLightDetection(ctx, canvas) {
        try {
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            let colorSum = 0;
            let samples = 0;

            for (let i = 0; i < data.length; i += 40) {
                const r = data[i], g = data[i+1], b = data[i+2];
                colorSum += (r * 299 + g * 587 + b * 114) / 1000;
                samples++;
            }

            const avgBrightness = colorSum / samples;
            const video   = document.getElementById('cameraPreview');
            const guide   = document.getElementById('faceGuide');
            const infoEl  = document.getElementById('scanInstructions');

            // Toggle flash on/off with hysteresis
            if (avgBrightness < 75)  isFlashOn = true;
            if (avgBrightness > 110) isFlashOn = false;

            if (isFlashOn) {
                // ★ KEY FIX: boost the VIDEO itself so the face is actually lit ★
                const boost = Math.min(3.5, 90 / Math.max(avgBrightness, 10));
                if (video) video.style.filter = `brightness(${boost.toFixed(2)}) contrast(1.15)`;

                // Also re-draw canvas with the same filter so AI gets a brighter image
                if (ctx && video) {
                    ctx.filter = `brightness(${boost.toFixed(2)}) contrast(1.15)`;
                    ctx.translate(canvas.width, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.filter = 'none';
                }

                // Keep dark vignette (not white) — white blinds the user
                if (guide) guide.style.boxShadow = '0 0 0 4000px rgba(0,0,0,0.55)';

                // Show hint
                if (infoEl && !stopScanning) {
                    infoEl.textContent = 'สภาวะแสงน้อยเกินไป — กำลังปรับความสว่างกล้องอัตโนมัติ...';
                }
            } else {
                if (video) video.style.filter = '';   // reset video filter
                if (guide) guide.style.boxShadow = '0 0 0 4000px rgba(0,0,0,0.6)';
                // restore scan instruction if it was overridden by brightness message
                if (infoEl && infoEl.textContent.includes('แสงน้อย')) {
                    infoEl.textContent = 'กำลังสแกนใบหน้าแบบเรียลไทม์... กรุณามองกล้อง';
                }
            }

            console.debug(`[Brightness] avg=${avgBrightness.toFixed(1)} flash=${isFlashOn}`);
        } catch (e) {
            console.warn('Brightness check error', e);
        }
    }
        
        // ========================================
        // JS MODE (Face-api.js 128D) - Enhanced with Smart Processing
        // ========================================
        async function performJsVerification(canvas) {
            try {
                const detection = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
                let score = 0;
                let passed = false;
                
                if (detection) {
                    const distance = faceapi.euclideanDistance(profileDescriptor.embedding_128d, detection.descriptor);
                    score = Math.max(0, (1 - distance) * 100);
                    passed = distance < 0.5;
                }
                
                const rtScore = document.getElementById('realtimeScore');
                if (rtScore) {
                    rtScore.style.display = 'block';
                    rtScore.textContent = 'JS (128D): ' + score.toFixed(1) + '%';
                    rtScore.style.color = passed ? '#10b981' : '#f59e0b';
                }

                if (passed) {
                    await processScanResult({
                        confidence: score / 100,
                        passed: true,
                        score: score,
                        source: 'js_primary',
                        processingTime: Date.now() % 1000
                    });
                }
                
            } catch (error) {
                console.warn('JS verification error:', error);
            }
        }

        async function performPythonVerification(base64Image) {
            try {
                const startTime = Date.now();
                const response = await fetch('/api/face/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify({
                        image: base64Image,
                        mode: 'python',
                        priority: 'accuracy'
                    })
                });

                const processingTime = Date.now() - startTime;
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success !== false) {
                    const score = result.score_percentage || 0;
                    const passed = result.is_match || false;

                    const rtScore = document.getElementById('realtimeScore');
                    if (rtScore) {
                        rtScore.style.display = 'block';
                        rtScore.textContent = `Python (512D): ${score.toFixed(1)}% (${result.processing_ms || processingTime}ms)`;
                        rtScore.style.color = passed ? '#10b981' : '#f59e0b';
                    }

                    if (passed) {
                        await processScanResult({
                            confidence: score / 100,
                            passed: true,
                            score: score,
                            source: 'python_primary',
                            processingTime: result.processing_ms || processingTime
                        });
                    }
                } else if (result.fallback_recommended) {
                    // Switch to JS mode automatically
                    console.log('🔄 Python failed, switching to JS mode');
                    isJsModeActive = true;
                    pythonFailCount++;
                }
                
            } catch (error) {
                console.warn('Python verification failed:', error);
                pythonFailCount++;
                
                if (pythonFailCount >= 2 && isFaceApiLoaded && profileDescriptor.embedding_128d) {
                    console.log('🔄 Switching to JS mode due to Python failures');
                    isJsModeActive = true;
                }
            }
        }

        // Enhanced JS verification with API support
        async function loadJsDescriptorFromApi() {
            if (profileDescriptor && profileDescriptor.embedding_128d) {
                return true; // Already loaded
            }

            try {
                const response = await fetch('/api/face/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        image: 'placeholder', // Not used for JS mode
                        mode: 'js'
                    })
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success && result.descriptor_128d) {
                        if (!profileDescriptor) {
                            profileDescriptor = {};
                        }
                        profileDescriptor.embedding_128d = new Float32Array(result.descriptor_128d);
                        console.log('✅ 128D descriptor loaded from API');
                        return true;
                    }
                }
            } catch (error) {
                console.warn('Failed to load JS descriptor from API:', error);
            }

            return false;
        }

    // ===== 4. ถ่ายรูปจริงเมื่อ AI ให้ผ่าน =====
    function capturePhoto(autoSubmit = false) {
        stopScanning = true;
        isScanningActive = false;
        clearTimeout(scanTimeout);
        stopRealtimeDetection();
        
        const video = document.getElementById('cameraPreview');
        const canvas = document.getElementById('captureCanvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        document.getElementById('selfieData').value = canvas.toDataURL('image/jpeg', 0.8);
        canvas.style.display = 'block';
        document.getElementById('faceGuide').style.display = 'none';
        
        // Draw to thumb
        const thumbCanvas = document.getElementById('selfieThumb');
        const thumbCtx = thumbCanvas.getContext('2d');
        thumbCtx.drawImage(canvas, 0, 0, thumbCanvas.width, thumbCanvas.height);
        
        if (autoSubmit) {
            submitSelfie();
        } else {
            showComparisonResult(0, false);
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').textContent = 'บันทึกรูปนี้';
            document.getElementById('submitBtn').onclick = submitSelfie;
        }
    }
    
    function showComparisonResult(score, passed) {
        document.querySelector('.status-container').style.display = 'none';
        const resDiv = document.getElementById('comparisonResult');
        resDiv.style.display = 'flex';
        
        if (passed) {
            document.getElementById('matchScoreText').textContent = score.toFixed(1) + '%';
            document.getElementById('matchScoreText').style.color = '#10b981';
            document.getElementById('matchStatusText').textContent = 'ใบหน้าตรงกับรูปโปรไฟล์ (AI ยืนยันแล้ว)';
        } else {
            document.getElementById('matchScoreText').textContent = 'รอการตรวจสอบ';
            document.getElementById('matchScoreText').style.color = '#f59e0b';
            document.getElementById('matchStatusText').textContent = 'ส่งรูปเพื่อให้เจ้าหน้าที่ตรวจสอบภายหลัง';
        }
    }

    function showStatus(msg, type='info') {
        const el = document.getElementById('statusMsg');
        el.textContent = msg;
        el.style.display = 'block';
        if (type === 'error') el.style.background = 'rgba(239,68,68,0.9)';
        else if (type === 'success') el.style.background = 'rgba(16,185,129,0.9)';
        else el.style.background = 'rgba(234,88,12,0.9)';
    }

    // ===== 5. ส่งฟอร์มพร้อม GPS =====
    function submitSelfie() {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'กำลังบันทึก...';
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    document.getElementById('qr_lat').value = position.coords.latitude;
                    document.getElementById('qr_lng').value = position.coords.longitude;
                    document.getElementById('selfieForm').submit();
                },
                (error) => {
                    console.warn("GPS Error", error);
                    document.getElementById('selfieForm').submit();
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        } else {
            document.getElementById('selfieForm').submit();
        }
    }
    </script>

</body>
</html>