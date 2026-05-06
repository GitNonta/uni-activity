<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - ไม่มีสิทธิ์เข้าถึง</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at center, #2d0a0a 0%, #020617 100%);
            margin: 0;
            overflow: hidden;
        }
        .orbitron { font-family: 'Orbitron', sans-serif; }
        
        .stars-container {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            background: transparent;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle var(--duration) infinite ease-in-out;
            opacity: 0;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.2; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.2); }
        }

        .pulse-animation {
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { filter: drop-shadow(0 0 10px rgba(239, 68, 68, 0.4)); transform: scale(1); }
            50% { filter: drop-shadow(0 0 30px rgba(239, 68, 68, 0.8)); transform: scale(1.02); }
        }

        .nebula {
            position: fixed;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(239, 68, 68, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(239, 68, 68, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }

        .glow-text-red {
            text-shadow: 0 0 20px rgba(239, 68, 68, 0.6);
        }
    </style>
</head>
<body class="min-h-screen items-center justify-center p-4 flex">
    <div class="stars-container" id="stars-container"></div>
    <div class="nebula top-[-100px] left-[-100px]"></div>
    <div class="nebula bottom-[-100px] right-[-100px] bg-red-900/10"></div>

    <div class="max-w-4xl w-full z-10">
        <div class="glass-card rounded-[2.5rem] p-10 md:p-16 text-center">
            <!-- Illustration -->
            <div class="mb-12 relative">
                <div class="absolute inset-x-0 bottom-0 h-4 w-48 mx-auto bg-red-500/20 blur-xl rounded-full"></div>
                <img src="/images/errors/403.png" alt="Restricted Access" class="w-80 md:w-96 mx-auto pulse-animation relative z-10">
            </div>

            <h1 class="orbitron text-8xl md:text-9xl font-bold bg-gradient-to-r from-red-500 via-rose-500 to-red-600 bg-clip-text text-transparent mb-6 glow-text-red tracking-tighter">
                403
            </h1>
            
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-6 tracking-tight">ไม่มีสิทธิ์เข้าถึง</h2>
            
            <p class="text-rose-100/80 text-xl mb-12 max-w-2xl mx-auto leading-relaxed">
                ขออภัย พิกัดนี้ถูกจำกัดสิทธิ์การเข้าถึง<br>
                ระบบรักษาความปลอดภัยข้ามดวงดาวไม่อนุญาตให้ยานของคุณลงจอดที่นี่
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-xl mx-auto">
                <a href="/" class="flex items-center justify-center px-8 py-4 bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white font-semibold rounded-2xl transition-all duration-300 shadow-lg shadow-red-500/25 transform hover:-translate-y-1">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    กลับสู่แดชบอร์ด
                </a>
                <button onclick="history.back()" class="group flex items-center justify-center px-8 py-4 bg-white/5 hover:bg-white/10 border border-white/10 rounded-2xl text-white font-semibold transition-all duration-300">
                    <svg class="w-5 h-5 mr-3 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    ย้อนกลับ
                </button>
            </div>

            <div class="mt-16 flex items-center justify-center space-x-6 text-rose-300/40 text-sm font-medium">
                <span class="orbitron tracking-widest uppercase text-red-500/60">Security: Restricted</span>
                <span class="w-1.5 h-1.5 bg-red-800 rounded-full"></span>
                <span class="orbitron tracking-widest uppercase">Error Code: 403</span>
            </div>
        </div>
    </div>

    <script>
        const container = document.getElementById('stars-container');
        const starCount = 150;

        for (let i = 0; i < starCount; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            
            const size = Math.random() * 2 + 1;
            const x = Math.random() * 100;
            const y = Math.random() * 100;
            const duration = Math.random() * 3 + 2;
            const delay = Math.random() * 5;

            star.style.width = `${size}px`;
            star.style.height = `${size}px`;
            star.style.left = `${x}%`;
            star.style.top = `${y}%`;
            star.style.setProperty('--duration', `${duration}s`);
            star.style.animationDelay = `${delay}s`;

            container.appendChild(star);
        }
    </script>
</body>
</html>
