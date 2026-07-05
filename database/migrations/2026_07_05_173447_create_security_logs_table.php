<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: สร้างตาราง security_logs
 * บันทึกเหตุการณ์ความปลอดภัยเช่น multi-account login, suspicious check-in
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50)
                ->comment('multi_account_login | suspicious_checkin | device_mismatch');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable();
            $table->json('related_user_ids')->nullable()
                ->comment('รายการ user_id ที่เกี่ยวข้อง (JSON array)');
            $table->json('details')->nullable()
                ->comment('ข้อมูลเพิ่มเติม เช่น user_agent, activity_id');
            $table->boolean('is_reviewed')->default(false)
                ->comment('admin ตรวจสอบแล้วหรือยัง');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('event_type');
            $table->index('ip_address');
            $table->index('device_fingerprint');
            $table->index(['is_reviewed', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
