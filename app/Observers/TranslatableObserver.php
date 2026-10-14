<?php

// ── [tag:2026-10-14-translation] Translatable Observer ──
// Hook ตอนโพสต์ถูกบันทึก → แปลไทย→อังกฤษแบบเรียลไทม์ แล้วเขียนลงคอลัมน์ *_en พร้อมบันทึกเดียวกัน
// ถ้าแปลไม่สำเร็จ (เช่น API ล่ม / ไม่มีเน็ต) → ปล่อย *_en ว่าง ระบบจะ fallback ไปใช้ข้อความไทย

namespace App\Observers;

use App\Services\PostTranslationService;

class TranslatableObserver
{
    protected const MAP = [
        \App\Models\Announcement::class => ['title', 'content'],   // → *_en: title_en, content_en
        \App\Models\JobListing::class   => ['title', 'description', 'note'], // → *_en
    ];

    public function creating($model)
    {
        $this->translate($model);
    }

    public function updating($model)
    {
        // แปลใหม่เฉพาะเมื่อแก้ไขเนื้อหาต้นฉบับ (TH) → EN จะถูกบันทึกตาม dirty
        $fields = self::MAP[get_class($model)] ?? [];

        foreach ($fields as $field) {
            if ($model->isDirty($field)) {
                $this->translate($model);
                break;
            }
        }
    }

    protected function translate($model): void
    {
        $fields = self::MAP[get_class($model)] ?? [];

        if (empty($fields)) {
            return;
        }

        $texts = [];

        foreach ($fields as $field) {
            $val = $model->getAttribute($field);

            if (is_string($val) && trim($val) !== '') {
                $texts[$field] = $val;
            }
        }

        if (empty($texts)) {
            return;
        }

        try {
            $out = app(PostTranslationService::class)->translateFields($texts);

            if (!empty($out)) {
                // ห้าม syncChanges ที่นี่ — ต้องให้คอลัมน์ *_en คงอยู่ใน dirty
                // เพื่อให้ save()/insert เขียนลง DB พร้อมข้อความต้นฉบับไทย
                $model->fill($out);
            }
        } catch (\Throwable $e) {
            // fail-safe: แปลไม่ได้ก็ไม่ถูกบันทึกลง DB แสดง TH แทน
            report($e);
        }
    }
}
