<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - ระบบอยู่ระหว่างการบำรุงรักษา</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin-slow {
            animation: spin-slow 3s linear infinite;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full text-center">
        <div class="bg-white rounded-3xl shadow-2xl p-12">
            <!-- Maintenance Icon -->
            <div class="mb-8">
                <svg class="w-32 h-32 mx-auto spin-slow" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="40" stroke="#f5576c" stroke-width="4" fill="none"/>
                    <path d="M50 20 L50 50 L70 50" stroke="#f093fb" stroke-width="4" stroke-linecap="round"/>
                </svg>
            </div>

            <h1 class="text-6xl font-bold text-gray-800 mb-4">503</h1>
            <h2 class="text-3xl font-bold text-gray-700 mb-6">ระบบอยู่ระหว่างการบำรุงรักษา</h2>
            
            <p class="text-gray-600 text-lg mb-8">
                ขออภัยในความไม่สะดวก เรากำลังปรับปรุงระบบเพื่อประสบการณ์ที่ดีขึ้น<br>
                กรุณากลับมาใหม่ในอีกสักครู่
            </p>

            <div class="bg-pink-50 rounded-xl p-6 mb-8">
                <p class="text-pink-800 font-medium">
                    <svg style="width:16px;height:16px;display:inline;vertical-align:-3px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> ระบบจะกลับมาให้บริการโดยเร็วที่สุด
                </p>
            </div>

            <button onclick="location.reload()" class="bg-gradient-to-r from-pink-500 to-red-500 text-white px-8 py-3 rounded-xl font-semibold hover:from-pink-600 hover:to-red-600 transition-all duration-200 shadow-lg">
                ลองใหม่อีกครั้ง
            </button>
        </div>
    </div>
</body>
</html>
