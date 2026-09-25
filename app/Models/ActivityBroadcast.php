<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * โมเดลเก็บบันทึกประวัติการบรอดแคสต์ข้อความด่วนเฉพาะกิจกรรม
 *
 * @property int $id
 * @property int $activity_id
 * @property int|null $sender_id
 * @property string $title
 * @property string $message
 * @property string $type
 * @property string $target_audience
 * @property array<string>|null $channels
 * @property int $recipients_count
 * @property int $line_sent_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ActivityBroadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'sender_id',
        'title',
        'message',
        'type',
        'target_audience',
        'channels',
        'recipients_count',
        'line_sent_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels'         => 'array',
            'recipients_count' => 'integer',
            'line_sent_count'  => 'integer',
        ];
    }

    /**
     * ความสัมพันธ์: บรอดแคสต์เป็นของกิจกรรม
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * ความสัมพันธ์: ผู้ส่งข้อความ (Admin / Staff)
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
