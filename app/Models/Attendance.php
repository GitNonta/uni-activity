<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'checked_out_at',
        'method',
        'checkout_method',
        'status',
        'verified_by',
        'is_verified',
        'ip_address',
        'device_fingerprint',
        'is_suspicious',
        'checkin_latitude',
        'checkin_longitude',
        'checkout_latitude',
        'checkout_longitude',
        'distance_meters',
        'checkout_distance_meters',
        'selfie_photo_path',
        'face_match_score',
        'face_match_passed',
        'liveness_score',
        'liveness_passed',
        'detector_pipeline',
        'selfie_reviewed',
        'selfie_review_result',
        'selfie_reviewed_by',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'checked_in_at'            => 'datetime',
            'checked_out_at'           => 'datetime',
            'is_verified'              => 'boolean',
            'is_suspicious'            => 'boolean',
            'checkin_latitude'         => 'decimal:7',
            'checkin_longitude'        => 'decimal:7',
            'checkout_latitude'        => 'decimal:7',
            'checkout_longitude'       => 'decimal:7',
            'distance_meters'          => 'decimal:2',
            'checkout_distance_meters' => 'decimal:2',
            'face_match_score'         => 'decimal:2',
            'face_match_passed'        => 'boolean',
            'liveness_score'           => 'decimal:4',
            'liveness_passed'          => 'boolean',
            'selfie_reviewed'          => 'boolean',
        ];
    }

    /** ความสัมพันธ์: การเข้าร่วมเป็นของผู้ใช้ */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ความสัมพันธ์: การเข้าร่วมสังกัดกิจกรรม */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** ความสัมพันธ์: ผู้ตรวจสอบการเข้าร่วม (เจ้าหน้าที่) */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** ความสัมพันธ์: ผู้ตรวจสอบ selfie */
    public function selfieReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selfie_reviewed_by');
    }

    /**
     * ดึงรายการเหตุผลความเสี่ยงหรือเงื่อนไขที่ยังไม่ผ่านเกณฑ์การอนุมัติอัตโนมัติ
     * @return array<int, string>
     */
    public function getRiskReasonsAttribute(): array
    {
        $reasons = [];

        if ($this->face_match_score !== null && (float) $this->face_match_score < 80.0) {
            $reasons[] = 'คะแนนใบหน้า ' . round((float) $this->face_match_score, 1) . '% (ต่ำกว่าเกณฑ์ 80%)';
        }

        if ($this->face_match_passed === false) {
            $reasons[] = 'ใบหน้าไม่ตรงกับโปรไฟล์';
        }

        if ($this->liveness_passed === false) {
            $reasons[] = 'ตรวจบุคคลจริง (Liveness) ไม่ผ่าน';
        }

        if ($this->distance_meters !== null && (float) $this->distance_meters > 50.0) {
            $reasons[] = 'GPS ห่าง ' . round((float) $this->distance_meters) . ' ม. (เกินเกณฑ์ 50 ม.)';
        }

        if ($this->is_suspicious) {
            $reasons[] = 'ตรวจพบความเสี่ยงหรืออุปกรณ์ซ้ำ';
        }

        return $reasons;
    }
}

