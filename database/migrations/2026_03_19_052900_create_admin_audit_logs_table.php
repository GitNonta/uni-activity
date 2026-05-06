<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สร้างตาราง admin_audit_logs สำหรับบันทึกการกระทำของ Admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');   // ผู้ดำเนินการ (admin)
            $table->string('action', 50);        // ประเภทการกระทำ: create, update, delete, approve, reject, toggle, login, logout
            $table->string('model_type')->nullable();  // ชื่อ Model ที่ถูกกระทำ เช่น Activity, User
            $table->unsignedBigInteger('model_id')->nullable(); // ID ของ record ที่ถูกกระทำ
            $table->string('description');        // คำอธิบายสิ่งที่ทำ
            $table->json('old_values')->nullable(); // ค่าเดิมก่อนเปลี่ยน (สำหรับ update/delete)
            $table->json('new_values')->nullable(); // ค่าใหม่หลังเปลี่ยน (สำหรับ create/update)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
