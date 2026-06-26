@extends('layouts.app')

@section('title', 'Staff Scanner - ' . $activity->title)

@section('content')
<div class="max-w-2xl mx-auto py-8 px-4">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $activity->title }}</h1>
                <p class="text-sm text-gray-500">โหมดสแกน QR นักศึกษา (Staff Only)</p>
            </div>
            <a href="{{ route('admin.activities.show', $activity->id) }}" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                ปิดหน้าสแกน
            </a>
        </div>

        <!-- Scanner UI -->
        <div id="reader" class="rounded-xl overflow-hidden bg-black aspect-video mb-6"></div>

        <!-- Status & Results -->
        <div id="status-container" class="space-y-4">
            <div id="idle-msg" class="text-center py-8 text-gray-400 italic">
                วาง QR Code ของนักศึกษาให้ตรงกลางกรอบเพื่อสแกน
            </div>
            
            <div id="last-result" class="hidden animate-in fade-in zoom-in duration-300">
                <div id="result-card" class="p-4 rounded-xl flex items-center space-x-4 border-2">
                    <div id="result-icon" class="w-12 h-12 rounded-full flex items-center justify-center text-2xl"></div>
                    <div class="flex-1">
                        <div id="result-name" class="font-bold text-lg"></div>
                        <div id="result-id" class="text-sm font-mono opacity-80"></div>
                        <div id="result-msg" class="text-xs mt-1"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Scans List -->
        <div class="mt-8">
            <h3 class="font-bold text-gray-700 mb-3 text-sm uppercase tracking-wider">สแกนล่าสุด</h3>
            <div id="recent-scans" class="divide-y divide-gray-100 bg-gray-50 rounded-xl overflow-hidden border border-gray-100">
                <div class="p-4 text-center text-gray-400 text-sm py-8" id="no-scans-msg">ยังไม่มีการสแกนในเซสชันนี้</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
    let html5QrCode;
    let isScanning = true;
    const recentScans = [];

    async function onScanSuccess(decodedText, decodedResult) {
        if (!isScanning) return;
        
        // Pause scanning while processing
        isScanning = false;
        html5QrCode.pause();
        
        try {
            const response = await fetch('{{ route("admin.activities.scan-student", $activity->id) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ token: decodedText })
            });

            const data = await response.json();
            showResult(data);
            
            if (data.success) {
                addRecentScan(data.student);
                playSuccessSound();
            } else {
                playErrorSound();
            }

        } catch (error) {
            showResult({ success: false, message: 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์' });
        }

        // Resume scanning after 2 seconds
        setTimeout(() => {
            isScanning = true;
            html5QrCode.resume();
            hideResult();
        }, 2000);
    }

    function showResult(data) {
        const container = document.getElementById('last-result');
        const card = document.getElementById('result-card');
        const icon = document.getElementById('result-icon');
        const name = document.getElementById('result-name');
        const sid = document.getElementById('result-id');
        const msg = document.getElementById('result-msg');
        
        document.getElementById('idle-msg').classList.add('hidden');
        container.classList.remove('hidden');

        if (data.success) {
            card.className = 'p-4 rounded-xl flex items-center space-x-4 border-2 border-green-500 bg-green-50 text-green-800';
            icon.innerHTML = '✅';
            name.innerText = data.student.name;
            sid.innerText = data.student.student_id;
            msg.innerText = data.message;
        } else {
            card.className = 'p-4 rounded-xl flex items-center space-x-4 border-2 border-red-500 bg-red-50 text-red-800';
            icon.innerHTML = '❌';
            name.innerText = 'ล้มเหลว';
            sid.innerText = '-';
            msg.innerText = data.message;
        }
    }

    function hideResult() {
        // We don't hide immediately to let the staff see who it was
    }

    function addRecentScan(student) {
        document.getElementById('no-scans-msg').classList.add('hidden');
        const container = document.getElementById('recent-scans');
        
        const item = document.createElement('div');
        item.className = 'p-3 flex items-center justify-between text-sm animate-in slide-in-from-top duration-300';
        item.innerHTML = `
            <div>
                <div class="font-bold text-gray-800">${student.name}</div>
                <div class="text-xs text-gray-500 font-mono">${student.student_id}</div>
            </div>
            <div class="text-xs text-green-600 font-medium bg-green-100 px-2 py-1 rounded">สำเร็จ</div>
        `;
        
        if (container.firstChild) {
            container.insertBefore(item, container.firstChild);
        } else {
            container.appendChild(item);
        }
        
        // Keep only last 5
        if (container.children.length > 5) {
            container.removeChild(container.lastChild);
        }
    }

    function playSuccessSound() {
        // In a real app, play a beep
    }

    function playErrorSound() {
        // In a real app, play a buzz
    }

    function initScanner() {
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        html5QrCode = new Html5Qrcode("reader");
        html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
    }

    window.onload = initScanner;
</script>
@endpush
@endsection
