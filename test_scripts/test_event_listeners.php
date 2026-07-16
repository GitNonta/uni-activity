<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\AnnouncementPublished;
use Illuminate\Support\Facades\Event;

$listeners = Event::getListeners(AnnouncementPublished::class);
echo "Number of listeners for AnnouncementPublished: " . count($listeners) . PHP_EOL;

foreach ($listeners as $index => $listener) {
    echo "Listener #{$index}: ";
    if (is_callable($listener)) {
        echo "Closure/Callable" . PHP_EOL;
    } else if (is_string($listener)) {
        echo $listener . PHP_EOL;
    } else {
        echo get_class($listener) . PHP_EOL;
    }
}
