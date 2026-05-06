<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลกิจกรรม
 * เก็บข้อมูลกิจกรรมทั้งหมด เช่น ชื่อ วันที่ สถานที่ ช่วงเวลาลงทะเบียน/เช็คอิน
 */
class Activity extends Model
{
    use HasFactory;

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    protected $fillable = [
        'title',
        'description',
        'location',
        'activity_date',
        'start_time',
        'end_time',
        'activity_hours',
        'max_participants',
        'register_open_at',
        'register_close_at',
        'checkin_open_at',
        'checkin_close_at',
        'is_mandatory',
        'category_id',
        'created_by',
        'qr_token',
        'image_path',
        'status',
        'scope',
        'faculty',
        'department',
        'allow_early_checkin',
        'latitude',
        'longitude',
        'checkin_radius',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'register_open_at' => 'datetime',
            'register_close_at' => 'datetime',
            'checkin_open_at' => 'datetime',
            'checkin_close_at' => 'datetime',
            'is_mandatory' => 'boolean',
            'allow_early_checkin' => 'boolean',
            'activity_hours' => 'decimal:1',
            'max_participants' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'checkin_radius' => 'integer',
        ];
    }

    /** ความสัมพันธ์: กิจกรรมมีการลงทะเบียนหลายรายการ */
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /** ความสัมพันธ์: กิจกรรมมีการเข้าร่วม (attendance) หลายรายการ */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /** ความสัมพันธ์: กิจกรรมอยู่ในหมวดหมู่ */
    public function category()
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id');
    }

    /** ความสัมพันธ์: กิจกรรมถูกสร้างโดยเจ้าหน้าที่ */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ความสัมพันธ์: กิจกรรมมีการประเมินหลายรายการ */
    public function feedbacks()
    {
        return $this->hasMany(ActivityFeedback::class);
    }

    /** คำนวณคะแนนเฉลี่ยจากการประเมิน */
    public function getAverageRatingAttribute(): ?float
    {
        $avg = $this->feedbacks()->avg('rating');
        return $avg ? round($avg, 1) : null;
    }

    /** นับจำนวนการประเมินทั้งหมด */
    public function getFeedbackCountAttribute(): int
    {
        return $this->feedbacks()->count();
    }

    /** คำนวณสถานะกิจกรรมแบบ realtime จาก ActivityStatusService */
    public function getComputedStatusAttribute(): string
    {
        return app(\App\Services\ActivityStatusService::class)->computeStatus($this);
    }

    /** คำนวณจำนวนที่ว่างเหลือสำหรับลงทะเบียน */
    public function getRemainingSlots(): int
    {
        $registered = $this->registrations()
            ->whereIn('status', ['pending', 'approved'])
            ->count();
        return max(0, $this->max_participants - $registered);
    }

    /** ตรวจสอบว่ากิจกรรมนี้ตั้งค่าพิกัดสถานที่ไว้หรือไม่ */
    public function hasGeolocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** นับจำนวนผู้ลงทะเบียนทั้งหมด (pending + approved) */
    public function getRegisteredCount(): int
    {
        return $this->registrations()
            ->whereIn('status', ['pending', 'approved'])
            ->count();
    }
}
