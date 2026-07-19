<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ยืนยันตัวตน - Selfie</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/face-scan-animation.css') }}?v={{ filemtime(public_path('css/face-scan-animation.css')) }}">
    <style>
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; font-family: 'Sarabun', sans-serif; }
        #cameraContainer { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }
        #cameraPreview { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
        .overlay-ui { position: absolute; z-index: 10; pointer-events: none; }
        
        .top-bar { top: 0; left: 0; right: 0; padding: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; background: linear-gradient(to bottom, rgba(0,0,0,0.5), transparent); pointer-events: auto; }
        .back-btn { color: white; background: rgba(0,0,0,0.3); border-radius: 50%; padding: 12px; backdrop-filter: blur(8px); display: inline-flex; transition: background 0.3s; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); }
        .back-btn:active { background: rgba(255,255,255,0.4); }

        .status-container { position: absolute; bottom: 10%; left: 0; right: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 1.5rem; pointer-events: auto; }
        .status-text { color: white; background: rgba(0,0,0,0.6); padding: 12px 24px; border-radius: 30px; text-align: center; font-size: 1.1rem; backdrop-filter: blur(8px); transition: all 0.3s; border: 1px solid rgba(255,255,255,0.15); margin-bottom: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.3); max-width: 100%; width: 100%; }
        
        #faceGuide { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%); width: 280px; height: 380px; border: 3px solid rgba(255,255,255,0.6); border-radius: 120px; box-shadow: 0 0 0 4000px rgba(0,0,0,0.6); transition: border-color 0.3s, box-shadow 0.8s ease; overflow: hidden; }
        
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
        
        .scanning-ring { border-color: #4f46e5 !important; }
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
            
            <!-- 3D Face Mesh Canvas -->
            <canvas id="faceMesh3DCanvas" style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 25; transform: scaleX(-1);"></canvas>
            
            <!-- Face Bounding Box (fallback) -->
            <canvas id="faceLandmarksCanvas" style="position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 24; display: none;"></canvas>
            
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
        
        <div id="realtimeScore" style="display:none; font-weight:bold; font-size:1.2rem; margin-bottom: 15px; background:rgba(0,0,0,0.6); padding:8px 20px; border-radius:20px; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15);"></div>

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
        <strong style="display:block;margin-bottom:5px;">⚠️ ไม่มีรูปโปรไฟล์</strong>
        <span style="font-size:0.9rem;">ระบบจะบันทึก Selfie ไว้แต่ไม่สามารถเปรียบเทียบใบหน้าได้ กรุณาอัปโหลดรูปโปรไฟล์ภายหลัง</span>
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

    <script>
        const faceScanMethod = '{{ $faceScanMethod ?? "python" }}';
        let isJsModeActive = (faceScanMethod === 'js');
        let profileDescriptor = null;
        let pythonFailCount = 0;
        let isFaceApiLoaded = false;
        
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

        await startCamera();
        
        const guide = document.getElementById('faceGuide');
        if (guide) guide.classList.add('scanning-ring');
        
        const instructionEl = document.getElementById('scanInstructions');
        if (instructionEl) instructionEl.textContent = 'กำลังสแกนใบหน้าแบบเรียวไทม์... กรุณามองกล้อง';
        
        scanTimeout = setTimeout(scanFrame, 1000);
    });

    // ===== 2. เปิดกล้องหน้า =====
    async function startCamera() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user' },
                audio: false
            });
            document.getElementById('cameraPreview').srcObject = stream;
        } catch (e) {
            showStatus('ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตให้ใช้กล้องในเบราว์เซอร์', 'error');
            console.error('Camera error:', e);
        }
    }

    // ===== 3. ส่งภาพสแกนชั่วคราวให้ Backend (Python AI Server - InsightFace 512D) =====
    async function scanFrame() {
        if (isVerifying || !stream || stopScanning) return;
        
        const video = document.getElementById('cameraPreview');
        if (video.videoWidth === 0) {
            scanTimeout = setTimeout(scanFrame, 500);
            return;
        }
        
        isVerifying = true;
        scanAttempts++;
        
        // Play soft scan sound every few attempts
        if (scanAttempts % 3 === 0) {
            playScanSound();
        }
        
        // ใช้ความละเอียด 480px เพื่อให้รองรับ "ภาพมุมกว้าง" และใบหน้าที่อยู่ไกลได้ดีขึ้น
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
        
        // Low light detection
        try {
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            let colorSum = 0;
            let samples = 0;
            // Sample every 10th pixel to save CPU
            for (let i = 0; i < data.length; i += 40) {
                const r = data[i];
                const g = data[i+1];
                const b = data[i+2];
                // Perceived brightness formula
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                colorSum += brightness;
                samples++;
            }
            const avgBrightness = colorSum / samples;
            const guide = document.getElementById('faceGuide');
            if (guide) {
                // If it's too dark, turn flash on. Once it's on, keep it on until scanning is done.
                if (avgBrightness < 75) {
                    isFlashOn = true;
                }
                if (isFlashOn) {
                    // Too dark -> soft white screen flash
                    guide.style.boxShadow = '0 0 0 4000px rgba(255,255,255,0.85)';
                } else {
                    // Normal -> dark overlay
                    guide.style.boxShadow = '0 0 0 4000px rgba(0,0,0,0.6)';
                }
            }
        } catch (e) {
            console.warn("Brightness check error", e);
        }

        const base64Image = canvas.toDataURL('image/jpeg', 0.6);
        
        // ========================================
        // JS MODE (Face-api.js 128D) - เฉพาะเมื่อเลือกใช้
        // ========================================
        if (isJsModeActive && isFaceApiLoaded && profileDescriptor) {
            // --- JS FACE API MODE (128D descriptor) ---
            // ใช้เมื่อ: Python AI Server ไม่พร้อมหรือเลือกใช้ Client-side
            try {
                // Use face-api to detect face on canvas
                const detection = await faceapi.detectSingleFace(canvas).withFaceLandmarks().withFaceDescriptor();
                let score = 0;
                let passed = false;
                
                if (detection) {
                    const distance = faceapi.euclideanDistance(profileDescriptor, detection.descriptor);
                    score = Math.max(0, (1 - distance) * 100);
                    // threshold typically 0.5-0.6 for euclidian. 0.5 -> score 50%.
                    // let's just use distance < 0.5 as passed.
                    passed = distance < 0.5;
                }
                
                const rtScore = document.getElementById('realtimeScore');
                if (rtScore) {
                    rtScore.style.display = 'block';
                    rtScore.textContent = 'JS (128D): ' + score.toFixed(1) + '%';
                    rtScore.style.color = passed ? '#10b981' : '#f59e0b';
                }

                if (passed) {
                    stopScanning = true;
                    isScanningActive = false;
                    clearTimeout(scanTimeout);
                    const guide = document.getElementById('faceGuide');
                    if (guide) guide.classList.replace('scanning-ring', 'success-ring');
                    
                    // Play success sound
                    playSuccessSound();
                    
                    // We need to inject these to the form before submitting
                    let jsScoreInput = document.createElement('input');
                    jsScoreInput.type = 'hidden';
                    jsScoreInput.name = 'js_face_match_score';
                    jsScoreInput.value = score;
                    document.getElementById('selfieForm').appendChild(jsScoreInput);
                    
                    let jsPassedInput = document.createElement('input');
                    jsPassedInput.type = 'hidden';
                    jsPassedInput.name = 'js_face_match_passed';
                    jsPassedInput.value = '1';
                    document.getElementById('selfieForm').appendChild(jsPassedInput);
                    
                    capturePhoto(true); // submit
                    showComparisonResult(score, true);
                    return;
                } else {
                    const guide = document.getElementById('faceGuide');
                    if (guide) {
                        guide.classList.replace('scanning-ring', 'error-ring');
                        playErrorSound();
                        setTimeout(() => { 
                            guide.classList.replace('error-ring', 'scanning-ring'); 
                        }, 300);
                    }
                    
                    if (scanAttempts >= MAX_ATTEMPTS) {
                        stopScanning = true;
                        clearTimeout(scanTimeout);
                        document.getElementById('manualCaptureBtn').style.display = 'inline-block';
                        const statusText = document.getElementById('statusText');
                        if (statusText) statusText.textContent = 'ตรวจสอบไม่ผ่าน กรุณาใช้ปุ่มถ่ายรูป';
                        return;
                    }
                }
            } catch (e) {
                console.error('JS Face API detection error', e);
            }
            scanAttempts++;
            isVerifying = false;
            // เพิ่ม delay เป็น 1.5 วินาที เพื่อไม่ให้ภาพจากกล้องค้างเวลาใช้ภาพความละเอียด 480px
            scanTimeout = setTimeout(scanFrame, 1500);
            return;
        }
        
        // ========================================
        // PYTHON AI SERVER MODE - InsightFace 512D (Default & Recommended)
        // ========================================
        // ใช้ InsightFace 512D descriptor - ความแม่นยำสูงกว่า face-api.js
        // Python AI Server ที่ /ai_service/server.py
        try {
            // Setup timeout abort controller
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
            
            const response = await fetch("{{ route('checkin.verify_frame', $token) }}", {
                signal: controller.signal,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ image: base64Image })
            });
            
            clearTimeout(timeoutId);
            const result = await response.json();
            
            const rtScore = document.getElementById('realtimeScore');
            if (rtScore && result.score_percentage !== undefined) {
                rtScore.style.display = 'block';
                rtScore.textContent = 'InsightFace (512D): ' + result.score_percentage.toFixed(1) + '%';
                rtScore.style.color = result.score_percentage >= THRESHOLD ? '#10b981' : '#f59e0b';
            }

            if (result.is_match && result.score_percentage >= THRESHOLD) {
                stopScanning = true;
                isScanningActive = false;
                clearTimeout(scanTimeout);
                const guide = document.getElementById('faceGuide');
                if (guide) guide.classList.replace('scanning-ring', 'success-ring');
                
                // Play success sound
                playSuccessSound();
                
                capturePhoto(true);
                showComparisonResult(result.score_percentage, true);
                return;
            } else {
                const guide = document.getElementById('faceGuide');
                if (guide) {
                    guide.classList.replace('scanning-ring', 'error-ring');
                    playErrorSound();
                    setTimeout(() => { 
                        guide.classList.replace('error-ring', 'scanning-ring'); 
                    }, 300);
                }
                
                if (scanAttempts >= MAX_ATTEMPTS) {
                    stopScanning = true;
                    clearTimeout(scanTimeout);
                    document.getElementById('manualCaptureBtn').style.display = 'inline-block';
                    document.getElementById('scanInstructions').textContent = 'ไม่สามารถยืนยันใบหน้าอัตโนมัติได้ กรุณากดปุ่มเพื่อสแกนด้วยตนเอง';
                    isVerifying = false;
                    return;
                }
            }
        } catch (e) {
            console.error("Frame verify error (Python AI Server):", e);
            // อาจจะ fallback ไปใช้ JS mode ได้ถ้า Python server ไม่ตอบ
        }
        
        isVerifying = false;
        if (!stopScanning) {
            scanTimeout = setTimeout(scanFrame, 500);
        }
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
        else el.style.background = 'rgba(79,70,229,0.9)';
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
<!-- MediaPipe Face Mesh for 3D mask effect -->
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/control_utils/control_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js" crossorigin="anonymous"></script>

<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
    // ===== 3D Face Mesh Integration (Premium) =====
    let faceMesh3D = null;
    let use3DMesh = true;

    // ── Mesh animation state ──
    const meshState = {
        hue: 170,          // cyan start
        opacity: 0,        // fade in
        scanState: 'scanning', // 'scanning' | 'success' | 'error'
        frameCount: 0,
        lastFaceTime: 0,
    };

    // ── Color palettes per state ──
    const meshColors = {
        scanning: { h: 170, s: 100, l: 55 },  // cyan
        success:  { h: 150, s: 90,  l: 45 },  // emerald
        error:    { h:   0, s: 90,  l: 55 },  // red
    };

    // ── Key landmark indices for node dots ──
    const KEY_LANDMARKS = [
        // Eyes outline
        33, 133, 159, 145, 362, 263, 386, 374,
        // Nose
        1, 4, 94,
        // Lips
        61, 291, 0, 17,
        // Face oval
        10, 152, 234, 454, 127, 356,
        // Eyebrows
        46, 276, 55, 285,
        // Cheeks
        116, 345
    ];

    async function init3DFaceMesh() {
        const video = document.getElementById('cameraPreview');
        const canvas = document.getElementById('faceMesh3DCanvas');
        if (!canvas || !video) return false;

        // Use additive blending via composite operation
        const ctx = canvas.getContext('2d');

        try {
            const faceMesh = new FaceMesh({
                locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`
            });

            faceMesh.setOptions({
                maxNumFaces: 1,
                refineLandmarks: true,
                minDetectionConfidence: 0.5,
                minTrackingConfidence: 0.5
            });

            faceMesh.onResults(onFaceMesh3DResults);

            // Poll video frames manually to avoid conflicts with existing camera
            function pollFrame() {
                if (!use3DMesh) return;
                if (isScanningActive && video.videoWidth > 0) {
                    faceMesh.send({ image: video }).catch(() => {});
                }
                requestAnimationFrame(pollFrame);
            }
            pollFrame();

            faceMesh3D = { faceMesh, canvas, ctx };
            console.log('✓ 3D Face Mesh (Premium) initialized');
            return true;

        } catch (error) {
            console.error('3D Face Mesh error:', error);
            use3DMesh = false;
            return false;
        }
    }

    function onFaceMesh3DResults(results) {
        if (!faceMesh3D) return;

        const { canvas, ctx } = faceMesh3D;

        // Sync canvas size to video
        const video = document.getElementById('cameraPreview');
        if (canvas.width !== (results.image.width || video.videoWidth)) {
            canvas.width  = results.image.width  || video.videoWidth  || 640;
            canvas.height = results.image.height || video.videoHeight || 480;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!isScanningActive) {
            // Fade out
            meshState.opacity = Math.max(0, meshState.opacity - 0.05);
            return;
        }

        const hasFace = results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0;

        // Smooth fade in/out
        if (hasFace) {
            meshState.opacity = Math.min(1, meshState.opacity + 0.08);
            meshState.lastFaceTime = Date.now();
        } else {
            const elapsed = Date.now() - meshState.lastFaceTime;
            if (elapsed > 500) {
                meshState.opacity = Math.max(0, meshState.opacity - 0.04);
            }
        }

        if (hasFace && meshState.opacity > 0.05) {
            const landmarks = results.multiFaceLandmarks[0];
            draw3DFaceMesh(ctx, landmarks, canvas.width, canvas.height);
        }

        meshState.frameCount++;
    }

    function draw3DFaceMesh(ctx, landmarks, width, height) {
        const t = Date.now() / 1000;
        const op = meshState.opacity;
        const state = meshState.scanState;

        // ── Compute current color ──
        const target = meshColors[state];
        // Animate hue slowly when scanning
        if (state === 'scanning') {
            meshState.hue = target.h + Math.sin(t * 0.5) * 15; // ±15° shift
        } else {
            meshState.hue = target.h;
        }
        const hue = meshState.hue;
        const sat = target.s;
        const lit = target.l;

        // Pulse opacity for glow elements
        const pulse = 0.5 + Math.sin(t * 2) * 0.3;

        // Convert landmarks to pixel coords
        const pts = landmarks.map(lm => ({
            x: lm.x * width,
            y: lm.y * height,
            z: lm.z  // normalized depth (-1..0..1)
        }));

        // ── 1. Draw tessellation lines ──
        if (window.FACEMESH_TESSELATION) {
            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            ctx.lineWidth = 0.6;

            for (const [a, b] of FACEMESH_TESSELATION) {
                const p1 = pts[a], p2 = pts[b];
                if (!p1 || !p2) continue;

                // Depth-based brightness: closer = brighter
                const avgZ = (p1.z + p2.z) / 2;
                const depthFactor = Math.max(0.3, Math.min(1, 1 + avgZ * 2));
                const lineOpacity = op * depthFactor * 0.55;

                ctx.strokeStyle = `hsla(${hue}, ${sat}%, ${lit}%, ${lineOpacity})`;
                ctx.beginPath();
                ctx.moveTo(p1.x, p1.y);
                ctx.lineTo(p2.x, p2.y);
                ctx.stroke();
            }
            ctx.restore();
        }

        // ── 2. Draw face oval with stronger glow ──
        if (window.FACEMESH_FACE_OVAL) {
            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            ctx.lineWidth = 2;
            ctx.shadowBlur = 18;
            ctx.shadowColor = `hsla(${hue}, ${sat}%, ${lit}%, ${op * 0.9})`;
            ctx.strokeStyle = `hsla(${hue}, ${sat}%, ${lit + 10}%, ${op * (0.6 + pulse * 0.3)})`;

            ctx.beginPath();
            let first = true;
            for (const [a] of FACEMESH_FACE_OVAL) {
                const p = pts[a];
                if (!p) continue;
                if (first) { ctx.moveTo(p.x, p.y); first = false; }
                else ctx.lineTo(p.x, p.y);
            }
            ctx.closePath();
            ctx.stroke();
            ctx.restore();
        }

        // ── 3. Draw eyes / lips contours ──
        const contourGroups = [
            window.FACEMESH_LEFT_EYE,
            window.FACEMESH_RIGHT_EYE,
            window.FACEMESH_LIPS,
        ].filter(Boolean);

        for (const contour of contourGroups) {
            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            ctx.lineWidth = 1.2;
            ctx.shadowBlur = 12;
            ctx.shadowColor = `hsla(${hue}, ${sat}%, 80%, ${op * 0.7})`;
            ctx.strokeStyle = `hsla(${hue}, ${sat}%, ${lit + 15}%, ${op * 0.8})`;

            ctx.beginPath();
            let first = true;
            for (const [a] of contour) {
                const p = pts[a];
                if (!p) continue;
                if (first) { ctx.moveTo(p.x, p.y); first = false; }
                else ctx.lineTo(p.x, p.y);
            }
            ctx.closePath();
            ctx.stroke();
            ctx.restore();
        }

        // ── 4. Draw glowing node dots at key landmarks ──
        ctx.save();
        ctx.globalCompositeOperation = 'screen';

        for (let i = 0; i < KEY_LANDMARKS.length; i++) {
            const idx = KEY_LANDMARKS[i];
            const p = pts[idx];
            if (!p) continue;

            const depthFactor = Math.max(0.4, Math.min(1, 1 + p.z * 2));
            const animOffset = (i / KEY_LANDMARKS.length) * Math.PI * 2;
            const nodePulse = 0.6 + Math.sin(t * 3 + animOffset) * 0.4;
            const nodeOp = op * depthFactor * nodePulse;

            // Outer glow
            const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, 8);
            grad.addColorStop(0, `hsla(${hue}, ${sat}%, 90%, ${nodeOp * 0.9})`);
            grad.addColorStop(0.4, `hsla(${hue}, ${sat}%, ${lit}%, ${nodeOp * 0.4})`);
            grad.addColorStop(1, `hsla(${hue}, ${sat}%, ${lit}%, 0)`);

            ctx.beginPath();
            ctx.arc(p.x, p.y, 8, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();

            // Solid core dot
            ctx.beginPath();
            ctx.arc(p.x, p.y, 2, 0, Math.PI * 2);
            ctx.fillStyle = `hsla(${hue}, 100%, 95%, ${nodeOp})`;
            ctx.fill();
        }
        ctx.restore();

        // ── 5. Scan line that sweeps across face ──
        if (state === 'scanning') {
            const scanY = pts[0] ? pts[0].y : height / 2;
            const faceTop    = Math.min(...KEY_LANDMARKS.map(i => pts[i]?.y ?? height).filter(v => v !== undefined));
            const faceBottom = Math.max(...KEY_LANDMARKS.map(i => pts[i]?.y ?? 0).filter(v => v !== undefined));
            const faceHeight = faceBottom - faceTop;

            const sweepT = (t % 2) / 2; // 0→1 every 2s
            const sweepY = faceTop + sweepT * faceHeight;

            ctx.save();
            ctx.globalCompositeOperation = 'screen';
            const lineGrad = ctx.createLinearGradient(0, sweepY - 4, 0, sweepY + 4);
            lineGrad.addColorStop(0,   `hsla(${hue}, ${sat}%, ${lit}%, 0)`);
            lineGrad.addColorStop(0.5, `hsla(${hue}, ${sat}%, 90%, ${op * (0.5 + pulse * 0.4)})`);
            lineGrad.addColorStop(1,   `hsla(${hue}, ${sat}%, ${lit}%, 0)`);
            ctx.fillStyle = lineGrad;
            ctx.fillRect(0, sweepY - 4, width, 8);
            ctx.restore();
        }
    }

    // ── Sync mesh state with scan ring state changes ──
    const origClassReplace = DOMTokenList.prototype.replace;
    const faceGuide = document.getElementById('faceGuide');
    if (faceGuide) {
        const observer = new MutationObserver(() => {
            if (faceGuide.classList.contains('success-ring')) {
                meshState.scanState = 'success';
            } else if (faceGuide.classList.contains('error-ring')) {
                meshState.scanState = 'error';
                setTimeout(() => { meshState.scanState = 'scanning'; }, 500);
            } else {
                meshState.scanState = 'scanning';
            }
        });
        observer.observe(faceGuide, { attributes: true, attributeFilter: ['class'] });
    }

    // Initialize 3D mesh after a short delay
    function tryInit3DMesh() {
        if (typeof FaceMesh === 'undefined') {
            setTimeout(tryInit3DMesh, 500);
            return;
        }
        init3DFaceMesh().catch(err => {
            console.warn('3D Face Mesh not available, using 2D fallback', err);
            use3DMesh = false;
            const fb = document.getElementById('faceLandmarksCanvas');
            if (fb) fb.style.display = 'block';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(tryInit3DMesh, 1500);
    });
</script>
</body>
</html>