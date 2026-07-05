<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: เพิ่ม device_fingerprint ใน attendances
 * ใช้สำหรับตรวจจับการเช็คอินแทนกัน (suspicious check-in)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->string('device_fingerprint', 64)->nullable()->after('ip_address')
                ->comment('SHA-256 hash ของ User-Agent + IP ตอนเช็คอิน');
            $table->boolean('is_suspicious')->default(false)->after('device_fingerprint')
                ->comment('flag ถ้าตรวจพบว่าน่าสงสัย (เช่น IP เดิมเช็คอินหลาย account)');

            $table->index('device_fingerprint', 'attendances_device_fingerprint_idx');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('attendances_device_fingerprint_idx');
            $table->dropColumn(['device_fingerprint', 'is_suspicious']);
        });
    }
};
