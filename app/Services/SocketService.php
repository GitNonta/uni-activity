<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocketService
{
    public static function roomToken(string $room): string
    {
        return hash_hmac('sha256', $room, config('socket.secret'));
    }

    public static function emit(string $room, string $event, array $data): void
    {
        try {
            Http::timeout(2)->post(config('socket.server_url') . '/emit', [
                'secret' => config('socket.secret'),
                'room'   => $room,
                'event'  => $event,
                'data'   => $data,
            ]);
        } catch (\Exception $e) {
            Log::warning('SocketService::emit failed — ' . $e->getMessage());
        }
    }
}
