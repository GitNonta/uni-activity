<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - LOST IN SPACE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;900&family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-bg: #020617;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--color-bg);
            margin: 0;
            overflow: hidden;
            color: white;
        }

        .orbitron { font-family: 'Orbitron', sans-serif; font-weight: 900; }

        /* --- Celestial Background --- */
        .stars-container {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .star {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle var(--duration) infinite ease-in-out;
            opacity: 0;
        }

        @keyframes twinkle {
            0%, 100% { opacity: 0.1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.2); }
        }

        /* --- Diagonal Error Comets --- */
        .comet {
            position: absolute;
            width: 2px;
            height: 100px;
            background: linear-gradient(to bottom, rgba(255,255,255,0) 0%, rgba(255,255,255,1) 100%);
            transform: rotate(-135deg);
            z-index: 1;
            pointer-events: none;
            animation: fall var(--fall-duration) var(--fall-delay) linear infinite;
            top: -150px;
        }

        @keyframes fall {
            0% { transform: translate(100vw, -100px) rotate(-135deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translate(-20vw, 120vh) rotate(-135deg); opacity: 0; }
        }

        /* --- Layout --- */
        .parallax-scene {
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            perspective: 1200px;
        }

        .scene-layer {
            transition: transform 0.15s ease-out;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* --- Asset Styling --- */
        .composite-404 {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 5;
        }

        .composite-404 img {
            width: clamp(100px, 20vw, 300px);
            filter: drop-shadow(0 0 20px rgba(99, 102, 241, 0.4));
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin: 0 -0.5rem; /* Closer together */
            cursor: pointer;
        }

        .composite-404 img:hover {
            transform: translateY(-40px) scale(1.05) rotate(5deg);
            filter: drop-shadow(0 0 40px rgba(99, 102, 241, 0.8));
        }

        .header-error {
            height: clamp(40px, 8vw, 80px);
            margin-bottom: 2rem;
            filter: drop-shadow(0 0 10px rgba(236, 72, 153, 0.5));
        }

        /* --- Animations --- */
        .rocket-orbit {
            position: absolute;
            width: 80px;
            height: 80px;
            z-index: 10;
            pointer-events: none;
            offset-path: path('M -400,200 C -400,-150 400,-150 400,200 C 400,550 -400,550 -400,200 Z');
            animation: moveRocket 10s infinite linear;
        }

        @keyframes moveRocket {
            0% { offset-distance: 0%; z-index: 10; transform: scale(1.1); }
            45% { z-index: 10; transform: scale(1); }
            50% { offset-distance: 50%; z-index: 1; transform: scale(0.7); opacity: 0.8; }
            95% { z-index: 1; }
            100% { offset-distance: 100%; z-index: 10; transform: scale(1.1); }
        }

        .ufo-hover {
            position: absolute;
            top: -20%;
            left: -30%;
            width: 150px;
            filter: drop-shadow(0 0 15px #0ff);
            animation: ufoFloat 6s ease-in-out infinite;
        }

        @keyframes ufoFloat {
            0%, 100% { transform: translateY(0) rotate(-10deg); }
            50% { transform: translateY(-30px) rotate(10deg); }
        }

        .astronaut-float {
            position: absolute;
            right: -25%;
            top: 20%;
            width: 120px;
            animation: astronautSlow 8s ease-in-out infinite;
        }

        @keyframes astronautSlow {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(-10px, 20px) rotate(5deg); }
            66% { transform: translate(10px, -15px) rotate(-5deg); }
        }

        .asteroid {
            position: absolute;
            opacity: 0.6;
            filter: blur(1px);
            pointer-events: none;
        }

        /* --- Buttons --- */
        .nav-btn {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(99, 102, 241, 0.5);
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px -10px rgba(99, 102, 241, 0.3);
        }

        .primary-btn {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            box-shadow: 0 0 25px rgba(99,102,241,0.3);
        }
    </style>
</head>
<body>
    <div class="stars-container" id="stars-container"></div>
    <div id="comets-container"></div>

    <!-- Background Asteroids -->
    <img src="/images/errors/404/1.png" class="asteroid w-20 top-[10%] left-[5%]" style="animation: float 12s infinite alternative">
    <img src="/images/errors/404/1.png" class="asteroid w-12 bottom-[15%] right-[10%] rotate-45" style="animation: float 15s infinite alternative">

    <main class="parallax-scene" id="parallax-scene">
        <div class="scene-layer" data-speed="0.03">
            
            <img src="/images/errors/404/4.png" alt="ERROR" class="header-error">

            <div class="relative">
                <!-- UFO (7.png) -->
                <img src="/images/errors/404/7.png" alt="UFO" class="ufo-hover">

                <!-- 404 Composite (2.png, 3.png) -->
                <div class="composite-404">
                    <img src="/images/errors/404/2.png" alt="4">
                    <img src="/images/errors/404/3.png" alt="0" class="mx-[-1rem] md:mx-[-2rem]">
                    <img src="/images/errors/404/2.png" alt="4">
                </div>

                <!-- Rocket (6.png) -->
                <img src="/images/errors/404/6.png" alt="Rocket" class="rocket-orbit">

                <!-- Astronaut (5.png) -->
                <img src="/images/errors/404/5.png" alt="Astronaut" class="astronaut-float">
            </div>

            <div class="text-center mt-12 px-8 max-w-2xl">
                <h2 class="orbitron text-2xl md:text-3xl tracking-widest text-indigo-300 mb-4">LOST IN HYPERSPACE</h2>
                <p class="text-slate-400 text-lg mb-10 leading-relaxed">
                    พิกัดเป้าหมายถูกวาร์ปเข้าไปในมิติที่มองไม่เห็น<br>
                    ยานอวกาศของคุณกำลังลอยเคว้งอยู่ในกลุ่มดาว 404
                </p>

                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    <button onclick="history.back()" class="nav-btn px-10 py-4 rounded-full text-white font-semibold flex items-center group">
                        <svg class="w-5 h-5 mr-3 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        ย้อนกลับ
                    </button>
                    <a href="/" class="nav-btn primary-btn px-12 py-4 rounded-full text-white font-bold flex items-center justify-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        กลับสู่แดชบอร์ด
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Stars
        const starsContainer = document.getElementById('stars-container');
        for (let i = 0; i < 180; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            const size = Math.random() * 2 + 0.5;
            star.style.width = star.style.height = `${size}px`;
            star.style.left = `${Math.random() * 100}%`;
            star.style.top = `${Math.random() * 100}%`;
            star.style.setProperty('--duration', `${Math.random() * 3 + 2}s`);
            star.style.animationDelay = `${Math.random() * 3}s`;
            starsContainer.appendChild(star);
        }

        // Comets
        const cometsContainer = document.getElementById('comets-container');
        setInterval(() => {
            const comet = document.createElement('div');
            comet.className = 'comet';
            const duration = Math.random() * 2 + 1.5;
            comet.style.setProperty('--fall-duration', `${duration}s`);
            comet.style.left = `${Math.random() * 100}vw`;
            cometsContainer.appendChild(comet);
            setTimeout(() => comet.remove(), duration * 1000);
        }, 2500);

        // Parallax
        document.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth - 0.5) * 50;
            const y = (e.clientY / window.innerHeight - 0.5) * 50;
            const layer = document.querySelector('.scene-layer');
            layer.style.transform = `translate(${x}px, ${y}px)`;
        });
    </script>
</body>
</html>
