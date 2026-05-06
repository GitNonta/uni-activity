<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * โมเดลการสมัครงาน
 * เก็บสถานะการสมัคร: pending / confirmed / rejected
 */
class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_listing_id', 'user_id', 'status',
    ];

    /** ความสัมพันธ์: การสมัครเชื่อมกับประกาศงาน */
    public function jobListing()
    {
        return $this->belongsTo(JobListing::class);
    }

    /** ความสัมพันธ์: การสมัครเชื่อมกับผู้ใช้ (นักศึกษา) */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
