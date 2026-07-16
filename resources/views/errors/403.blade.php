<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - ไม่มีสิทธิ์เข้าถึงระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', 'Outfit', sans-serif;
            background: #f8fafc;
            margin: 0;
        }
        .glow-shield {
            filter: drop-shadow(0 10px 15px rgba(79, 70, 229, 0.15));
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <!-- Shield SVG Illustration -->
        <div class="mb-8 flex justify-center">
            <div class="glow-shield bg-indigo-50 text-indigo-600 rounded-full p-6 inline-flex items-center justify-center border border-indigo-100/50">
                <svg class="w-20 h-20" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"></path>
                </svg>
            </div>
        </div>

        <!-- Error Code -->
        <div class="text-xs uppercase tracking-widest font-bold text-indigo-600 mb-2 font-mono">
            Error Code: 403 Forbidden
        </div>
        
        <!-- Title -->
        <h1 class="text-2xl font-bold text-slate-800 mb-3">ไม่มีสิทธิ์เข้าถึงส่วนนี้</h1>
        
        <!-- Description -->
        <p class="text-slate-600 text-sm mb-8 leading-relaxed max-w-sm mx-auto">
            ขออภัย บัญชีผู้ใช้งานของคุณไม่ได้รับอนุญาตให้เข้าถึงเนื้อหาในส่วนนี้ เนื่องจากข้อจำกัดด้านสิทธิ์การใช้งาน (Role Restrictions) กรุณาตรวจสอบสิทธิ์การเชื่อมต่อหรือติดต่อผู้ดูแลระบบเพื่อแจ้งปัญหา
        </p>

        <!-- Actions Buttons -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center mb-8">
            <a href="/" class="inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-lg transition-colors shadow-sm gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"></path>
                </svg>
                กลับสู่หน้าหลัก
            </a>
            <button onclick="history.back()" class="inline-flex items-center justify-center px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-700 font-medium text-sm rounded-lg border border-slate-200 transition-colors gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"></path>
                </svg>
                ย้อนกลับ
            </button>
        </div>

        <!-- Footer divider -->
        <hr class="border-slate-200 my-6">

        <!-- Additional security details -->
        <div class="text-[11px] text-slate-400 font-medium flex items-center justify-center gap-4">
            <span>สิทธิ์ปัจจุบัน: {{ auth()->check() ? (auth()->user()->role === 'admin' ? 'Administrator' : (auth()->user()->role === 'staff' ? 'Staff' : 'Student')) : 'Guest' }}</span>
            <span class="w-1.5 h-1.5 bg-slate-300 rounded-full"></span>
            <span>ที่อยู่ IP: {{ request()->ip() }}</span>
        </div>
    </div>
</body>
</html>
