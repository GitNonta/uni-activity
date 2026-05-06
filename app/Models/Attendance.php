<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลการเข้าร่วมกิจกรรม (Attendance)
 * บันทึกการเช็คอิน/บันทึกกิจกรรมของนักศึกษา วิธี: qr_scan, manual
 */
class Attendance extends Model
{
    use HasFactory;

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    protected $fillable = [
        'user_id',
        'activity_id',
        'checked_in_at',
        'method',
        'status',
        'verified_by',
        'is_verified',
        'ip_address',
        'checkin_latitude',
        'checkin_longitude',
        'distance_meters',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'is_verified' => 'boolean',
            'checkin_latitude' => 'decimal:7',
            'checkin_longitude' => 'decimal:7',
            'distance_meters' => 'decimal:2',
        ];
    }

    /** ความสัมพันธ์: การเข้าร่วมเป็นของผู้ใช้ */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** ความสัมพันธ์: การเข้าร่วมสังกัดกิจกรรม */
    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    /** ความสัมพันธ์: ผู้ตรวจสอบการเข้าร่วม (เจ้าหน้าที่) */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
