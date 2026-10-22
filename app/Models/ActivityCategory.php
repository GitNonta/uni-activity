<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลหมวดหมู่กิจกรรม
 * เช่น จิตอาสา, วิชาการ, กีฬา ฯลฯ พร้อมกำหนดชั่วโมงขั้นต่ำที่ต้องทำ
 */
class ActivityCategory extends Model
{
    use HasFactory;

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    protected $fillable = [
        'name',
        'description',
        'required_hours',
        'icon',
        'color',
        'options',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'required_hours' => 'decimal:1',
            'options' => 'array',
        ];
    }

    /** ความสัมพันธ์: หมวดหมู่มีกิจกรรมหลายรายการ */
    public function activities()
    {
        return $this->hasMany(Activity::class, 'category_id');
    }

    /**
     * [tag:2026-10-14-translation] ชื่อหมวดหมู่แปลตาม locale ผ่าน key ใน lang/{locale}.json
     * (ชื่อหมวดเป็นชุดคงที่ ใช้ __($name) — ถ้าไม่มี key ใน locale นั้นจะ fallback เป็นข้อความเดิม)
     */
    public function getNameAttribute($value)
    {
        if (app()->getLocale() !== 'th') {
            $t = __($value);
            if (is_string($t) && $t !== $value) {
                return $t;
            }
        }

        return $value ?? null;
    }

    /** คำอธิบายหมวดหมู่แปลตาม locale ผ่าน key ใน lang/{locale}.json (fallback เป็นข้อความเดิม) */
    public function getDescriptionAttribute($value)
    {
        if (app()->getLocale() !== 'th' && filled($value)) {
            $t = __($value);
            if (is_string($t) && $t !== $value) {
                return $t;
            }
        }

        return $value ?? null;
    }
}
