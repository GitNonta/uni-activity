<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - ENGINE FAILURE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #020617;
            --color-amber: #f59e0b;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--color-bg);
            margin: 0;
            overflow: hidden;
            color: white;
        }

        /* --- Full Screen Video Background --- */
        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            overflow: hidden;
        }

        .video-background video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.35;
            filter: grayscale(0.5) brightness(0.6);
        }

        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(2,6,23,0.4) 0%, var(--color-bg) 100%);
            z-index: -1;
        }

        .orbitron { font-family: 'Orbitron', sans-serif; }
        
        .stars-container {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle var(--duration) infinite ease-in-out;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.2); }
        }

        .readable-shadow {
            text-shadow: 0 4px 12px rgba(0,0,0,0.8);
        }

        .btn-action {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--color-amber);
            transform: translateY(-3px);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(245, 158, 11, 0.2);
            border-radius: 10px;
        }
    </style>
</head>
<body class="min-h-screen items-center justify-center p-6 flex flex-col text-center">
    <!-- Video Background -->
    <div class="video-background">
        <video autoplay loop muted playsinline>
            <source src="/images/errors/500.mp4" type="video/mp4">
        </video>
    </div>
    <div class="video-overlay"></div>

    <div class="stars-container" id="stars-container"></div>

    <main class="max-w-3xl w-full z-10">
        <div class="mb-6">
            <div class="inline-block px-4 py-1 rounded-full bg-amber-500/10 border border-amber-500/20 mb-8">
                <span class="orbitron text-amber-500 tracking-[0.4em] text-xs font-bold uppercase">System Critical</span>
            </div>
        </div>

        <h2 class="orbitron text-4xl md:text-6xl font-bold text-white mb-6 tracking-tight readable-shadow uppercase">
            Engine Core Failure
        </h2>
        
        <p class="text-slate-300 text-lg md:text-xl mb-12 max-w-2xl mx-auto leading-relaxed readable-shadow">
            ขออภัย! ระบบวิศวกรรมหลักขัดข้องกระทันหัน<br class="hidden md:block">
            ทีมซ่อมบำรุงกำลังเร่งแก้ไขสถานการณ์ฉุกเฉินนี้
        </p>

        @if(config('app.debug') && isset($exception))
            <div class="mb-12 p-6 bg-black/40 backdrop-blur-md border border-amber-500/10 rounded-2xl text-left max-w-xl mx-auto shadow-2xl">
                <p class="orbitron text-xs font-bold text-amber-500/60 mb-3 uppercase tracking-widest">Diagnostic Log:</p>
                <div class="text-[10px] md:text-xs text-amber-200/50 font-mono overflow-auto max-h-32 leading-relaxed custom-scrollbar">
                    {{ $exception->getMessage() }}
                </div>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
            <button onclick="location.reload()" class="btn-action w-full sm:w-auto px-10 py-4 rounded-full text-white font-bold flex items-center justify-center bg-gradient-to-r from-amber-600/80 to-orange-600/80 hover:from-amber-600 hover:to-orange-600 shadow-lg shadow-amber-900/20">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                ลองใหม่อีกครั้ง
            </button>
            <a href="/" class="btn-action w-full sm:w-auto px-10 py-4 rounded-full text-white font-semibold flex items-center justify-center group">
                <svg class="w-5 h-5 mr-3 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                กลับสู่แดชบอร์ด
            </a>
        </div>

        <div class="mt-20 flex items-center justify-center space-x-6 text-slate-500 text-[10px] md:text-sm font-medium tracking-widest">
            <span class="orbitron uppercase">STATUS: EMERGENCY</span>
            <span class="w-1 h-1 bg-slate-700 rounded-full"></span>
            <span class="orbitron uppercase">ERR: 500</span>
        </div>
    </main>

    <script>
        const container = document.getElementById('stars-container');
        for (let i = 0; i < 120; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            const size = Math.random() * 1.5 + 0.5;
            star.style.width = star.style.height = `${size}px`;
            star.style.left = `${Math.random() * 100}%`;
            star.style.top = `${Math.random() * 100}%`;
            star.style.setProperty('--duration', `${Math.random() * 3 + 2}s`);
            star.style.animationDelay = `${Math.random() * 5}s`;
            container.appendChild(star);
        }
    </script>
</body>
</html>
