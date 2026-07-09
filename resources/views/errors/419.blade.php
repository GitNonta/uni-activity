<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - หมดเวลาการเชื่อมต่อ (Page Expired)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full">
        <div class="bg-white rounded-2xl shadow-xl p-10 text-center border border-gray-100">
            <div class="mb-6 flex justify-center">
                <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-600">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>

            <h1 class="text-5xl font-bold text-gray-900 mb-2 tracking-tight">419</h1>
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">หมดเวลาการเชื่อมต่อ (Session Expired)</h2>
            
            <p class="text-gray-600 text-base mb-8 leading-relaxed">
                เซสชันของคุณหมดอายุเนื่องจากไม่มีการใช้งานเป็นระยะเวลาหนึ่ง<br>
                เพื่อความปลอดภัยของข้อมูล กรุณารีเฟรชหน้าเว็บและเข้าสู่ระบบใหม่อีกครั้ง
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <button onclick="location.reload()" class="w-full sm:w-auto bg-indigo-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-100 transition-all duration-200">
                    รีเฟรชหน้าเว็บ
                </button>
                <a href="{{ url('/') }}" class="w-full sm:w-auto bg-white text-gray-700 px-8 py-3 rounded-lg font-medium border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100 transition-all duration-200 text-center">
                    กลับสู่หน้าหลัก
                </a>
            </div>
            
            <div class="mt-8 pt-6 border-t border-gray-100">
                <p class="text-sm text-gray-400">
                    หากคุณพบปัญหานี้บ่อยครั้ง กรุณาตรวจสอบการตั้งค่าคุกกี้ในเบราว์เซอร์ของคุณ
                </p>
            </div>
        </div>
    </div>
</body>
</html>
