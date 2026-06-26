<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$otp = DB::table('password_reset_otps')->first();
if ($otp) {
    echo "OTP Exp: " . $otp->expires_at . "\n";
    echo "Current PHP: " . now() . "\n";
    echo "Is Past? " . (\Carbon\Carbon::parse($otp->expires_at)->isPast() ? 'YES' : 'NO') . "\n";
} else {
    echo "No OTP record in DB.\n";
}
