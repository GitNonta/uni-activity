<?php

namespace App\Services;

use App\Events\ChatMessageEvent;
use Illuminate\Support\Facades\Log;

class SocketService
{
    /**
     * สำหรับความเข้ากันได้กับระบบเดิม
     */
    public static function roomToken(string $room): string
    {
        return hash_hmac('sha256', $room, config('app.key'));
    }

    /**
     * ส่งข้อมูลผ่าน Laravel Broadcasting (Reverb)
     */
    public static function emit(string $room, string $event, array $data): void
    {
        try {
            broadcast(new ChatMessageEvent($room, $event, $data));
        } catch (\Exception $e) {
            Log::warning('SocketService::emit failed — ' . $e->getMessage());
        }
    }
}
