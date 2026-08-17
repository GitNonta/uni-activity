<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'image_path',
        'target_faculty',
        'type',
        'is_active',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** ประกาศที่ถึงเวลาเผยแพร่แล้ว (หรือไม่ได้ตั้งเวลา = เผยแพร่ทันที) */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('published_at')
              ->orWhere('published_at', '<=', now());
        });
    }

    /** กองประกาศสำหรับนักศึกษาที่กำลังเข้าดู */
    public function scopeForAudience(Builder $query, ?User $user = null): Builder
    {
        $query->where('is_active', true)->published();

        if ($user && $user->role === 'student') {
            $query->where(function (Builder $q) use ($user): void {
                $q->whereNull('target_faculty')
                  ->orWhere('target_faculty', $user->faculty);
            });
        }

        return $query;
    }

    /** สถานะเผยแพร่สำหรับแสดงในแอดมิน */
    public function getPublishStatusAttribute(): string
    {
        if ($this->published_at === null) {
            return 'เผยแพร่ทันที';
        }

        if ($this->published_at->isPast()) {
            return 'เผยแพร่แล้ว';
        }

        return 'ตั้งเวลา ' . $this->published_at->format('d/m/Y H:i');
    }

    /** ความสัมพันธ์กับผู้สร้างประกาศ */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
