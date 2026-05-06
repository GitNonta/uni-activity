<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลการตั้งค่าระบบ (key-value)
 * เช่น total_required_hours สำหรับกำหนดเกณฑ์ชั่วโมงรวม
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value'];

    /** ดึงค่าจาก key (คืน null ถ้าไม่มี) */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);
        return $row ? $row->value : $default;
    }

    /** บันทึกหรืออัปเดตค่า */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
