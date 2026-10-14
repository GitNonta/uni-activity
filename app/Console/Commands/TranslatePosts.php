<?php

// ── [tag:2026-10-14-translation] Backfill คำแปลอังกฤษ ──
// รันคำสั่ง: php artisan posts:translate [--limit=100]
// ค่อย ๆ แปลข้อมั่งทีละแบทช์ เพื่อไม่ชน rate limit ของ MyMemory API

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\JobListing;
use App\Services\PostTranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TranslatePosts extends Command
{
    protected $signature   = 'posts:translate {--limit=200}';
    protected $description = 'แปลเนื้อหาโพสต์ (ประกาศ/งาน) ทุกรายการที่ยังไม่มี *_en';

    public function handle(PostTranslationService $translator): int
    {
        $limit = (int) $this->option('limit');
        $count = 0;

        // ── Announcement: title + content ──
        Announcement::query()
            ->where(function ($q) {
                $q->whereNull('title_en')->orWhereNull('content_en');
            })
            ->orderBy('id')
            ->limit($limit)
            ->each(function (Announcement $a) use (&$count, $translator) {
                $result = $translator->translateFields([
                    'title'   => (string) $a->title,
                    'content' => (string) $a->content,
                ]);

                if (!empty($result)) {
                    $a->timestamps = false;
                    $a->forceFill($result)->saveQuietly();
                    $a->timestamps = true;
                    $count++;
                }

                usleep(600_000);
            });

        // ── JobListing: หลายฟิลด์ก็แยกไปเงียบ ๆ ──
        JobListing::query()
            ->where(function ($q) {
                $q->whereNull('title_en')
                    ->orWhereNull('position_en')
                    ->orWhereNull('description_en')
                    ->orWhereNull('location_en')
                    ->orWhereNull('note_en');
            })
            ->orderBy('id')
            ->limit($limit)
            ->each(function (JobListing $j) use (&$count, $translator) {
                $result = $translator->translateFields([
                    'title'       => (string) $j->title,
                    'position'    => (string) $j->position,
                    'description' => (string) ($j->description ?? ''),
                    'location'    => (string) $j->location,
                    'note'        => (string) ($j->note ?? ''),
                ]);

                if (!empty($result)) {
                    $j->timestamps = false;
                    $j->forceFill($result)->saveQuietly();
                    $j->timestamps = true;
                    $count++;
                }

                usleep(600_000);
            });

        $this->info(Str::upper("backfill done — translated {$count} rows"));

        return self::SUCCESS;
    }
}
