<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Room;
use App\Models\User;
use App\Repositories\ChatRepository;

$room = Room::latest()->first();
$admin = User::where('role', 'admin')->first();

if ($room && $admin) {
    $repo = app(ChatRepository::class);
    $repo->sendMessage($room, $admin, '🔧 System Verification: Real-time broadcast test at ' . date('H:i:s'));
    echo "SUCCESS: Room ID " . $room->id;
} else {
    echo "ERROR: No active room or admin found.";
}
