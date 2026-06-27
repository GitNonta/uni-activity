<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Error Pages</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">🎨 Error Pages Testing</h1>
            <p class="text-gray-600 mb-8">Click any button below to test the error pages</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- 404 Error -->
                <a href="{{ route('test.404') }}" class="block p-6 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl text-white hover:shadow-lg transition-all transform hover:-translate-y-1">
                    <div class="text-3xl font-bold mb-2">404</div>
                    <div class="text-sm opacity-90">Page Not Found</div>
                    <div class="text-xs opacity-75 mt-1">ไม่พบหน้าที่ต้องการ</div>
                </a>

                <!-- 403 Error -->
                <a href="{{ route('test.403') }}" class="block p-6 bg-gradient-to-br from-pink-500 to-red-500 rounded-xl text-white hover:shadow-lg transition-all transform hover:-translate-y-1">
                    <div class="text-3xl font-bold mb-2">403</div>
                    <div class="text-sm opacity-90">Forbidden</div>
                    <div class="text-xs opacity-75 mt-1">ไม่มีสิทธิ์เข้าถึง</div>
                </a>

                <!-- 419 Error -->
                <a href="{{ route('test.419') }}" class="block p-6 bg-gradient-to-br from-orange-400 to-red-400 rounded-xl text-white hover:shadow-lg transition-all transform hover:-translate-y-1">
                    <div class="text-3xl font-bold mb-2">419</div>
                    <div class="text-sm opacity-90">Page Expired</div>
                    <div class="text-xs opacity-75 mt-1">หน้าเว็บหมดอายุ</div>
                </a>

                <!-- 500 Error -->
                <a href="{{ route('test.500') }}" class="block p-6 bg-gradient-to-br from-purple-600 to-indigo-700 rounded-xl text-white hover:shadow-lg transition-all transform hover:-translate-y-1">
                    <div class="text-3xl font-bold mb-2">500</div>
                    <div class="text-sm opacity-90">Server Error</div>
                    <div class="text-xs opacity-75 mt-1">เกิดข้อผิดพลาดของเซิร์ฟเวอร์</div>
                </a>

                <!-- 503 Error -->
                <a href="{{ route('test.503') }}" class="block p-6 bg-gradient-to-br from-pink-400 to-red-500 rounded-xl text-white hover:shadow-lg transition-all transform hover:-translate-y-1">
                    <div class="text-3xl font-bold mb-2">503</div>
                    <div class="text-sm opacity-90">Service Unavailable</div>
                    <div class="text-xs opacity-75 mt-1">ระบบบำรุงรักษา</div>
                </a>

                <!-- Database Error -->
                <a href="{{ route('test.database') }}" class="block p-6 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl text-white hover:shadow-lg transition-all transform hover:-translate-y-1">
                    <div class="text-3xl font-bold mb-2">DB</div>
                    <div class="text-sm opacity-90">Database Error</div>
                    <div class="text-xs opacity-75 mt-1">ข้อผิดพลาดฐานข้อมูล</div>
                </a>
            </div>

            <div class="mt-8 p-6 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                <h3 class="font-semibold text-yellow-800 mb-2 flex items-center gap-1">
                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Development Only
                </h3>
                <p class="text-yellow-700 text-sm">
                    These test routes should be removed or disabled in production!<br>
                    Delete or comment out <code class="bg-yellow-100 px-2 py-1 rounded">routes/test-errors.php</code> before deploying.
                </p>
            </div>

            <div class="mt-6 text-center">
                <a href="/" class="text-purple-600 hover:text-purple-700 font-medium">
                    ← กลับหน้าแรก
                </a>
            </div>
        </div>

        <!-- Info Card -->
        <div class="mt-6 bg-white rounded-2xl shadow-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">📋 Error Pages Created</h2>
            <div class="space-y-2 text-gray-600">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <code class="text-sm">resources/views/errors/404.blade.php</code>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <code class="text-sm">resources/views/errors/403.blade.php</code>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <code class="text-sm">resources/views/errors/419.blade.php</code>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <code class="text-sm">resources/views/errors/500.blade.php</code>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <code class="text-sm">resources/views/errors/503.blade.php</code>
                </div>
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <code class="text-sm">resources/views/errors/database.blade.php</code>
                </div>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <p class="text-blue-800 text-sm">
                    💡 <strong>Tip:</strong> See <code>ERROR_PAGES_GUIDE.md</code> for customization options and details.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
