<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * ข้อความแชทใน MongoDB
 * 1 job_id มีหลาย messages (thread-based)
 */
class ChatMessage extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'chat_messages';

    protected $fillable = [
        'job_id',
        'sender_id',
        'sender_role',    // 'student' | 'admin'
        'sender_name',
        'sender_photo',   // URL of sender's profile photo (nullable)
        'student_id',     // which student thread this belongs to
        'message',
        'attachments',    // array of { filename, original_name, mime_type, size, url }
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'job_id'      => 'integer',
            'sender_id'   => 'integer',
            'attachments' => 'array',
            'read_at'     => 'datetime',
        ];
    }

    /** ผู้ส่งข้อความ */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** ประกาศงานที่แชทนี้เชื่อมอยู่ */
    public function job()
    {
        return $this->belongsTo(JobListing::class, 'job_id');
    }

    /** ตรวจสอบว่ามีไฟล์แนบหรือไม่ */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /** ตรวจสอบว่าอ่านแล้วหรือไม่ */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
