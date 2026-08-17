<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_code',
        'user_id',
        'category_id',
        'title',
        'hours_completed',
        'academic_year',
        'issued_at',
        'verification_token',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'hours_completed' => 'float',
            'issued_at'       => 'datetime',
            'metadata'        => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class, 'category_id');
    }

    public function getVerificationUrlAttribute(): string
    {
        return route('certificates.verify', $this->certificate_code);
    }
}
