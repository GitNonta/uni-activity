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

    /** แปลง event_type เป็นข้อความภาษาไทย */
    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            'multi_account_login' => '🔴 Login หลาย Account จากเครื่องเดียวกัน',
            'suspicious_checkin'  => '🟡 เช็คอินน่าสงสัย (IP/Device ซ้ำ)',
            'device_mismatch'     => '🟠 Device ไม่ตรงกับครั้งที่แล้ว',
            default               => $this->event_type,
        };
    }
}

