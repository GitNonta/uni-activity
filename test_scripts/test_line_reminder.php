<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\LineService;
use App\Models\Activity;
use App\Models\User;

$user = User::find(3);
if (!$user) {
    echo "ERROR: User with ID 3 not found." . PHP_EOL;
    exit(1);
}

$activity = Activity::latest()->first();
if (!$activity) {
    echo "ERROR: No activities found in the database. Please create one first." . PHP_EOL;
    exit(1);
}

$lineService = app(LineService::class);

echo "Testing LINE Reminder using Activity: ID={$activity->id}, Title='{$activity->title}'" . PHP_EOL;
echo "Recipient Student: {$user->full_name} (LINE User ID: {$user->line_user_id})" . PHP_EOL;

echo "Building reminder Flex Message..." . PHP_EOL;
$message = $lineService->buildReminderMessage($activity, $user->full_name);

// Print the Redirect Link from the footer action to verify correctness
$uri = $message['contents']['footer']['contents'][0]['action']['uri'] ?? 'N/A';
echo "Generated Redirect Link in Reminder Message: {$uri}" . PHP_EOL;

echo "Sending push message to LINE..." . PHP_EOL;
$result = $lineService->pushMessage($user->line_user_id, [$message]);

if ($result) {
    echo "SUCCESS: Test reminder notification sent successfully to LINE!" . PHP_EOL;
} else {
    echo "FAILED: Failed to send reminder notification. Check laravel logs." . PHP_EOL;
}
