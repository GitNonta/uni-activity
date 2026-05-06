<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลคอมเมนต์ประกาศงาน
 * รองรับ reply ผ่าน parent_id (self-referencing)
 */
class JobComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_listing_id', 'user_id', 'parent_id', 'body',
    ];

    /** ความสัมพันธ์: คอมเมนต์เชื่อมกับประกาศงาน */
    public function jobListing()
    {
        return $this->belongsTo(JobListing::class);
    }

    /** ความสัมพันธ์: คอมเมนต์เชื่อมกับผู้เขียน */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** ความสัมพันธ์: คอมเมนต์สามารถเป็นลูกของคอมเมนต์อื่น (reply) */
    public function parent()
    {
        return $this->belongsTo(JobComment::class, 'parent_id');
    }

    /** ความสัมพันธ์: คอมเมนต์มี replies */
    public function replies()
    {
        return $this->hasMany(JobComment::class, 'parent_id')->orderBy('created_at', 'asc');
    }
}
