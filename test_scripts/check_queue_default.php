<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "queue.default = " . config('queue.default') . PHP_EOL;
echo "env(QUEUE_CONNECTION) = " . env('QUEUE_CONNECTION') . PHP_EOL;
