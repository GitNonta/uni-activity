<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Announcement;
use App\Events\AnnouncementPublished;
use App\Listeners\SendLineAnnouncementNotification;

$announcement = Announcement::find(8);
if (!$announcement) {
    echo "ERROR: Announcement ID 8 not found in database." . PHP_EOL;
    exit(1);
}

echo "Testing Announcement Notification synchronously for: ID={$announcement->id}, Title='{$announcement->title}'" . PHP_EOL;

// Run the listener directly (synchronously) to capture any errors
$listener = app(SendLineAnnouncementNotification::class);
echo "Invoking SendLineAnnouncementNotification handle method..." . PHP_EOL;
try {
    $listener->handle(new AnnouncementPublished($announcement));
    echo "SUCCESS: handle method completed without exceptions." . PHP_EOL;
} catch (\Throwable $e) {
    echo "FAILED: handle method threw an exception: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
