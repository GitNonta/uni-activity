{{-- หน้าถ่าย Selfie เพื่อยืนยันตัวตน: เปิดกล้องหน้า + AI Face Compare --}}
@extends('layouts.app')
@section('title', 'ยืนยันตัวตน - Selfie')

@section('content')
<div class="container-sm" style="padding-top:1rem;max-width:500px;margin:0 auto;">
    <div class="card">
        <div class="card-body text-center">
            {{-- Header --}}
            <div style="margin-bottom:1rem;">
                <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                    <svg width="28" height="28" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                </div>
                <h1 class="font-bold" style="font-size:1.2rem;">ยืนยันตัวตนด้วย Selfie</h1>
                <p class="text-muted text-sm">{{ $activity->title }}</p>
            </div>

            {{-- Camera Preview --}}
            <div id="cameraContainer" style="position:relative;border-radius:16px;overflow:hidden;background:#111;margin-bottom:1rem;aspect-ratio:3/4;">
                <video id="cameraPreview" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);"></video>
                {{-- Face guide overlay --}}
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
                    <div id="faceGuide" style="width:200px;height:260px;border:3px dashed rgba(255,255,255,.5);border-radius:50%;transition:border-color .3s;"></div>
                </div>
                {{-- Loading overlay --}}
                <div id="loadingOverlay" style="position:absolute;inset:0;background:rgba(0,0,0,.7);display:flex;flex-direction:column;align-items:center;justify-content:center;display:none;">
                    <div style="width:40px;height:40px;border:3px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin 1s linear infinite;"></div>
                    <p style="color:#fff;margin-top:.75rem;font-size:.85rem;" id="loadingText">กำลังโหลดโมเดล AI...</p>
                </div>
                {{-- Captured photo overlay --}}
                <canvas id="captureCanvas" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:none;"></canvas>
            </div>

            {{-- Status messages --}}
            <div id="statusMsg" style="padding:.5rem;border-radius:8px;margin-bottom:1rem;font-size:.85rem;display:none;"></div>

            {{-- Capture / Retake buttons --}}
            <div id="captureControls" style="display:flex;gap:.5rem;justify-content:center;margin-bottom:1rem;">
                <p id="scanInstructions" class="text-sm font-semi text-primary">กรุณามองกล้องเพื่อสแกนใบหน้าอัตโนมัติ...</p>
                <button type="button" id="manualCaptureBtn" class="btn btn-outline btn-sm" style="display:none;" onclick="capturePhoto(true)">
                    ถ่ายภาพและส่งด้วยตัวเอง
                </button>
            </div>
            <div id="retakeControls" style="display:none;gap:.5rem;justify-content:center;margin-bottom:1rem;">
                <button type="button" id="submitBtn" class="btn btn-success btn-block" disabled>กำลังบันทึกข้อมูล...</button>
            </div>

            {{-- Face comparison result --}}
            <div id="comparisonResult" style="display:none;padding:1rem;border-radius:12px;margin-bottom:1rem;">
                <div style="display:flex;align-items:center;justify-content:center;gap:1rem;margin-bottom:.75rem;">
                    <div style="text-align:center;">
                        <img id="profileThumb" src="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;">
                        <p class="text-xs text-muted" style="margin-top:.25rem;">รูปในระบบ</p>
                    </div>
                    <div>
                        <span id="matchIcon" style="font-size:1.5rem;">⟷</span>
                    </div>
                    <div style="text-align:center;">
                        <canvas id="selfieThumb" width="64" height="64" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;"></canvas>
                        <p class="text-xs text-muted" style="margin-top:.25rem;">Selfie</p>
                    </div>
                </div>
                <p id="matchScoreText" style="font-size:1.1rem;font-weight:700;"></p>
                <p id="matchStatusText" style="font-size:.85rem;margin-top:.25rem;"></p>
            </div>

            {{-- No profile photo warning --}}
            @if(!$profilePhotoUrl)
            <div class="alert" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;text-align:left;">
                <strong>⚠️ ไม่มีรูปโปรไฟล์</strong><br>
                <span class="text-sm">ระบบจะบันทึก Selfie ไว้แต่ไม่สามารถเปรียบเทียบใบหน้าได้ กรุณาอัปโหลดรูปโปรไฟล์ภายหลัง</span>
            </div>
            @endif

            {{-- Hidden form --}}
            <form id="selfieForm" method="POST" action="{{ route('checkin.store', $token) }}">
                @csrf
                <input type="hidden" name="latitude" id="qr_lat">
                <input type="hidden" name="longitude" id="qr_lng">
                <input type="hidden" name="selfie" id="selfieData">
                <input type="hidden" name="face_match_score" id="faceMatchScore">
            </form>

            <a href="{{ route('activities.index') }}" class="text-sm text-muted" style="display:inline-block;margin-top:.5rem;">ข้ามขั้นตอนนี้ →</a>
        </div>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
#cameraContainer video { display: block; }
.scanning-ring { border-color: #4f46e5 !important; box-shadow: 0 0 15px rgba(79,70,229,0.5); }
.success-ring { border-color: #10b981 !important; box-shadow: 0 0 15px rgba(16,185,129,0.5); }
.error-ring { border-color: #ef4444 !important; box-shadow: 0 0 15px rgba(239,68,68,0.5); }
</style>
@endsection

@section('scripts')
{{-- face-api.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
const PROFILE_PHOTO_URL = '{{ $profilePhotoUrl }}';
const THRESHOLD = 60; // คะแนนความเหมือนขั้นต่ำ (%)
const PRECOMPUTED_DESCRIPTOR = @json(auth()->user()->face_descriptor);

let stream = null;
let capturedImageData = null;
let profileDescriptor = null;
let modelsLoaded = false;
let scanningInterval = null;
let scanAttempts = 0;
const MAX_ATTEMPTS_BEFORE_MANUAL = 10; // After 10 failed auto-scans, show manual button

// ===== 1. เริ่มระบบ =====
document.addEventListener('DOMContentLoaded', async () => {
    await startCamera();
    await loadModels();
});

// ===== 2. เปิดกล้องหน้า =====
async function startCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
            audio: false
        });
        document.getElementById('cameraPreview').srcObject = stream;
    } catch (e) {
        showStatus('ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตให้ใช้กล้องในเบราว์เซอร์', 'error');
        console.error('Camera error:', e);
    }
}

// ===== 3. โหลดโมเดล face-api.js =====
async function loadModels() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = 'flex';

    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        document.getElementById('loadingText').textContent = 'กำลังโหลดโมเดลจดจำใบหน้า...';
        await faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

        modelsLoaded = true;

        // โหลด descriptor ของรูปโปรไฟล์ล่วงหน้า
        if (PRECOMPUTED_DESCRIPTOR && Array.isArray(PRECOMPUTED_DESCRIPTOR) && PRECOMPUTED_DESCRIPTOR.length === 128) {
            profileDescriptor = new Float32Array(PRECOMPUTED_DESCRIPTOR);
            document.getElementById('loadingText').textContent = 'พบข้อมูลใบหน้าจากระบบ...';
            if (PROFILE_PHOTO_URL) {
                document.getElementById('profileThumb').src = PROFILE_PHOTO_URL;
            }
            await new Promise(resolve => setTimeout(resolve, 300));
        } else if (PROFILE_PHOTO_URL) {
            // Fallback: คำนวณใหม่จากรูป
            document.getElementById('loadingText').textContent = 'กำลังวิเคราะห์รูปโปรไฟล์...';
            try {
                const img = await faceapi.fetchImage(PROFILE_PHOTO_URL);
                const det = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions())
                    .withFaceLandmarks(true)
                    .withFaceDescriptor();
                if (det) {
                    profileDescriptor = det.descriptor;
                    document.getElementById('profileThumb').src = PROFILE_PHOTO_URL;
                }
            } catch (e) {
                console.warn('Cannot analyze profile photo:', e);
            }
        }

        overlay.style.display = 'none';
        
        // เริ่มสแกนเรียวไทม์ทันทีที่โมเดลพร้อม
        startRealTimeScan();
    } catch (e) {
        document.getElementById('loadingText').textContent = 'โหลดโมเดลไม่สำเร็จ — กรุณารีเฟรชหน้า';
        console.error('Model loading error:', e);
    }
}

// ===== 4. สแกนใบหน้าเรียวไทม์ =====
function startRealTimeScan() {
    const video = document.getElementById('cameraPreview');
    const guide = document.getElementById('faceGuide');
    
    showStatus('กำลังสแกนใบหน้า...', 'info');
    guide.classList.add('scanning-ring');
    
    scanningInterval = setInterval(async () => {
        if (!modelsLoaded || video.paused || video.ended) return;
        
        try {
            const selfieDet = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks(true)
                .withFaceDescriptor();
                
            if (selfieDet) {
                scanAttempts++;
                // 4.1 ถ้าไม่มีรูปโปรไฟล์ ให้ถือว่าผ่านเลย (เก็บข้อมูลให้แอดมินดู)
                if (!profileDescriptor) {
                    clearInterval(scanningInterval);
                    guide.classList.replace('scanning-ring', 'success-ring');
                    await capturePhoto(true); // capture and force submit
                    return;
                }
                
                // 4.2 เปรียบเทียบกับรูปโปรไฟล์
                const distance = faceapi.euclideanDistance(profileDescriptor, selfieDet.descriptor);
                const similarity = Math.max(0, Math.min(100, (1 - distance) * 100));
                const score = Math.round(similarity * 100) / 100;
                
                if (score >= THRESHOLD) {
                    // ผ่านการสแกน!
                    clearInterval(scanningInterval);
                    guide.classList.replace('scanning-ring', 'success-ring');
                    document.getElementById('faceMatchScore').value = score;
                    await capturePhoto(false, score);
                } else {
                    // ไม่ผ่าน
                    guide.classList.replace('scanning-ring', 'error-ring');
                    setTimeout(() => { guide.classList.replace('error-ring', 'scanning-ring'); }, 500);
                    
                    if (scanAttempts >= MAX_ATTEMPTS_BEFORE_MANUAL) {
                        document.getElementById('manualCaptureBtn').style.display = 'inline-block';
                        document.getElementById('scanInstructions').textContent = 'ใบหน้าไม่ตรงกับระบบ หรือแสงไม่พอ หากต้องการยืนยันให้กดปุ่มด้านล่าง';
                    }
                }
            }
        } catch (e) {
            console.error("Realtime scan error:", e);
        }
    }, 800); // เช็คทุกๆ 800ms
}

// ===== 5. ถ่ายรูปและส่งข้อมูล =====
async function capturePhoto(forceSubmit = false, matchScore = null) {
    if (scanningInterval) clearInterval(scanningInterval);
    
    const video = document.getElementById('cameraPreview');
    const canvas = document.getElementById('captureCanvas');
    const ctx = canvas.getContext('2d');

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    // Mirror the image (front camera)
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0);
    ctx.setTransform(1, 0, 0, 1, 0, 0); // reset

    canvas.style.display = 'block';
    capturedImageData = canvas.toDataURL('image/jpeg', 0.85);
    document.getElementById('selfieData').value = capturedImageData;

    // Switch UI
    document.getElementById('captureControls').style.display = 'none';
    document.getElementById('retakeControls').style.display = 'flex';

    // Draw selfie thumbnail
    const thumbCanvas = document.getElementById('selfieThumb');
    const thumbCtx = thumbCanvas.getContext('2d');
    thumbCanvas.width = 128;
    thumbCanvas.height = 128;
    const size = Math.min(canvas.width, canvas.height);
    const sx = (canvas.width - size) / 2;
    const sy = (canvas.height - size) / 2;
    thumbCtx.drawImage(canvas, sx, sy, size, size, 0, 0, 128, 128);

    // Show result UI
    const resultDiv = document.getElementById('comparisonResult');
    if (matchScore !== null) {
        document.getElementById('faceMatchScore').value = matchScore;
        resultDiv.style.background = '#dcfce7';
        resultDiv.style.border = '1px solid #86efac';
        document.getElementById('matchIcon').textContent = '✅';
        document.getElementById('matchScoreText').style.color = '#15803d';
        document.getElementById('matchScoreText').textContent = 'ความคล้าย: ' + matchScore.toFixed(1) + '%';
        document.getElementById('matchStatusText').textContent = 'ผ่านการยืนยันตัวตน';
        document.getElementById('matchStatusText').style.color = '#15803d';
        resultDiv.style.display = 'block';
        showStatus('✅ ยืนยันตัวตนสำเร็จ! กำลังบันทึกข้อมูล...', 'success');
    } else {
        if (!profileDescriptor) {
            showStatus('บันทึกรูปภาพแล้ว (ไม่มีรูปโปรไฟล์) กำลังส่งข้อมูล...', 'warning');
        } else {
            showStatus('⚠️ บันทึกรูปภาพแล้ว รอผู้จัดตรวจสอบ กำลังส่งข้อมูล...', 'warning');
        }
    }

    // Auto submit form with GPS
    submitSelfie();
}

// ===== 6. ส่ง Selfie (เรียกอัตโนมัติ) =====
function submitSelfie() {
    if (!capturedImageData) return;
    
    const form = document.getElementById('selfieForm');
    
    // ขอพิกัด GPS ก่อน Submit
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                document.getElementById('qr_lat').value = pos.coords.latitude;
                document.getElementById('qr_lng').value = pos.coords.longitude;
                form.submit();
            },
            function(error) { 
                console.warn("GPS Error:", error);
                form.submit(); 
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        form.submit();
    }
}

// ===== Utility =====
function showStatus(msg, type) {
    const el = document.getElementById('statusMsg');
    el.style.display = 'block';
    el.textContent = msg;
    if (type === 'success') { el.style.background = '#dcfce7'; el.style.color = '#15803d'; el.style.border = '1px solid #86efac'; }
    else if (type === 'error') { el.style.background = '#fee2e2'; el.style.color = '#dc2626'; el.style.border = '1px solid #fca5a5'; }
    else if (type === 'warning') { el.style.background = '#fef3c7'; el.style.color = '#92400e'; el.style.border = '1px solid #fde68a'; }
    else { el.style.background = '#dbeafe'; el.style.color = '#1d4ed8'; el.style.border = '1px solid #93c5fd'; }
}

// Cleanup camera on page leave
window.addEventListener('beforeunload', () => {
    if (scanningInterval) clearInterval(scanningInterval);
    if (stream) stream.getTracks().forEach(t => t.stop());
});
</script>
@endsection
