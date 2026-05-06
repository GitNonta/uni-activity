<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel สำหรับ Inquiry ของแต่ละงาน (Admin เห็นทุก inquiry)
Broadcast::channel('job-inquiry.{jobId}', function ($user, $jobId) {
    return auth()->check();
});

// Channel ส่วนตัวของนักศึกษาแต่ละคน (รับเฉพาะ inquiry ของตัวเอง)
Broadcast::channel('job-inquiry.{jobId}.{userId}', function ($user, $jobId, $userId) {
    return (int) $user->id === (int) $userId;
});
