<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลการลงทะเบียนกิจกรรม
 * เก็บข้อมูลการลงทะเบียนของนักศึกษา สถานะ: pending, approved, rejected, cancelled, completed
 */
class Registration extends Model
{
    use HasFactory;

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    protected $fillable = [
        'user_id',
        'activity_id',
        'status',
        'registered_at',
        'cancelled_at',
        'note',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** ความสัมพันธ์: การลงทะเบียนเป็นของผู้ใช้ */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** ความสัมพันธ์: การลงทะเบียนสังกัดกิจกรรม */
    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    /** เปลี่ยนสถานะเป็น 'completed' เมื่อมีการเข้าร่วมกิจกรรมแล้ว */
    public function markAsCompleted()
    {
        if ($this->status === 'approved') {
            $this->update(['status' => 'completed']);
        }
    }
}
