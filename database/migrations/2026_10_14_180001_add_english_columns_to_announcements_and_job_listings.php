<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ── [tag:2026-10-14-translation] เพิ่มคอลัมน์ _en สำหรับ cache คำแปล EN ──
// ตามสเกีมาจริง: announcements มี content (ไม่ใช่ body), job_listings มี description + note
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->text('title_en')->nullable()->after('title');
            $table->longText('content_en')->nullable()->after('content');
        });

        Schema::table('job_listings', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->longText('description_en')->nullable()->after('description');
            $table->text('note_en')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'content_en']);
        });

        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en', 'note_en']);
        });
    }
};
