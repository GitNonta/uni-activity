<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * โมเดลเก็บบันทึกประวัติและป้องกันการส่ง Auto-Reminder ซ้ำซ้อน (24h / 2h)
 *
 * @property int $id
 * @property int $activity_id
 * @property string $reminder_type
 * @property \Illuminate\Support\Carbon $sent_at
 * @property int $recipients_count
 * @property int $line_sent_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ActivityReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'reminder_type',
        'sent_at',
        'recipients_count',
        'line_sent_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at'          => 'datetime',
            'recipients_count' => 'integer',
            'line_sent_count'  => 'integer',
        ];
    }

    /**
     * ความสัมพันธ์: บันทึกของกิจกรรม
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
