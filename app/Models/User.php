<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use App\Models\Room;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * โมเดลผู้ใช้งาน (นักศึกษา / เจ้าหน้าที่)
 * - นักศึกษา: เข้าสู่ระบบด้วย student_id
 * - เจ้าหน้าที่: เข้าสู่ระบบด้วย email + password
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** ฟิลด์ที่อนุญาตให้บันทึกผ่าน mass assignment */
    /** ผูก route ผ่าน username แทน id — URL แบบ /users/{username} */
    public function getRouteKeyName(): string
    {
        return 'username';
    }

    protected static function booted(): void
    {
        // สร้าง username อัตโนมัติถ้าไม่ได้ระบุ (ครอบคลุม factory/seed/SSO/LINE/admin panel)
        static::creating(function (self $user): void {
            if (empty($user->username)) {
                $user->username = self::generateUsername(
                    $user->english_name,
                    $user->full_name,
                    $user->email ? explode('@', $user->email)[0] : null,
                    $user->student_id ? 'user' . $user->student_id : null,
                );
            }
        });
    }

    /** สร้าง username ที่ไม่ซ้ำจากตัวเลือกที่มี (fallback: user + id/สุ่ม) */
    public static function generateUsername(?string ...$candidates): string
    {
        $normalized = array_values(array_filter(array_map(
            fn (?string $c): ?string => self::normalizeUsername($c),
            $candidates,
        )));

        if (empty($normalized)) {
            $normalized = ['user-' . strtolower((string) \Illuminate\Support\Str::random(6))];
        }

        $base = $normalized[0];
        $username = $base;
        $i = 0;
        while (self::query()->where('username', $username)->exists()) {
            $i++;
            $username = \Illuminate\Support\Str::limit($base, 56, '') . '-' . $i;
            if ($i > 50) {
                $username = 'user-' . strtolower((string) \Illuminate\Support\Str::random(8));
                if (!self::query()->where('username', $username)->exists()) {
                    break;
                }
            }
        }

        return $username;
    }

    /** ASCII-friendly username: a-z0-9 และขีด ยาวไม่เกิน 64 */
    public static function normalizeUsername(?string $s): ?string
    {
        if ($s === null || trim($s) === '') {
            return null;
        }

        $s = \Illuminate\Support\Str::ascii(trim($s));
        $s = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $s));
        $s = trim($s, '-');

        return $s !== '' ? \Illuminate\Support\Str::limit($s, 64, '') : null;
    }

    /** ถ้าไม่มี username (partial select / ข้อมูลเก่า) ใช้ id — resolveRouteBinding รองรับทั้งสองแบบ */
    public function getRouteKey(): string
    {
        return $this->username ?: (string) $this->getKey();
    }

    /** ตัวเลขล้วน = ลิงก์รูปแบบเดิม (id) — ยังเปิด/ยิง API ได้เหมือนเดิม */
    public function resolveRouteBinding($value, $field = null)
    {
        if (ctype_digit((string) $value)) {
            return $this->findOrFail((int) $value);
        }

        return static::query()->where($field ?: $this->getRouteKeyName(), $value)->firstOrFail();
    }

    protected $fillable = [
        'username',
        'student_id',
        'email',
        'password',
        'full_name',
        'english_name',
        'phone',
        'position',
        'organization',
        'faculty',
        'department',
        'year',
        'program',
        'role',
        'gender',
        'is_active',
        'profile_photo',
        'last_seen_at',
        'line_user_id',
        'line_display_name',
        'line_notify_enabled',
        'last_login_ip',
        'last_login_at',
        'last_device_fingerprint',
        'face_descriptor',
        'face_descriptor_js',
    ];

    /** ฟิลด์ที่ซ่อนเมื่อแปลงเป็น JSON (Biometric PDPA Compliance) */
    protected $hidden = [
        'password',
        'remember_token',
        'face_descriptor',
        'face_descriptor_js',
    ];

    /** กำหนดประเภทการแปลงค่าฟิลด์ */
    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'year'                => 'integer',
            'password'            => 'hashed',
            'last_seen_at'        => 'datetime',
            'line_notify_enabled' => 'boolean',
            'last_login_at'       => 'datetime',
            'face_descriptor'     => 'encrypted:array',
            'face_descriptor_js'  => 'encrypted:array',
        ];
    }

    /** ความสัมพันธ์: ผู้ใช้มีบันทึกความปลอดภัยหลายรายการ */
    public function securityLogs()
    {
        return $this->hasMany(SecurityLog::class);
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

    /** ความสัมพันธ์: ผู้ใช้ที่คนนี้กดติดตาม (กำลังติดตาม) */
    public function followings(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    /** ความสัมพันธ์: ผู้ใช้ที่กดติดตามคนนี้ (ผู้ติดตาม) */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    /** ตรวจสอบว่าผู้ใช้นี้กดติดตามผู้ใช้อื่นอยู่หรือไม่ */
    public function isFollowing(User $other): bool
    {
        return $this->followings()->where('following_id', $other->id)->exists();
    }

    /** จำนวนผู้ติดตามของผู้ใช้นี้ */
    public function followersCount(): int
    {
        return (int) $this->followers()->count();
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

    /** ข้อความแสดงผลของเพศ */
    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'male'   => 'ชาย',
            'female' => 'หญิง',
            'other'  => 'อื่นๆ / ไม่ระบุ',
            default  => 'ไม่ระบุ',
        };
    }

    /** ประเภทอวตาร (male, female, neutral) */
    public function getAvatarTypeAttribute(): string
    {
        return match ($this->gender) {
            'male'   => 'male',
            'female' => 'female',
            default  => 'neutral',
        };
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
