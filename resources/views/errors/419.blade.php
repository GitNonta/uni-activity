<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - หน้าเว็บหมดอายุ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <div class="bg-white rounded-3xl shadow-2xl p-12 text-center">
            <div class="mb-8">
                <svg class="w-32 h-32 mx-auto" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="40" fill="#ffecd2"/>
                    <path d="M35 45 Q50 35 65 45" stroke="#fcb69f" stroke-width="3" fill="none"/>
                    <circle cx="40" cy="40" r="3" fill="#fcb69f"/>
                    <circle cx="60" cy="40" r="3" fill="#fcb69f"/>
                    <path d="M30 60 L70 60" stroke="#fcb69f" stroke-width="3" stroke-linecap="round"/>
                </svg>
            </div>

            <h1 class="text-6xl font-bold text-gray-800 mb-4">419</h1>
            <h2 class="text-3xl font-bold text-gray-700 mb-6">หน้าเว็บหมดอายุ</h2>
            
            <p class="text-gray-600 text-lg mb-8">
                เซสชันของคุณหมดอายุแล้ว กรุณารีเฟรชหน้าเว็บและลองใหม่อีกครั้ง
            </p>

            <div class="bg-orange-50 rounded-xl p-6 mb-8">
                <p class="text-orange-800 font-medium">
                    💡 เกิดจากการเปิดหน้าเว็บทิ้งไว้นานเกินไป
                </p>
            </div>

            <button onclick="location.reload()" class="bg-gradient-to-r from-orange-400 to-red-400 text-white px-8 py-3 rounded-xl font-semibold hover:from-orange-500 hover:to-red-500 transition-all duration-200 shadow-lg">
                รีเฟรชหน้าเว็บ
            </button>
        </div>
    </div>
</body>
</html>
