<?php
define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Request;

echo "--- Client IP Info ---\n";
echo "Request::ip(): " . Request::ip() . "\n";
echo "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'N/A') . "\n";
echo "HTTP_X_FORWARDED_FOR: " . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'N/A') . "\n";
echo "HTTP_X_REAL_IP: " . ($_SERVER['HTTP_X_REAL_IP'] ?? 'N/A') . "\n";
echo "--- All Headers ---\n";
print_r(getallheaders());
