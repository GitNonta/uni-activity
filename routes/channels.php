<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Log::info("Loading routes/channels.php");

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Channel แจ้งเตือนทั่วไป (Notification)
Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

// Channel แชทส่วนตัวของนักศึกษา
Broadcast::channel('chat.student.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});

// Channel แจ้งเตือนแอดมินเวลามีข้อความใหม่ (สำหรับหน้า Inbox List)
Broadcast::channel('admin.inbox', function ($user) {
    return $user->isStaffOrAdmin();
});

// Presence Channel สำหรับแสดงสถานะออนไลน์
Broadcast::channel('online', function ($user) {
    if (!$user) return false;
    return [
        'id'   => (string) $user->id,
        'name' => $user->full_name ?? $user->name ?? 'User',
    ];
});

// Channel เฉพาะห้องสนทนา
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    $room = \App\Models\Room::find($roomId);
    if (!$room) {
        \Illuminate\Support\Facades\Log::info("Broadcast Auth Failed: Room not found", ['user_id' => $user->id, 'room_id' => $roomId]);
        return false;
    }

    if ($room->type === 'direct') {
        $isParticipant = \Illuminate\Support\Facades\DB::table('room_user')
            ->where('room_id', $roomId)
            ->where('user_id', $user->id)
            ->exists();
        return $isParticipant || $user->isStaffOrAdmin();
    }

    $hasAccess = $room->users()->where('users.id', $user->id)->exists() || $user->isStaffOrAdmin();
    \Illuminate\Support\Facades\Log::info("Broadcast Auth", ['user_id' => $user->id, 'room_id' => $roomId, 'has_access' => $hasAccess]);
    return $hasAccess;
});
