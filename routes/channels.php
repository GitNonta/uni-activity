<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel ส่วนตัวของนักศึกษา (สำหรับรับข้อความแชท)
Broadcast::channel('chat.student.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->isAdmin() || $user->isStaff();
});

// Channel เฉพาะห้องสนทนา
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    $room = \App\Models\Room::find($roomId);
    if (!$room) return false;
    return $room->users->contains($user->id) || $user->isAdmin() || $user->isStaff();
});
