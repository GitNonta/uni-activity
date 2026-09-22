<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * ทำให้ Model ผูกกับ route ผ่าน slug แทน ID
 *
 * - เพิ่ม 'slug' ให้ fillable อัตโนมัติ
 * - สร้าง slug อัตโนมัติจาก title เมื่อสร้างรายการใหม่ (ถ้าไม่ได้ระบุมาเอง)
 * - URL ได้รูปแบบ /activities/{slug} — มีชื่อใน URL แทน ID
 * - resolveRouteBinding รองรับ: {slug} (ใหม่) และ {id} (เดิม)
 *   ลิงก์เก่าและข้อความแจ้งเตือนที่เก็บ id ไว้จึงยังใช้งานได้ต่อเนื่อง
 */
trait HasSlugRouting
{
    public function initializeHasSlugRouting(): void
    {
        $this->fillable = array_values(array_unique(array_merge($this->fillable, ['slug'])));
    }

    /** Boot hook ของ trait — สร้าง slug อัตโนมัติเมื่อสร้างรายการใหม่ */
    public static function bootHasSlugRouting(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::generateUniqueSlug((string) $model->getAttribute('title'));
            }
        });
    }

    /** สร้าง slug ที่ไม่ซ้ำกันภายในตาราง (ตัดข้อความไทย/พิเศษออก แล้ว append สุ่มถ้าชน) */
    public static function generateUniqueSlug(string $title): string
    {
        $base = static::slugifyTitle($title) ?: 'item';
        $slug = $base;
        $attempt = 0;

        while (static::query()->where('slug', $slug)->exists()) {
            $attempt++;
            $suffix = '-' . strtolower(Str::random(4));
            // คงความยาวรวมไม่เกิน 191 ตัวอักษร (ขนาดคอลัมน์)
            $slug = Str::limit($base, 191 - strlen($suffix), '') . $suffix;
            if ($attempt > 20) {
                $slug = strtolower(Str::random(24));
            }
        }

        return $slug;
    }

    /** แปลง title เป็น slug — คงภาษาไทยไว้ครบถ้วน (รวมสระ/วรรณยุกต์ที่เป็น combining marks) */
    public static function slugifyTitle(string $title): string
    {
        $title = trim($title);

        // \p{M} สำคัญมากสำหรับไทย: สระบน-ล่าง/วรรณยุกต์ (ิ ี ั ่ ้ ...) เป็น combining marks
        // ถ้าไม่ใส่จะถูกตัดหาย ทำให้ "กิจกรรม" กลายเป็น "กจกรรม"
        $clean = preg_replace('/[^\p{L}\p{M}\p{Nd}\s-]+/u', '', $title) ?? '';
        $clean = preg_replace('/[\s_]+/u', '-', trim($clean)) ?? '';
        $clean = trim($clean, '-');

        $slug = mb_strtolower($clean);
        // เก็บไม่เกิน 80 ตัวอักษรสำหรับความอ่านง่ายใน URL
        $slug = mb_substr($slug, 0, 80);
        $slug = rtrim($slug, '-');

        return $slug;
    }

    public function getRouteKey(): string
    {
        $slug = (string) $this->getAttribute('slug');

        return $slug !== '' ? $slug : (string) $this->getKey();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * ผูก route binding รองรับ: "{slug}" (ใหม่) และ "{id}" (ลิงก์เก่า)
     */
    public function resolveRouteBinding($value, $field = null)
    {
        // ตัวเลขล้วน = ลิงก์รูปแบบเดิม (id) — ยังเปิดได้ ไม่พัง
        if (ctype_digit((string) $value)) {
            return $this->findOrFail((int) $value);
        }

        return static::query()->where($field ?: $this->getRouteKeyName(), $value)->firstOrFail();
    }

    /** ใช้ใน migration: backfill slug ให้แถวเดิม */
    public static function backfillSlugs(): void
    {
        static::query()->whereNull('slug')->orWhere('slug', '')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $item->slug = static::generateUniqueSlug((string) $item->getAttribute('title'));
                $item->saveQuietly();
            }
        });
    }
}
