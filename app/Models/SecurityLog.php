<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * โมเดลบันทึกเหตุการณ์ความปลอดภัย
 * event_type: multi_account_login | suspicious_checkin | device_mismatch
 */
class SecurityLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'event_type',
        'ip_address',
        'device_fingerprint',
        'related_user_ids',
        'details',
        'is_reviewed',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'related_user_ids' => 'array',
            'details'          => 'array',
            'is_reviewed'      => 'boolean',
            'reviewed_at'      => 'datetime',
        ];
    }

    /** ผู้ใช้หลักที่เกี่ยวข้องกับเหตุการณ์ */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** admin ที่ตรวจสอบ */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Scope: เฉพาะที่ยังไม่ได้ตรวจสอบ */
    public function scopeUnreviewed($query)
    {
        return $query->where('is_reviewed', false);
    }

    /** แปลง event_type เป็นข้อความภาษาไทย (ตามกฎ SVG เท่านั้น ไม่ใช้ Emoji) */
    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            'multi_account_login'  => 'เข้าสู่ระบบหลายบัญชี (Multi Account Login)',
            'suspicious_checkin'   => 'การเช็คอินน่าสงสัย (Suspicious Checkin)',
            'device_mismatch'      => 'อุปกรณ์ไม่ตรงกับที่บันทึกไว้',
            'staff_login_failed'   => 'เข้าสู่ระบบเจ้าหน้าที่ไม่สำเร็จ',
            'rate_limit_exceeded'  => 'คำขอเกินขีดจำกัดความปลอดภัย',
            default                => ucwords(str_replace('_', ' ', (string) $this->event_type)),
        };
    }
}

