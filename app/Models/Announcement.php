<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'created_by',
    ];

    /** กองประกาศสำหรับนักศึกษาที่กำลังเข้าดู */
    public function scopeForAudience($query, $user = null)
    {
        $query->where('is_active', true);
        
        if ($user && $user->role === 'student') {
            $query->where(function ($q) use ($user) {
                $q->whereNull('target_faculty')
                  ->orWhere('target_faculty', $user->faculty);
            });
        }
        
        return $query;
    }

    /** ความสัมพันธ์กับผู้สร้างประกาศ */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
