<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * โมเดล AdminAuditLog: บันทึกการกระทำของ Admin ในระบบ
 */
class AdminAuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /** ผู้ดำเนินการ (admin) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** record ที่ถูกกระทำ (polymorphic) */
    public function subject(): MorphTo
    {
        return $this->morphTo(null, 'model_type', 'model_id');
    }

    /** แปลง action เป็นข้อความภาษาไทย */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'create'  => __('สร้าง'),
            'update'  => __('แก้ไข'),
            'delete'  => __('ลบ'),
            'approve' => __('อนุมัติ'),
            'reject'  => __('ปฏิเสธ'),
            'toggle'  => __('สลับสถานะ'),
            'login'   => __('เข้าสู่ระบบ'),
            'logout'  => __('ออกจากระบบ'),
            default   => $this->action,
        };
    }

    /** แปลง model_type เป็นชื่อภาษาไทย */
    public function getModelLabelAttribute(): string
    {
        if (!$this->model_type) return '-';

        return match (class_basename($this->model_type)) {
            'Activity'     => 'กิจกรรม',
            'User'         => 'ผู้ใช้',
            'Registration' => 'การลงทะเบียน',
            'Attendance'   => 'การเข้าร่วม',
            'ActivityCategory' => 'หมวดหมู่',
            default        => class_basename($this->model_type),
        };
    }

    /** สี badge ตาม action */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'create'  => 'badge-green',
            'update'  => 'badge-yellow',
            'delete'  => 'badge-red',
            'approve' => 'badge-green',
            'reject'  => 'badge-red',
            'toggle'  => 'badge-yellow',
            'login'   => 'badge-blue',
            'logout'  => 'badge-gray',
            default   => '',
        };
    }

    /** รายการ prefix ภาษาไทยของ log เก่า -> เทมเพลตอังกฤษ (%s = ค่าที่จับได้) */
    private static function legacyDescriptionPatterns(): array
    {
        return [
            '/^สร้างกิจกรรมด่วน\s+"(.+)"/u'                => 'Created quick activity "%1$s"',
            '/^สร้างกิจกรรม\s+"(.+)"/u'                   => 'Created activity "%1$s"',
            '/^แก้ไขกิจกรรม\s+"(.+)"/u'                   => 'Updated activity "%1$s"',
            '/^ลบกิจกรรม\s+"(.+)"/u'                      => 'Deleted activity "%1$s"',
            '/^(เปิด|ปิด)เช็คอินก่อนเวลา:\s+"(.+)"/u'      => null, // handled inline
            '/^สร้าง QR Code ใหม่สำหรับกิจกรรม\s+"(.+)"/u'  => 'Generated a new QR code for activity "%1$s"',
            '/^สร้าง QR Code ออกงานใหม่สำหรับกิจกรรม\s+"(.+)"/u' => 'Generated a new checkout QR code for activity "%1$s"',
            '/^คัดลอกกิจกรรม #(\d+)\s+"(.+)"\s+เป็น #(\d+)/u'  => 'Cloned activity #%1$s "%2$s" to #%3$s',
            '/^เช็คอิน manual:\s*(.+?)\s+ในกิจกรรม\s+"(.+)"/u'  => 'Manual check-in: %1$s in activity "%2$s"',
            '/^อนุมัติการเข้าร่วม #(\d+)/u'                => 'Attendance approved #%1$s',
            '/^ปฏิเสธการเข้าร่วม #(\d+)/u'                => 'Attendance rejected #%1$s',
            '/^อนุมัติ ภาพ Selfie รหัสเข้าร่วม #(\d+)/u'    => 'Approved selfie verification for check-in #%1$s',
            '/^ปฏิเสธ ภาพ Selfie รหัสเข้าร่วม #(\d+)/u'    => 'Rejected selfie verification for check-in #%1$s',
            '/^อนุมัติการลงทะเบียน #(\d+)(\s+\(Dashboard\))?/u' => null, // handled inline
            '/^ปฏิเสธการลงทะเบียน #(\d+)(\s+\(Dashboard\))?/u' => null, // handled inline
            '/^(เปิด|ปิด)การใช้งานประกาศ\s+"(.+)"/u'       => null, // handled inline
            '/^สร้างประกาศ\s+"(.+)"/u'                    => 'Created announcement "%1$s"',
            '/^แก้ไขประกาศ\s+"(.+)"/u'                    => 'Updated announcement "%1$s"',
            '/^ลบประกาศ\s+"(.+)"/u'                       => 'Deleted announcement "%1$s"',
            '/^สร้างหมวดหมู่\s+"(.+)"/u'                  => 'Created category "%1$s"',
            '/^แก้ไขหมวดหมู่\s+"(.+)"/u'                  => 'Updated category "%1$s"',
            '/^ลบหมวดหมู่\s+"(.+)"/u'                     => 'Deleted category "%1$s"',
            '/^แก้ไขโปรไฟล์ส่วนตัว/u'                      => 'Updated own profile',
            '/^เพิ่มบันทึกกิจกรรมให้\s+"(.+)"/u'          => 'Added activity record for "%1$s"',
            '/^แก้ไขบันทึกกิจกรรมของ\s+"(.+)"/u'          => 'Updated activity record of "%1$s"',
            '/^ลบบันทึกกิจกรรมของ\s+"(.+)"/u'             => 'Deleted activity record of "%1$s"',
            '/^สร้างผู้ใช้ \((\w+)\):\s+"(.+)"/u'       => 'Created user (%1$s): "%2$s"',
            '/^แก้ไขผู้ใช้\s+"(.+)"/u'                    => 'Updated user "%1$s"',
            '/^ลบผู้ใช้ \((\w+)\):\s+"(.+)"/u'         => 'Deleted user (%1$s): "%2$s"',
            '/^(เปิด|ปิด)การใช้งาน:\s+"(.+)"/u'            => null, // handled inline
            '/^นำเข้านักศึกษาสำเร็จ: สร้างใหม่ (\d+) คน, อัปเดต (\d+) คน, ข้าม (\d+) คน/u'
                                                           => 'Student import completed: %1$s created, %2$s updated, %3$s skipped',
        ];
    }

    /** แปลชื่อประเภทของแถวเก่า (เปิด/ปิด, อนุมัติ/ปฏิเสธ) เป็นอังกฤษ */
    private static function enWord(string $th): string
    {
        return match ($th) {
            'เปิด' => 'Enabled', 'ปิด' => 'Disabled',
            'อนุมัติ' => 'Approved', 'ปฏิเสธ' => 'Rejected',
            default => $th,
        };
    }

    /**
     * ข้อความนำไปแสดงของ description — แปลตามภาษาปัจจุบัน
     * (ถ้า __() แปลไม่ได้เพราะแถวเก่าเก็บไทยมีชื่อกิจกรรมแปะ ใช้ pattern แปลเฉพาะ prefix)
     */
    public function getDescriptionDisplayAttribute(): string
    {
        $d = (string) $this->description;
        if ($d === '') return $d;
        $t = __($d);
        if ($t !== $d) return $t;
        if (app()->getLocale() !== 'en') return $d;

        foreach (self::legacyDescriptionPatterns() as $re => $tpl) {
            if (preg_match($re, $d, $m) !== 1) continue;
            $args = array_slice($m, 1);
            // ค่าที่เป็นชื่อ (ชื่อกิจกรรม/ผู้ใช้/หมวดหมู่) ลองแปลผ่าน dictionary ด้วย
            foreach ($args as $i => $v) {
                if (is_string($v) && preg_match('/[ก-๙]/u', $v) && !ctype_digit($v)) {
                    $tv = __($v);
                    if ($tv !== $v) $args[$i] = $tv;
                } elseif (is_numeric($v) && $v === '0' && (int) $v === 0) {
                    // no-op
                }
            }
            return match ($tpl) {
                null => self::legacyInline($d, $m),
                default => vsprintf($tpl, $args),
            };
        }
        return $d;
    }

    private static function legacyInline(string $d, array $m): string
    {
        if (isset($m[2], $m[1]) && str_contains($d, 'เช็คอินก่อนเวลา')) {
            return sprintf('%s early check-in: "%s"', self::enWord($m[1]), $m[2]);
        }
        if (isset($m[1]) && str_contains($d, 'การลงทะเบียน')) {
            $word = str_starts_with($d, 'อนุมัติ') ? 'อนุมัติ' : 'ปฏิเสธ';
            $suffix = str_contains($d, '(Dashboard)') ? ' (Dashboard)' : '';
            return sprintf('%s registration #%s%s', self::enWord($word), $m[1], $suffix);
        }
        if (isset($m[2], $m[1]) && str_contains($d, 'ประกาศ')) {
            return sprintf('%s announcement "%s"', self::enWord($m[1]), $m[2]);
        }
        if (isset($m[2], $m[1]) && str_contains($d, 'การใช้งาน:')) {
            return sprintf('%s user "%s"', self::enWord($m[1]), $m[2]);
        }
        return $d;
    }
}

