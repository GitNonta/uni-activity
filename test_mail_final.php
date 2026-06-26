<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

echo "--- Testing Email Sending ---\n";

try {
    Mail::raw('ยินดีด้วย! ระบบส่งอีเมลผ่าน Gmail ของคุณทำงานได้ถูกต้องแล้วครับ', function ($message) {
        $message->to('nontawat2546.2546@gmail.com')
                ->subject('✅ ทดสอบระบบส่งอีเมลสำเร็จ');
    });
    echo "✅ SUCCESS: Email has been sent to nontawat2546.2546@gmail.com\n";
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "--- End Test ---\n";
