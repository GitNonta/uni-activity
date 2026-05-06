<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->string('title');                                              // หัวข้อพาดหัวงาน
            $table->enum('job_type', ['general', 'parttime'])->default('general'); // ประเภทงาน
            $table->string('position');                                            // ตำแหน่งงาน
            $table->integer('quota')->default(1);                                  // จำนวนรับสมัคร
            $table->string('work_period')->nullable();                             // ช่วงเวลางาน เช่น "08:00 – 17:00 น."
            $table->string('compensation')->nullable();                            // ค่าตอบแทน เช่น "400 บาท/วัน"
            $table->string('location');                                            // สถานที่ปฏิบัติงาน
            $table->date('start_date');                                             // วันเริ่มงาน
            $table->date('end_date')->nullable();                                  // วันสิ้นสุดงาน
            $table->string('dresscode')->nullable();                               // การแต่งกาย
            $table->enum('gender', ['male', 'female', 'any'])->default('any');     // เพศที่รับสมัคร
            $table->text('note')->nullable();                                      // หมายเหตุ (optional)
            $table->text('description')->nullable();                               // รายละเอียดงาน
            $table->enum('status', ['open', 'closed', 'completed'])->default('open'); // สถานะ
            $table->decimal('latitude', 10, 7)->nullable();                        // พิกัด GPS lat
            $table->decimal('longitude', 10, 7)->nullable();                       // พิกัด GPS lng
            $table->string('image_path')->nullable();                              // รูปภาพประกอบ
            $table->foreignId('created_by')->constrained('users');                 // ผู้สร้างประกาศ
            $table->timestamps();

            $table->index(['status', 'start_date']);
            $table->index('job_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
