<?php

// ── [tag:2026-10-14-translation] Translatable Observer ──
// Hook ตอนโพสต์ถูกบันทึก → แปลไทย→อังกฤษแล้ว cache ลงคอลัมน์ *_en (แยกเพียง 1 ครั้ง)
// การแปลถูกทำแบบ async-ish (queue:afterResponse ถ้ามี) เพื่อไม่ให้ save ช้า

namespace App\Observers;

use App\Services\PostTranslationService;

class TranslatableObserver
{
    protected const MAP = [
        \App\Models\Announcement::class => ['title', 'body'],
        \App\Models\JobListing::class   => ['title', 'description', 'requirements', 'benefits'],
    ];

    public function creating($model)
    {
        $this->translate($model);
    }

    public function updating($model)
    {
        // แปลใหม่เฉพาะเมื่อมีการแก้ไขเนื้อหาต้นฉบับ (TH)
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

        $service = app(PostTranslationService::class);
        $texts   = [];

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
            $out = $service->translateFields($texts);

            if (!empty($out)) {
                // ทำแบบเงียบ ๆ เพื่อไม่ให้ dirty สาขาอื่น (แปลแค่ cache EN)
                $model->fill($out)->syncChanges();
            }
        } catch (\Throwable $e) {
            // fail-safe: ถ้าแปลไม่สำเร็จให้แสดงไทยแทน (fallback)
            report($e);
        }
    }
}
