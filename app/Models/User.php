<?php

namespace App\Models;

use Database\Factories\UserFactory;
use App\Models\Room;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * โมเดลผู้ใช้งาน (นักศึกษา / เจ้าหน้าที่)
 * - นักศึกษา: เข้าสู่ระบบด้วย student_id
 * - เจ้าหน้าที่: เข้าสู่ระบบด้วย email + password
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    protected $fillable = [
        'student_id',
        'email',
        'password',
        'full_name',
        'phone',
        'position',
        'organization',
        'faculty',
        'department',
        'year',
        'program',
        'role',
        'is_active',
        'profile_photo',
        'last_seen_at',
    ];

    /** ฟิลด์ที่ซ่อนเมื่อแปลงเป็น JSON */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'year'          => 'integer',
            'password'      => 'hashed',
            'last_seen_at'  => 'datetime',
        ];
    }

    /** ความสัมพันธ์: ผู้ใช้มีการลงทะเบียนหลายรายการ */
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /** ความสัมพันธ์: ผู้ใช้มีการเข้าร่วมกิจกรรมหลายรายการ */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /** ความสัมพันธ์: ผู้ใช้มีการแจ้งเตือนหลายรายการ */
    public function customNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    /** ความสัมพันธ์: ผู้ใช้มีการประเมินกิจกรรมหลายรายการ */
    public function feedbacks()
    {
        return $this->hasMany(ActivityFeedback::class);
    }

    /** คำนวณชั่วโมงกิจกรรมรวมทั้งหมดที่เข้าร่วม */
    public function totalHours(): float
    {
        return $this->attendances()->with('activity')->get()
                    ->sum('activity.activity_hours');
    }

    /** ตรวจสอบว่าเป็นผู้ดูแลระบบ (สิทธิ์สูงสุด) */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** ตรวจสอบว่าเป็นเจ้าหน้าที่หรือไม่ (เฉพาะ staff ไม่รวม admin) */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /** ตรวจสอบว่าเป็น staff หรือ admin (เข้าถึงหลังบ้านได้) */
    public function isStaffOrAdmin(): bool
    {
        return in_array($this->role, ['staff', 'admin']);
    }

    /** ตรวจสอบว่าเป็นนักศึกษาหรือไม่ */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /** ส่ง notification รีเซ็ตรหัสผ่าน (สำหรับเจ้าหน้าที่และผู้ดูแลระบบ) */
    public function sendPasswordResetNotification($token): void
    {
        if (in_array($this->role, ['staff', 'admin'])) {
            $this->notify(new \App\Notifications\StaffResetPasswordNotification($token));
        } else {
            // กรณีบทบาทอื่น (เช่น นักศึกษา) ให้ใช้ระบบปกติของ Laravel (ถ้ามีการตั้งค่าไว้)
            parent::sendPasswordResetNotification($token);
        }
    }

    /**
     * The rooms that the user belongs to.
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class)
            ->withPivot(['role', 'last_read_at', 'joined_at'])
            ->withTimestamps();
    }
}
