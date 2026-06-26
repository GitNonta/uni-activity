@extends('layouts.app')

@section('title', 'QR Code ส่วนตัว')

@section('content')
<div class="max-w-md mx-auto py-8 px-4">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden text-center p-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">QR Code ของฉัน</h1>
        <p class="text-gray-500 mb-6">แสดง QR Code นี้ให้เจ้าหน้าที่สแกนเพื่อเข้ากิจกรรม</p>

        <!-- QR Code Container -->
        <div class="relative inline-block p-4 bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 mb-6">
            <div id="qrcode" class="mx-auto"></div>
            
            <!-- Refresh Overlay (Hidden by default) -->
            <div id="refresh-overlay" class="absolute inset-0 bg-white/80 flex items-center justify-center hidden rounded-xl">
                <button onclick="refreshQr()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-medium shadow-lg hover:bg-indigo-700 transition">
                    รีเฟรช QR Code
                </button>
            </div>
        </div>

        <!-- Timer Bar -->
        <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden mb-2">
            <div id="timer-bar" class="bg-indigo-500 h-full transition-all duration-1000 ease-linear" style="width: 100%"></div>
        </div>
        <p class="text-xs text-gray-400 mb-8 italic">QR Code นี้จะเปลี่ยนทุกๆ 30 วินาทีเพื่อความปลอดภัย</p>

        <!-- User Info -->
        <div class="flex items-center justify-center space-x-4 p-4 bg-indigo-50 rounded-xl text-left">
            <div class="w-12 h-12 bg-indigo-200 rounded-full flex items-center justify-center text-indigo-700 font-bold text-xl">
                {{ substr($user->full_name, 0, 1) }}
            </div>
            <div>
                <div class="font-bold text-gray-800">{{ $user->full_name }}</div>
                <div class="text-sm text-indigo-600 font-mono">{{ $user->student_id }}</div>
            </div>
        </div>

        <a href="{{ route('student.profile') }}" class="mt-8 inline-block text-gray-500 hover:text-indigo-600 transition text-sm">
            ← กลับหน้าโปรไฟล์
        </a>
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
                        width: 256,
                        height: 256,
                        colorDark : "#1e1b4b",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.H
                    });
                }
                
                qr.clear();
                qr.makeCode(data.token);
                
                timeLeft = data.expires_in;
                updateTimer();
                
                document.getElementById('refresh-overlay').classList.add('hidden');
                
                if (timer) clearInterval(timer);
                timer = setInterval(() => {
                    timeLeft--;
                    updateTimer();
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        document.getElementById('refresh-overlay').classList.remove('hidden');
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
                timerBar.classList.remove('bg-indigo-500');
                timerBar.classList.add('bg-red-500');
            } else {
                timerBar.classList.add('bg-indigo-500');
                timerBar.classList.remove('bg-red-500');
            }
        }

        // Initialize on load
        window.addEventListener('load', window.refreshQr);
    })();
</script>
@endpush
@endsection
