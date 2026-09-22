<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * URL แบบมีชื่อ (human-readable):
 *  - users.username        → /users/{username}
 *  - activities.slug       → /activities/{slug}
 *  - announcements.slug    → /announcements/{slug}
 *  - job_listings.slug     → /jobs/{slug}
 * เติมค่าให้แถวเดิมทั้งหมด (backfill) และตั้ง unique index
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── users.username ───────────────────────────────────────────
        Schema::table('users', function (Blueprint $t): void {
            $t->string('username')->nullable()->after('id');
        });

        DB::table('users')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $u) {
                DB::table('users')
                    ->where('id', $u->id)
                    ->update(['username' => self::uniqueUsername($u)]);
            }
        });

        Schema::table('users', function (Blueprint $t): void {
            $t->string('username', 64)->nullable(false)->unique()->change();
        });

        // ── activities.slug / announcements.slug / job_listings.slug ──
        $slugTables = [
            'activities'    => \App\Models\Activity::class,
            'announcements' => \App\Models\Announcement::class,
            'job_listings'  => \App\Models\JobListing::class,
        ];

        foreach ($slugTables as $table => $modelClass) {
            Schema::table($table, function (Blueprint $t): void {
                $t->string('slug')->nullable()->after('id');
            });

            $modelClass::query()->whereNull('slug')->orWhere('slug', '')->chunkById(200, function ($items) use ($modelClass): void {
                foreach ($items as $item) {
                    $item->slug = $modelClass::generateUniqueSlug((string) $item->title);
                    $item->saveQuietly();
                }
            });

            Schema::table($table, function (Blueprint $t): void {
                $t->string('slug', 191)->nullable(false)->unique()->change();
            });
        }
    }

    /** ตั้งชื่อผู้ใช้ที่ไม่ซ้ำจาก english_name/full_name/email/student_id */
    private static function uniqueUsername(object $u): string
    {
        $candidates = array_values(array_filter([
            self::normalize($u->english_name ?? null),
            self::normalize($u->full_name ?? null),
            self::normalize($u->email ? explode('@', $u->email)[0] : null),
            $u->student_id ? 'user' . $u->student_id : null,
            'user' . $u->id,
        ]));

        $base = $candidates[0];
        $slug = $base;
        $i = 0;
        while (DB::table('users')->where('username', $slug)->where('id', '!=', $u->id)->exists()) {
            $i++;
            $slug = Str::limit($base, 60, '') . '-' . $i;
        }

        return $slug;
    }

    /** ASCII-friendly username: ตัดเครื่องหมาย คง a-z0-9 และขีด */
    private static function normalize(?string $s): ?string
    {
        if ($s === null || trim($s) === '') {
            return null;
        }

        $s = Str::ascii(trim($s));
        $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '');
        $s = trim($s, '-');

        return $s !== '' ? Str::limit($s, 64, '') : null;
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropUnique(['username']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('username'));

        foreach (['activities', 'announcements', 'job_listings'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                $t->dropUnique([$table . '_slug_unique']);
            });
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('slug'));
        }
    }
};
