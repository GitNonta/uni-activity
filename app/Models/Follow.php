<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * โมเดลความสัมพันธ์ "ติดตาม" (Follow)
 * follower_id = ผู้ที่กดติดตาม, following_id = ผู้ที่ถูกติดตาม
 */
class Follow extends Model
{
    protected $fillable = [
        'follower_id',
        'following_id',
    ];

    /** ผู้ที่กดติดตาม */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /** ผู้ที่ถูกติดตาม */
    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
