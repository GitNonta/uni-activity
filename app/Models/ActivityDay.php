<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * โมเดลตารางกำหนดการและชั่วโมงกิจกรรมรายวันสำหรับกิจกรรมหลายวัน
 *
 * @property int $id
 * @property int $activity_id
 * @property int $day_number
 * @property \Illuminate\Support\Carbon $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property float $activity_hours
 * @property \Illuminate\Support\Carbon|null $checkin_open_at
 * @property \Illuminate\Support\Carbon|null $checkin_close_at
 * @property \Illuminate\Support\Carbon|null $checkout_open_at
 * @property \Illuminate\Support\Carbon|null $checkout_close_at
 */
class ActivityDay extends Model
{
    use HasFactory;

    protected $table = 'activity_days';

    /** ฟิลด์ที่อนุญาตให้ mass assignment */
    protected $fillable = [
        'activity_id',
        'day_number',
        'date',
        'start_time',
        'end_time',
        'activity_hours',
        'checkin_open_at',
        'checkin_close_at',
        'checkout_open_at',
        'checkout_close_at',
    ];

    /** การแปลงชนิดข้อมูลของฟิลด์ */
    protected function casts(): array
    {
        return [
            'day_number'        => 'integer',
            'date'              => 'date',
            'activity_hours'    => 'decimal:1',
            'checkin_open_at'   => 'datetime',
            'checkin_close_at'  => 'datetime',
            'checkout_open_at'  => 'datetime',
            'checkout_close_at' => 'datetime',
        ];
    }

    /**
     * ความสัมพันธ์: วันนี้เป็นของกิจกรรมใด
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
