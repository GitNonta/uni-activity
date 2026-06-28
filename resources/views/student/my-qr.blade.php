@extends('layouts.app')

@section('title', 'QR Code ส่วนตัว')

@section('content')
<div style="max-width: 480px; margin: 2rem auto; padding: 0 1rem;">
    <div style="background: #ffffff; border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); overflow: hidden; text-align: center; padding: 2.5rem 1.5rem; border: 1px solid #f1f5f9;">
        
        <h1 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
            <svg width="24" height="24" fill="none" stroke="#4f46e5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
            QR Code ของฉัน
        </h1>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 2rem;">แสดง QR Code นี้ให้เจ้าหน้าที่สแกนเพื่อเช็คอินเข้ากิจกรรม</p>

        <!-- QR Code Container -->
        <div style="position: relative; display: inline-block; padding: 1.25rem; background: #f8fafc; border-radius: 16px; border: 2px dashed #cbd5e1; margin-bottom: 1.5rem;">
            <div id="qrcode" style="margin: 0 auto; display:flex; justify-content:center;"></div>
            
            <!-- Refresh Overlay (Hidden by default) -->
            <div id="refresh-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.85); display: none; align-items: center; justify-content: center; border-radius: 14px; backdrop-filter: blur(2px);">
                <button onclick="refreshQr()" style="background: #4f46e5; color: #fff; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.25); display: flex; align-items: center; gap: 0.4rem; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    รีเฟรช QR Code
                </button>
            </div>
        </div>

        <!-- Timer Bar -->
        <div style="width: 100%; background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden; margin-bottom: 0.5rem;">
            <div id="timer-bar" style="background: #4f46e5; height: 100%; width: 100%; transition: width 1s linear, background-color 0.3s;"></div>
        </div>
        <p style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 2.5rem; font-style: italic;">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline; vertical-align:text-bottom;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7z"/></svg>
            QR Code จะรีเฟรชทุกๆ 30 วินาทีเพื่อความปลอดภัย
        </p>

        <!-- User Info -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; padding: 1rem; background: #f8fafc; border-radius: 12px; text-align: left; border: 1px solid #f1f5f9;">
            @if($user->profile_photo)
                <img src="{{ asset('storage/' . $user->profile_photo) }}" alt="profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
            @else
                <div style="width: 50px; height: 50px; background: #e0e7ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4338ca; font-weight: 700; font-size: 1.2rem; border: 2px solid #c7d2fe;">
                    {{ mb_substr($user->full_name, 0, 1) }}
                </div>
            @endif
            <div>
                <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">{{ $user->full_name }}</div>
                <div style="font-size: 0.85rem; color: #4f46e5; font-family: monospace; font-weight: 600;">{{ $user->student_id }}</div>
            </div>
        </div>

        <div style="margin-top: 2rem;">
            <a href="{{ route('student.profile') }}" style="display: inline-flex; align-items: center; gap: 0.4rem; color: #64748b; font-size: 0.85rem; font-weight: 500; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#4f46e5'" onmouseout="this.style.color='#64748b'">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                กลับหน้าโปรไฟล์
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    (function() {
        let qr;
        let timer;
        let timeLeft = 0;

        window.refreshQr = async function() {
            try {
                const response = await fetch('{{ route("student.qr.token") }}');
                const data = await response.json();
                
                if (!qr) {
                    qr = new QRCode(document.getElementById("qrcode"), {
                        width: 220,
                        height: 220,
                        colorDark : "#1e293b",
                        colorLight : "#f8fafc",
                        correctLevel : QRCode.CorrectLevel.H
                    });
                }
                
                qr.clear();
                qr.makeCode(data.token);
                
                timeLeft = data.expires_in;
                updateTimer();
                
                document.getElementById('refresh-overlay').style.display = 'none';
                
                if (timer) clearInterval(timer);
                timer = setInterval(() => {
                    timeLeft--;
                    updateTimer();
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        document.getElementById('refresh-overlay').style.display = 'flex';
                    }
                }, 1000);
                
            } catch (error) {
                console.error('Failed to fetch QR token', error);
            }
        };

        function updateTimer() {
            const percent = (timeLeft / 30) * 100;
            const timerBar = document.getElementById('timer-bar');
            if (!timerBar) return;

            timerBar.style.width = percent + '%';
            if (timeLeft < 5) {
                timerBar.style.backgroundColor = '#ef4444'; // Red for warning
            } else {
                timerBar.style.backgroundColor = '#4f46e5'; // Indigo normal
            }
        }

        // Initialize on load
        window.addEventListener('load', window.refreshQr);
    })();
</script>
@endpush
@endsection
