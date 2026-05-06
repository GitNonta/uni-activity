<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * โมเดล ActivityFeedback: การประเมินกิจกรรมของนักศึกษา
 */
class ActivityFeedback extends Model
{
    protected $table = 'activity_feedbacks';

    protected $fillable = [
        'activity_id',
        'user_id',
        'rating',
        'comment',
        'ratings',
        'is_anonymous',
    ];

    protected $casts = [
        'ratings' => 'array',
        'is_anonymous' => 'boolean',
    ];

    /** กิจกรรมที่ถูกประเมิน */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** ผู้ประเมิน */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** แสดงดาวตามคะแนน */
    public function getStarsAttribute(): string
    {
        return str_repeat('⭐', $this->rating);
    }

    /** คำนวณคะแนนเฉลี่ยจากหัวข้อย่อย */
    public function getDetailedAverageAttribute(): ?float
    {
        if (!$this->ratings || !is_array($this->ratings)) {
            return null;
        }

        $scores = array_filter($this->ratings, 'is_numeric');
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
    }
}
