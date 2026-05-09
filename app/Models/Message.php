<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'room_id',
        'user_id',
        'body',
        'type',
        'attachments',
        'read_by',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_by' => 'array',
    ];

    /**
     * Get the room that owns the message.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user who sent the message.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
