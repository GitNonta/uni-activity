<?php

namespace App\Models;

use App\Models\Concerns\HasSlugRouting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลประกาศรับสมัครงาน
 * เก็บข้อมูลงานทั่วไป / Part-time พร้อมพิกัดสำหรับแผนที่
 */
class JobListing extends Model
{
    use HasFactory, HasSlugRouting;

    protected $fillable = [
        'title', 'title_en', 'job_type', 'position', 'position_en', 'quota',
        'work_period', 'compensation', 'location', 'location_en',
        'start_date', 'end_date', 'dresscode', 'gender',
        'note', 'note_en', 'description', 'description_en', 'status',
        'latitude', 'longitude', 'image_path',
        'created_by',
    ];


    /**
     * [tag:2026-10-14-translation] แปลภาษาโพสต์ — ประกาศงาน
     */
    public function getTitleAttribute($value)
    {
        if (app()->getLocale() === 'en' && filled($this->attributes['title_en'] ?? null)) {
            return (string) $this->attributes['title_en'];
        }

        return $value ?? null;
    }

    public function getPositionAttribute($value)
    {
        if (app()->getLocale() === 'en' && filled($this->attributes['position_en'] ?? null)) {
            return (string) $this->attributes['position_en'];
        }

        return $value ?? null;
    }

    public function getDescriptionAttribute($value)
    {
        if (app()->getLocale() === 'en' && filled($this->attributes['description_en'] ?? null)) {
            return (string) $this->attributes['description_en'];
        }

        return $value ?? null;
    }

    public function getLocationAttribute($value)
    {
        if (app()->getLocale() === 'en' && filled($this->attributes['location_en'] ?? null)) {
            return (string) $this->attributes['location_en'];
        }

        return $value ?? null;
    }

    public function getNoteAttribute($value)
    {
        if (app()->getLocale() === 'en' && filled($this->attributes['note_en'] ?? null)) {
            return (string) $this->attributes['note_en'];
        }

        return $value ?? null;
    }

    /**
     * Accessor สำรอง — ค่าไทยต้นฉบับสำหรับฟอร์มแก้ไข admin
     */
    public function getTitleThAttribute(): ?string
    {
        return (string) ($this->attributes['title'] ?? '');
    }

    public function getPositionThAttribute(): ?string
    {
        return (string) ($this->attributes['position'] ?? '');
    }


    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'quota' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    // ── ความสัมพันธ์ ──

    /** ประกาศงานถูกสร้างโดย staff/admin */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ประกาศงานมีผู้สมัครหลายคน */
    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    /** ประกาศงานมีคอมเมนต์หลายรายการ */
    public function comments()
    {
        return $this->hasMany(JobComment::class);
    }

    // ── Helper Methods ──

    /** ตรวจสอบว่ามีพิกัด GPS หรือไม่ */
    public function hasGeolocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** นับจำนวนผู้สมัครทั้งหมด */
    public function getTotalApplicantsAttribute(): int
    {
        return $this->applications()->count();
    }

    /** นับจำนวนผู้ได้รับการยืนยัน */
    public function getConfirmedApplicantsAttribute(): int
    {
        return $this->applications()->where('status', 'confirmed')->count();
    }

    /** คำนวณเปอร์เซ็นต์ความคืบหน้าการยืนยัน */
    public function getProgressPercentAttribute(): float
    {
        if ($this->quota <= 0) return 0;
        return min(100, ($this->confirmed_applicants / $this->quota) * 100);
    }

    /** ตรวจสอบว่ายังเปิดรับสมัครอยู่ไหม */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** ตรวจสอบว่ายังมีที่ว่างไหม */
    public function hasAvailableSlots(): bool
    {
        return $this->confirmed_applicants < $this->quota;
    }
}
