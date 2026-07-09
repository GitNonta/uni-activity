@extends('layouts.app')
@section('title', 'สแกน QR Code')

@section('content')
<div class="scanner-container">
    <!-- Camera Viewport -->
    <div id="reader"></div>
    
    <!-- UI Overlay (Darkened with clear center) -->
    <div class="scanner-overlay">
        <!-- Top darkened area -->
        <div class="overlay-top">
            <h2 class="text-white font-bold text-xl mt-8 text-center" style="text-shadow: 0 2px 4px rgba(0,0,0,0.5);">สแกน QR Code</h2>
            <p class="text-white/80 text-sm text-center mt-2 px-6" style="text-shadow: 0 1px 2px rgba(0,0,0,0.5);">หันกล้องไปที่ QR Code ของกิจกรรมเพื่อลงทะเบียนเข้างาน หรือเช็คอินเข้าร่วมกิจกรรม</p>
        </div>
        
        <!-- Middle section with clear cutout -->
        <div class="overlay-middle">
            <div class="overlay-side"></div>
            <div class="scan-area">
                <!-- Corner brackets for Bank App look -->
                <div class="scan-corner top-left"></div>
                <div class="scan-corner top-right"></div>
                <div class="scan-corner bottom-left"></div>
                <div class="scan-corner bottom-right"></div>
                
                <!-- Scanning animation line -->
                <div class="scan-line"></div>
            </div>
            <div class="overlay-side"></div>
        </div>
        
        <!-- Bottom darkened area -->
        <div class="overlay-bottom">
            <div id="result-container" style="display:none;" class="text-center mt-4">
                <p class="text-green-400 font-bold mb-3 text-lg">สแกนสำเร็จ! กำลังนำทาง...</p>
                <div class="spinner"></div>
            </div>
            
            <!-- Fallback error text -->
            <p id="error-text" class="text-red-400 text-sm text-center mt-4 font-bold bg-black/50 mx-8 py-2 rounded-lg" style="display:none;"></p>
            
            <div class="text-center mt-8">
                <a href="{{ route('student.my') }}" class="btn" style="background:rgba(255,255,255,0.25); color:white; border-radius:30px; padding:0.6rem 2.5rem; font-weight:600; text-decoration:none; backdrop-filter:blur(4px);">ยกเลิก</a>
            </div>
        </div>
    </div>
</div>

<style>
/* Full screen override */
.container {
    padding: 0 !important;
    max-width: 100% !important;
    height: 100vh;
    overflow: hidden;
}
.bottom-nav {
    display: none !important; /* Hide bottom nav while scanning */
}
header {
    display: none !important; /* Hide header while scanning */
}
body {
    background: #000;
}

.scanner-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #000;
    z-index: 9999;
}

#reader {
    width: 100%;
    height: 100%;
    overflow: hidden;
    position: absolute;
    top: 0;
    left: 0;
}
#reader video {
    object-fit: cover !important;
    width: 100vw !important;
    height: 100vh !important;
}

.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    z-index: 10000;
    pointer-events: none;
}

/* Semi-transparent background color */
.overlay-top, .overlay-bottom, .overlay-side {
    background: rgba(0, 0, 0, 0.7);
    pointer-events: auto; /* Buttons inside should be clickable */
}

.overlay-top {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding-bottom: 2.5rem;
}

.overlay-middle {
    display: flex;
    height: 260px; /* Size of the scan square */
}

.overlay-side {
    flex: 1;
}

.scan-area {
    width: 260px;
    height: 260px;
    position: relative;
    /* Clear center */
    background: transparent;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
}

.overlay-bottom {
    flex: 1;
    padding-top: 2.5rem;
}

/* Corner Brackets */
.scan-corner {
    position: absolute;
    width: 35px;
    height: 35px;
    border-color: #10b981; /* Green color like bank */
    border-style: solid;
}
.top-left { top: -2px; left: -2px; border-width: 4px 0 0 4px; border-radius: 8px 0 0 0; }
.top-right { top: -2px; right: -2px; border-width: 4px 4px 0 0; border-radius: 0 8px 0 0; }
.bottom-left { bottom: -2px; left: -2px; border-width: 0 0 4px 4px; border-radius: 0 0 0 8px; }
.bottom-right { bottom: -2px; right: -2px; border-width: 0 4px 4px 0; border-radius: 0 0 8px 0; }

/* Scanning line animation */
.scan-line {
    width: 100%;
    height: 2px;
    background: #10b981;
    position: absolute;
    top: 0;
    left: 0;
    box-shadow: 0 0 12px 2px #10b981;
    animation: scan 2s infinite cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes scan {
    0% { top: 0; opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    100% { top: 100%; opacity: 0; }
}

@keyframes spin { 100% { transform: rotate(360deg); } }
.spinner {
    width: 32px;
    height: 32px;
    border: 3px solid rgba(255,255,255,0.2);
    border-top-color: #10b981;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}
</style>
@endsection

@section('scripts')
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const html5QrCode = new Html5Qrcode("reader");
    let isProcessing = false;

    // Start scanner automatically using back camera
    html5QrCode.start(
        { facingMode: "environment" }, 
        {
            fps: 15,
            qrbox: { width: 260, height: 260 },
            aspectRatio: window.innerHeight / window.innerWidth
        },
        (decodedText, decodedResult) => {
            if (isProcessing) return;
            
            if(decodedText.startsWith('http://') || decodedText.startsWith('https://')) {
                isProcessing = true;
                
                // Show success UI
                html5QrCode.pause();
                document.querySelector('.scan-line').style.display = 'none';
                document.querySelector('.scan-area').style.borderColor = '#10b981';
                document.querySelector('.scan-area').style.boxShadow = 'inset 0 0 0 2px #10b981, 0 0 20px rgba(16,185,129,0.5)';
                document.getElementById('result-container').style.display = 'block';
                document.getElementById('error-text').style.display = 'none';
                
                window.location.href = decodedText;
            } else {
                isProcessing = true;
                document.getElementById('error-text').innerText = "QR Code นี้ไม่ใช่ลิงก์ระบบกิจกรรม";
                document.getElementById('error-text').style.display = 'block';
                
                // Flash red
                document.querySelector('.scan-area').style.boxShadow = 'inset 0 0 0 2px #ef4444, 0 0 20px rgba(239,68,68,0.5)';
                
                html5QrCode.pause();
                setTimeout(() => {
                    isProcessing = false;
                    document.getElementById('error-text').style.display = 'none';
                    document.querySelector('.scan-area').style.boxShadow = 'inset 0 0 0 1px rgba(255,255,255,0.1)';
                    html5QrCode.resume();
                }, 2000);
            }
        },
        (errorMessage) => {
            // parse error, ignore
        }
    ).catch((err) => {
        alert("ไม่สามารถเปิดกล้องได้ กรุณาอนุญาตให้ใช้งานกล้อง");
        console.error(err);
    });
});
</script>
@endsection
