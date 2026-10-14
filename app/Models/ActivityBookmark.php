<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityBookmark extends Model
{
    protected $table = 'activity_bookmarks';

    protected $fillable = ['user_id', 'activity_id'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'activity_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
