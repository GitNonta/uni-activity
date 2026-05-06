<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลการแจ้งเตือน
 * เก็บข้อมูลการแจ้งเตือนที่ส่งถึงผู้ใช้ เช่น อนุมัติลงทะเบียน, เตือนกิจกรรม
 */
class Notification extends Model
{
    /** ชื่อตารางในฐานข้อมูล */
    protected $table = 'notifications_custom';

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    /** ความสัมพันธ์: การแจ้งเตือนเป็นของผู้ใช้ */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
