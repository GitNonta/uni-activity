<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_listing_id')->constrained('job_listings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();      // ผู้ถาม (นักศึกษา)
            $table->text('question');                                                     // คำถาม
            $table->text('answer')->nullable();                                           // คำตอบจาก Admin
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete(); // ผู้ตอบ
            $table->dateTime('answered_at')->nullable();                                  // เวลาที่ตอบ
            $table->timestamps();

            $table->index('job_listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_inquiries');
    }
};
