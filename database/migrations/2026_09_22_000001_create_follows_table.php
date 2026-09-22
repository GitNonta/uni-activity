<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตารางความสัมพันธ์ "ติดตาม" (Follow)
 * follower_id = ผู้ที่กดติดตาม, following_id = ผู้ที่ถูกติดตาม
 * ใช้เพื่อให้ผู้ใช้รับข่าวสารจากผู้ที่ตนสนใจได้เร็วขึ้นและตรงจุดขึ้น
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // ผู้ใช้ติดตามผู้ใช้เดียวกันได้เพียงครั้งเดียว
            $table->unique(['follower_id', 'following_id']);
            // ดึงรายชื่อผู้ติดตาม/ผู้ที่ติดตาม ได้เร็ว
            $table->index('following_id');
            $table->index('follower_id');
        });

        // คอลัมน์ URL สำหรับ deep-link ของการแจ้งเตือน (เช่น กดแล้วไปหน้าโปรไฟล์ผู้สร้าง)
        Schema::table('notifications_custom', function (Blueprint $table): void {
            $table->string('url')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');

        Schema::table('notifications_custom', function (Blueprint $table): void {
            $table->dropColumn('url');
        });
    }
};
