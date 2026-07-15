<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\ = 2; // Assuming admin is ID 2
\ = \Illuminate\Support\Facades\DB::table('messages')
    ->join('rooms', 'messages.room_id', '=', 'rooms.id')
    ->leftJoin('room_user', function(\) use (\) {
        \->on('rooms.id', '=', 'room_user.room_id')
             ->where('room_user.user_id', '=', \);
    })
    ->where('messages.user_id', '!=', \)
    ->where(function(\) {
        \->whereRaw('messages.created_at > room_user.last_read_at')
          ->orWhereNull('room_user.last_read_at');
    })
    ->count();
echo 'Unread: ' . \;
