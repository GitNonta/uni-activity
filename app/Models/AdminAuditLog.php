<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * โมเดล AdminAuditLog: บันทึกการกระทำของ Admin ในระบบ
 */
class AdminAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /** ผู้ดำเนินการ (admin) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** record ที่ถูกกระทำ (polymorphic) */
    public function subject(): MorphTo
    {
        return $this->morphTo(null, 'model_type', 'model_id');
    }

    /** แปลง action เป็นข้อความภาษาไทย */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'create'  => 'สร้าง',
            'update'  => 'แก้ไข',
            'delete'  => 'ลบ',
            'approve' => 'อนุมัติ',
            'reject'  => 'ปฏิเสธ',
            'toggle'  => 'สลับสถานะ',
            'login'   => 'เข้าสู่ระบบ',
            'logout'  => 'ออกจากระบบ',
            default   => $this->action,
        };
    }

    /** แปลง model_type เป็นชื่อภาษาไทย */
    public function getModelLabelAttribute(): string
    {
        if (!$this->model_type) return '-';

        return match (class_basename($this->model_type)) {
            'Activity'     => 'กิจกรรม',
            'User'         => 'ผู้ใช้',
            'Registration' => 'การลงทะเบียน',
            'Attendance'   => 'การเข้าร่วม',
            'ActivityCategory' => 'หมวดหมู่',
            default        => class_basename($this->model_type),
        };
    }

    /** สี badge ตาม action */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create'  => 'badge-green',
            'update'  => 'badge-yellow',
            'delete'  => 'badge-red',
            'approve' => 'badge-green',
            'reject'  => 'badge-red',
            'toggle'  => 'badge-yellow',
            'login'   => 'badge-blue',
            'logout'  => 'badge-gray',
            default   => '',
        };
    }
}
