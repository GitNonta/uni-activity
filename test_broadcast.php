<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

use App\Models\Room;
use App\Models\User;
use App\Repositories\ChatRepository;

\ = Room::latest()->first();
\ = User::where('role', 'admin')->first();

if (\ && \) {
    \ = app(ChatRepository::class);
    \->sendMessage(\, \, '🔧 System Verification: Real-time broadcast test at ' . date('H:i:s'));
    echo 'SUCCESS: Message dispatched to Room ' . \->id . PHP_EOL;
} else {
    echo 'ERROR: No active room or admin found.' . PHP_EOL;
}
