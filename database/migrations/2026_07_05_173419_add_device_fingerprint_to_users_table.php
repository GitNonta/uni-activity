<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: เพิ่มคอลัมน์ติดตาม Device Fingerprint ใน users table
 * ใช้สำหรับตรวจจับการ login หลาย account จากเครื่องเดียวกัน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('last_login_ip', 45)->nullable()->after('last_seen_at');
            $table->timestamp('last_login_at')->nullable()->after('last_login_ip');
            $table->string('last_device_fingerprint', 64)->nullable()->after('last_login_at')
                ->comment('SHA-256 hash ของ User-Agent + IP สำหรับตรวจจับ multi-account');

            $table->index('last_device_fingerprint', 'users_device_fingerprint_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_device_fingerprint_idx');
            $table->dropColumn(['last_login_ip', 'last_login_at', 'last_device_fingerprint']);
        });
    }
};
