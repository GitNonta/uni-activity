<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel ส่วนตัวของนักศึกษา (สำหรับรับข้อความแชท)
Broadcast::channel('chat.student.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->isAdmin() || $user->isStaff();
});

// Channel สถานะออนไลน์
Broadcast::channel('user.online.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->isAdmin() || $user->isStaff();
});

// Channel เฉพาะห้องสนทนา
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    $room = \App\Models\Room::find($roomId);
    if (!$room) return false;
    return $room->users()->where('users.id', $user->id)->exists() || $user->isAdmin() || $user->isStaff();
});

// Channel สำหรับหน้า Walk-in
Broadcast::channel('activity.{token}.checkins', function ($user, $token) {
    return $user->isAdmin() || $user->isStaff();
});

// Presence Channel สำหรับสถานะออนไลน์ทั่วระบบ
Broadcast::channel('online', function ($user) {
    return [
        'id' => $user->id,
        'name' => $user->full_name,
        'role' => $user->role,
    ];
});
