<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ยืนยันตัวตน - Selfie</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
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
        
        #faceGuide { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%); width: 280px; height: 380px; border: 3px dashed rgba(255,255,255,0.6); border-radius: 120px; box-shadow: 0 0 0 4000px rgba(0,0,0,0.6); transition: border-color 0.3s, box-shadow 0.8s ease; }
        .scanning-ring { border-color: #4f46e5 !important; }
        .success-ring { border-color: #10b981 !important; background: rgba(16,185,129,0.15); }
        .error-ring { border-color: #ef4444 !important; background: rgba(239,68,68,0.15); }
        
        #comparisonResult { display:none; position:absolute; inset:0; z-index:20; background:rgba(0,0,0,0.85); flex-direction:column; align-items:center; justify-content:center; color:white; backdrop-filter: blur(10px); pointer-events: auto; padding: 2rem; text-align: center; }
        
        .btn-action { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 16px 32px; border-radius: 30px; font-weight: 700; font-size: 1.2rem; border: none; box-shadow: 0 4px 15px rgba(16,185,129,0.4); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; width: 100%; max-width: 300px; }
        .btn-action:disabled { background: #4b5563; box-shadow: none; cursor: not-allowed; }
        .btn-action:active:not(:disabled) { transform: scale(0.95); }
        
        .btn-outline-white { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.5); padding: 10px 20px; border-radius: 20px; font-size: 1rem; cursor: pointer; backdrop-filter: blur(4px); }
    </style>
</head>
<body>
    <div id="cameraContainer">
        <video id="cameraPreview" autoplay playsinline muted></video>
        <div id="faceGuide" class="overlay-ui"></div>
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
        
        async function initFaceApi() {
            if (isFaceApiLoaded) return;
            const statusText = document.getElementById('statusText');
            if (statusText) statusText.innerHTML = '<span class="spinner"></span> กำลังโหลดโมเดล AI บนเครื่อง...';
            
            try {
                // Models need to be loaded from a CDN or public path, we'll use jsdelivr raw github for models
                const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
                await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
                await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
                await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
                
                // Setup pre-computed descriptor or compute it
                const preComputed = {!! $profileJsDescriptor ?? 'null' !!};
                if (preComputed) {
                    profileDescriptor = new Float32Array(Object.values(preComputed));
                    console.log('Loaded JS descriptor from DB');
                } else {
                    const profileUrl = '{{ $profilePhotoUrl }}';
                    if (profileUrl) {
                        console.log('Extracting JS descriptor from image...');
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
                if (statusText) statusText.textContent = 'วิเคราะห์ใบหน้า (Client-side)';
            } catch (e) {
                console.error("FaceAPI Load Error", e);
                if (statusText) statusText.textContent = 'ไม่สามารถโหลดระบบสำรองได้';
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            if (isJsModeActive) {
                initFaceApi();
            }
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

    // ===== 3. ส่งภาพสแกนชั่วคราวให้ Backend =====
    async function scanFrame() {
        if (isVerifying || !stream || stopScanning) return;
        
        const video = document.getElementById('cameraPreview');
        if (video.videoWidth === 0) {
            scanTimeout = setTimeout(scanFrame, 500);
            return;
        }
        
        isVerifying = true;
        scanAttempts++;
        
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
        
        if (isJsModeActive && isFaceApiLoaded && profileDescriptor) {
            // --- JS FACE API MODE ---
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
                    rtScore.textContent = 'JS ความแม่นยำ: ' + score.toFixed(1) + '%';
                    rtScore.style.color = passed ? '#10b981' : '#f59e0b';
                }

                if (passed) {
                    stopScanning = true;
                    clearTimeout(scanTimeout);
                    const guide = document.getElementById('faceGuide');
                    if (guide) guide.classList.replace('scanning-ring', 'success-ring');
                    
                    // We need to inject these to the form before submitting
                    let jsScoreInput = document.createElement('input');
                    jsScoreInput.type = 'hidden';
                    jsScoreInput.name = 'js_face_match_score';
                    jsScoreInput.value = score;
                    document.getElementById('checkinForm').appendChild(jsScoreInput);
                    
                    let jsPassedInput = document.createElement('input');
                    jsPassedInput.type = 'hidden';
                    jsPassedInput.name = 'js_face_match_passed';
                    jsPassedInput.value = '1';
                    document.getElementById('checkinForm').appendChild(jsPassedInput);
                    
                    capturePhoto(true); // submit
                    showComparisonResult(score, true);
                    return;
                } else {
                    const guide = document.getElementById('faceGuide');
                    if (guide) {
                        guide.classList.replace('scanning-ring', 'error-ring');
                        setTimeout(() => { guide.classList.replace('error-ring', 'scanning-ring'); }, 300);
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
            scanTimeout = setTimeout(scanFrame, 1000);
            return;
        }
        
        // --- PYTHON AI SERVER MODE ---
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
            
            const result = await response.json();
            
            const rtScore = document.getElementById('realtimeScore');
            if (rtScore && result.score_percentage !== undefined) {
                rtScore.style.display = 'block';
                rtScore.textContent = 'ความแม่นยำ: ' + result.score_percentage.toFixed(1) + '%';
                rtScore.style.color = result.score_percentage >= THRESHOLD ? '#10b981' : '#f59e0b';
            }

            if (result.is_match && result.score_percentage >= THRESHOLD) {
                stopScanning = true;
                clearTimeout(scanTimeout);
                const guide = document.getElementById('faceGuide');
                if (guide) guide.classList.replace('scanning-ring', 'success-ring');
                
                capturePhoto(true);
                showComparisonResult(result.score_percentage, true);
                return;
            } else {
                const guide = document.getElementById('faceGuide');
                if (guide) {
                    guide.classList.replace('scanning-ring', 'error-ring');
                    setTimeout(() => { guide.classList.replace('error-ring', 'scanning-ring'); }, 300);
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
            console.error("Frame verify error:", e);
        }
        
        isVerifying = false;
        if (!stopScanning) {
            scanTimeout = setTimeout(scanFrame, 500);
        }
    }

    // ===== 4. ถ่ายรูปจริงเมื่อ AI ให้ผ่าน =====
    function capturePhoto(autoSubmit = false) {
        stopScanning = true;
        clearTimeout(scanTimeout);
        
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
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
</body>
</html>