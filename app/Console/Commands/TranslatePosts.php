<?php

// ── [tag:2026-10-14-translation] Artisan: posts:translate ──
// Backfill โพสต์ (ประกาศ & ประกาศงาน) ที่มี EN ว่าง → แปลไทย→อังกฤษ

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\JobListing;
use App\Services\PostTranslationService;
use Illuminate\Console\Command;

class TranslatePosts extends Command
{
    /** field ที่ต้องแปล ดึงจากตารางการแปลของ Observer แต่ทำ public ที่นี่ตรง ๆ */
    protected const FIELD_MAP = [
        Announcement::class => ['title', 'content'],
        JobListing::class   => ['title', 'description', 'note'],
    ];

    protected $signature   = 'posts:translate {--model= : announcement|job|all (default all)}';
    protected $description = 'แปลข้อความโพสต์ไทย→อังกฤษ สำหรับโพสต์ที่ยังไม่มีคอลัมน์ *_en';

    public function handle(PostTranslationService $service)
    {
        $which = strtolower($this->option('model') ?: 'all');

        if ($which === 'all' || $which === 'announcement') {
            $this->translatePosts(Announcement::class, $service);
        }
        if ($which === 'all' || $which === 'job') {
            $this->translatePosts(JobListing::class, $service);
        }

        return 0;
    }

    protected function translatePosts(string $class, PostTranslationService $service): void
    {
        $short = class_basename($class);
        $map   = self::FIELD_MAP[$class] ?? [];

        if (empty($map)) {
            $this->warn("No fields mapped for {$short}");
            return;
        }

        $query = $class::query();

        foreach ($map as $field) {
            $query->orWhere(function ($q) use ($field) {
                $q->whereNull($field . '_en')->orWhere($field . '_en', '=', '');
            });
        }

        $posts = $query->get();

        $this->info("{$short}: พบ {$posts->count()} โพสต์ที่ยังไม่แปล");

        foreach ($posts as $post) {
            $texts = [];

            foreach ($map as $field) {
                $val = $post->getAttribute($field);

                if (is_string($val) && trim($val) !== '') {
                    $texts[$field] = $val;
                }
            }
            if (empty($texts)) continue;

            $out = $service->translateFields($texts);

            if (!empty($out)) {
                $post->fill($out)->saveQuietly();
                $this->line("  ✓ [#{$post->id}] " . $short);
            } else {
                $this->line("  ✗ [#{$post->id}] แปลไม่สำเร็จ");
            }
        }
    }
}
