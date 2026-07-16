<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\LineService;
use App\Models\Activity;

$lineUserId = 'Ua5791175d2e749fa8e48479b09a1e384';
$lineService = app(LineService::class);

$activity = Activity::latest()->first();
if (!$activity) {
    echo "ERROR: No activities found in the database. Please create an activity first." . PHP_EOL;
    exit(1);
}

echo "Testing system using activity: ID={$activity->id}, Title='{$activity->title}'" . PHP_EOL;
echo "Building activity Flex Message..." . PHP_EOL;
$message = $lineService->buildActivityMessage($activity);

// Print the URI that was generated inside the message to verify redirect URL format
$uri = $message['contents']['footer']['contents'][0]['action']['uri'] ?? 'N/A';
echo "Generated Redirect Link in Flex Message: {$uri}" . PHP_EOL;

echo "Sending push message to LINE User ID: {$lineUserId}..." . PHP_EOL;
$result = $lineService->pushMessage($lineUserId, [$message]);

if ($result) {
    echo "SUCCESS: Test notification sent successfully to LINE!" . PHP_EOL;
} else {
    echo "FAILED: Failed to send notification. Check laravel logs for details." . PHP_EOL;
}
