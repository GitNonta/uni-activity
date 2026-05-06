<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สร้างตาราง activity_feedbacks สำหรับบันทึกการประเมินกิจกรรมของนักศึกษา
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('rating')->comment('คะแนนประเมิน 1-5 ดาว');
            $table->text('comment')->nullable()->comment('ความคิดเห็นเพิ่มเติม');
            $table->json('ratings')->nullable()->comment('คะแนนแยกตามหัวข้อ เช่น เนื้อหา, วิทยากร, สถานที่');
            $table->boolean('is_anonymous')->default(false)->comment('ประเมินแบบไม่ระบุตัวตน');
            $table->timestamps();

            $table->unique(['activity_id', 'user_id'], 'unique_feedback_per_user');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_feedbacks');
    }
};
