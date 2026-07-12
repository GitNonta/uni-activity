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
                <button type="button" id="captureBtn" class="btn btn-primary btn-lg" style="flex:1;max-width:250px;" onclick="capturePhoto()" disabled>
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:.25rem;"><circle cx="12" cy="13" r="3"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/></svg>
                    ถ่ายรูป
                </button>
            </div>
            <div id="retakeControls" style="display:none;gap:.5rem;justify-content:center;margin-bottom:1rem;">
                <button type="button" class="btn btn-outline" onclick="retakePhoto()">ถ่ายใหม่</button>
                <button type="button" id="submitBtn" class="btn btn-success" onclick="submitSelfie()" disabled>ยืนยันและส่ง</button>
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
</style>
@endsection

@section('scripts')
{{-- face-api.js CDN --}}
<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
const PROFILE_PHOTO_URL = @json($profilePhotoUrl);
const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';
const THRESHOLD = 60; // ค่า threshold 60%

let stream = null;
let capturedImageData = null;
let profileDescriptor = null;
let modelsLoaded = false;

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
        document.getElementById('captureBtn').disabled = false;

        // โหลด descriptor ของรูปโปรไฟล์ล่วงหน้า
        if (PROFILE_PHOTO_URL) {
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
    } catch (e) {
        document.getElementById('loadingText').textContent = 'โหลดโมเดลไม่สำเร็จ — กรุณารีเฟรชหน้า';
        console.error('Model loading error:', e);
    }
}

// ===== 4. ถ่ายรูป =====
async function capturePhoto() {
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

    // Switch buttons
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

    // Face compare
    await compareFaces(canvas);
}

// ===== 5. เปรียบเทียบใบหน้า =====
async function compareFaces(selfieCanvas) {
    const resultDiv = document.getElementById('comparisonResult');
    const submitBtn = document.getElementById('submitBtn');

    if (!modelsLoaded) {
        showStatus('โมเดล AI ยังโหลดไม่เสร็จ กรุณารอสักครู่', 'warning');
        submitBtn.disabled = false;
        return;
    }

    showStatus('กำลังวิเคราะห์ใบหน้า...', 'info');

    try {
        // Detect face in selfie
        const selfieDet = await faceapi.detectSingleFace(selfieCanvas, new faceapi.TinyFaceDetectorOptions())
            .withFaceLandmarks(true)
            .withFaceDescriptor();

        if (!selfieDet) {
            showStatus('ไม่พบใบหน้าในรูป กรุณาถ่ายใหม่ให้เห็นหน้าชัดเจน', 'error');
            document.getElementById('faceMatchScore').value = '';
            submitBtn.disabled = false; // ยังให้ส่งได้ admin จะตรวจ
            return;
        }

        // ถ้าไม่มีรูปโปรไฟล์ → ไม่สามารถเปรียบเทียบได้
        if (!profileDescriptor) {
            showStatus('ไม่มีรูปโปรไฟล์ในระบบ — บันทึก Selfie ไว้เพื่อตรวจสอบภายหลัง', 'warning');
            document.getElementById('faceMatchScore').value = '';
            submitBtn.disabled = false;
            return;
        }

        // Compare
        const distance = faceapi.euclideanDistance(profileDescriptor, selfieDet.descriptor);
        // Convert euclidean distance to similarity percentage
        // distance 0 = identical, ~0.6 = threshold, >1.0 = very different
        const similarity = Math.max(0, Math.min(100, (1 - distance) * 100));
        const score = Math.round(similarity * 100) / 100;

        document.getElementById('faceMatchScore').value = score;

        // Show result
        resultDiv.style.display = 'block';
        const passed = score >= THRESHOLD;

        if (passed) {
            resultDiv.style.background = '#dcfce7';
            resultDiv.style.border = '1px solid #86efac';
            document.getElementById('matchIcon').textContent = '✅';
            document.getElementById('matchScoreText').style.color = '#15803d';
            document.getElementById('matchScoreText').textContent = 'ความคล้าย: ' + score.toFixed(1) + '%';
            document.getElementById('matchStatusText').textContent = 'ผ่านการยืนยันตัวตน';
            document.getElementById('matchStatusText').style.color = '#15803d';
            showStatus('✅ ยืนยันตัวตนสำเร็จ! กดปุ่ม "ยืนยันและส่ง" เพื่อดำเนินการต่อ', 'success');
        } else {
            resultDiv.style.background = '#fef3c7';
            resultDiv.style.border = '1px solid #fde68a';
            document.getElementById('matchIcon').textContent = '⚠️';
            document.getElementById('matchScoreText').style.color = '#b45309';
            document.getElementById('matchScoreText').textContent = 'ความคล้าย: ' + score.toFixed(1) + '%';
            document.getElementById('matchStatusText').textContent = 'ความคล้ายต่ำกว่าเกณฑ์ — ผู้จัดกิจกรรมจะตรวจสอบภายหลัง';
            document.getElementById('matchStatusText').style.color = '#92400e';
            showStatus('⚠️ ความคล้ายต่ำ — Selfie จะถูกส่งให้ผู้จัดตรวจสอบ', 'warning');
        }

        submitBtn.disabled = false;
    } catch (e) {
        console.error('Face comparison error:', e);
        showStatus('เกิดข้อผิดพลาดในการวิเคราะห์ กรุณาถ่ายใหม่', 'error');
        submitBtn.disabled = false;
    }
}

// ===== 6. ถ่ายใหม่ =====
function retakePhoto() {
    document.getElementById('captureCanvas').style.display = 'none';
    document.getElementById('captureControls').style.display = 'flex';
    document.getElementById('retakeControls').style.display = 'none';
    document.getElementById('comparisonResult').style.display = 'none';
    document.getElementById('statusMsg').style.display = 'none';
    capturedImageData = null;
}

// ===== 7. ส่ง Selfie =====
function submitSelfie() {
    if (!capturedImageData) {
        showStatus('กรุณาถ่ายรูปก่อน', 'error');
        return;
    }
    
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<div style="width:20px;height:20px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;display:inline-block;vertical-align:middle;margin-right:8px;"></div> กำลังบันทึก...';
    
    // ตั้งค่าข้อมูลรูปภาพและคะแนน
    document.getElementById('selfieData').value = capturedImageData;
    const scoreText = document.getElementById('matchScoreText').textContent;
    let scoreMatch = scoreText.match(/(\d+\.\d+|\d+)/);
    if (scoreMatch) {
        document.getElementById('faceMatchScore').value = scoreMatch[0];
    }

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
    if (stream) stream.getTracks().forEach(t => t.stop());
});
</script>
@endsection
