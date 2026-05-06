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
}
