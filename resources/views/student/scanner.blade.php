<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>สแกน QR Code</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body, html { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; font-family: 'Sarabun', sans-serif; }
        #cameraContainer { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }
        #cameraPreview { width: 100%; height: 100%; object-fit: cover; }
        .overlay-ui { position: absolute; z-index: 10; pointer-events: none; }
        
        .top-bar { top: 0; left: 0; right: 0; padding: 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; background: linear-gradient(to bottom, rgba(0,0,0,0.6), transparent); pointer-events: auto; }
        .back-btn { color: white; background: rgba(0,0,0,0.3); border-radius: 50%; padding: 12px; backdrop-filter: blur(8px); display: inline-flex; transition: background 0.3s; text-decoration: none; border: 1px solid rgba(255,255,255,0.2); }
        .back-btn:active { background: rgba(255,255,255,0.4); }

        .status-container { position: absolute; bottom: 10%; left: 0; right: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0 1.5rem; pointer-events: auto; }
        .status-text { color: white; background: rgba(0,0,0,0.6); padding: 12px 24px; border-radius: 30px; text-align: center; font-size: 1.1rem; backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.15); margin-bottom: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.3); max-width: 100%; width: 100%; transition: all 0.3s; }
        
        /* Guide area */
        #qrGuide { position: absolute; top: 45%; left: 50%; transform: translate(-50%, -50%); width: 250px; height: 250px; border-radius: 20px; box-shadow: 0 0 0 4000px rgba(0,0,0,0.65); transition: box-shadow 0.3s; }
        
        /* Corners */
        .corner { position: absolute; width: 40px; height: 40px; border-color: #10b981; border-style: solid; transition: border-color 0.3s; }
        .tl { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-radius: 12px 0 0 0; }
        .tr { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-radius: 0 12px 0 0; }
        .bl { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-radius: 0 0 0 12px; }
        .br { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-radius: 0 0 12px 0; }

        /* Scan line */
        .scan-line { position: absolute; left: 0; right: 0; height: 2px; background: #10b981; box-shadow: 0 0 10px 2px #10b981; animation: scan 2s infinite cubic-bezier(0.4, 0, 0.2, 1); opacity: 0.8; }
        @keyframes scan {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
        }

        .spinner { width: 24px; height: 24px; border: 3px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; margin-right: 10px; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div id="cameraContainer">
        <video id="cameraPreview" autoplay playsinline muted></video>
        <div id="qrGuide" class="overlay-ui">
            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>
            <div class="scan-line" id="scanLine"></div>
        </div>
        <canvas id="hiddenCanvas" style="display:none;"></canvas>
    </div>

    <!-- Top Bar -->
    <div class="overlay-ui top-bar">
        <a href="{{ route('student.my') }}" class="back-btn">
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </a>
    </div>

    <!-- Status Messages -->
    <div class="status-container overlay-ui">
        <div class="status-text" id="statusText">สแกน QR Code กิจกรรมเพื่อเข้างาน</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <script>
        const video = document.getElementById('cameraPreview');
        const canvas = document.getElementById('hiddenCanvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const statusText = document.getElementById('statusText');
        let isScanning = true;

        async function initCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false
                });
                video.srcObject = stream;
                video.setAttribute('playsinline', true);
                video.play();
                requestAnimationFrame(scanQRCode);
            } catch (err) {
                console.error("Camera access denied", err);
                statusText.innerHTML = 'ไม่สามารถเปิดกล้องได้ กรุณาให้สิทธิ์ใช้งานกล้อง';
                statusText.style.background = 'rgba(239,68,68,0.9)';
            }
        }

        function setSuccessUI() {
            document.querySelectorAll('.corner').forEach(el => el.style.borderColor = '#10b981');
            document.getElementById('scanLine').style.display = 'none';
            statusText.innerHTML = '<span class="spinner"></span> กำลังนำทาง...';
            statusText.style.background = 'rgba(16,185,129,0.9)';
            document.getElementById('qrGuide').style.boxShadow = '0 0 0 4000px rgba(0,0,0,0.8)';
        }

        function setErrorUI(msg) {
            document.querySelectorAll('.corner').forEach(el => el.style.borderColor = '#ef4444');
            statusText.innerHTML = msg;
            statusText.style.background = 'rgba(239,68,68,0.9)';
            setTimeout(() => {
                if (isScanning) {
                    document.querySelectorAll('.corner').forEach(el => el.style.borderColor = '#10b981');
                    statusText.innerHTML = 'สแกน QR Code กิจกรรมเพื่อเข้างาน';
                    statusText.style.background = 'rgba(0,0,0,0.6)';
                }
            }, 2500);
        }

        function scanQRCode() {
            if (!isScanning) return;
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                // Downscale for performance
                const w = Math.min(480, video.videoWidth);
                const h = Math.round(w * (video.videoHeight / video.videoWidth));
                canvas.width = w;
                canvas.height = h;
                
                ctx.drawImage(video, 0, 0, w, h);
                const imageData = ctx.getImageData(0, 0, w, h);
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "dontInvert",
                });
                
                if (code) {
                    if (code.data.startsWith('http://') || code.data.startsWith('https://')) {
                        isScanning = false;
                        setSuccessUI();
                        // Delay slightly for UI to show before redirect
                        setTimeout(() => {
                            window.location.href = code.data;
                        }, 500);
                        return;
                    } else {
                        setErrorUI('QR Code นี้ไม่ใช่ลิงก์ระบบกิจกรรม');
                        // Pause briefly before rescanning
                        setTimeout(() => { requestAnimationFrame(scanQRCode); }, 2500);
                        return;
                    }
                }
            }
            requestAnimationFrame(scanQRCode);
        }

        document.addEventListener('DOMContentLoaded', initCamera);
    </script>
</body>
</html>